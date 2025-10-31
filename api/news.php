<?php
header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/../config/sources.json';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => '設定ファイルが見つかりません。']);
    exit;
}

$config = json_decode(file_get_contents($configPath), true);
if (!$config) {
    http_response_code(500);
    echo json_encode(['error' => '設定ファイルの読み込みに失敗しました。']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$selectedCategories = isset($input['categories']) && is_array($input['categories']) ? $input['categories'] : [];
$selectedSources = isset($input['sources']) && is_array($input['sources']) ? $input['sources'] : [];
$keywords = isset($input['keywords']) && is_array($input['keywords']) ? $input['keywords'] : [];

$results = [];
foreach ($config['sources'] as $source) {
    if (!empty($selectedSources) && !in_array($source['id'], $selectedSources, true)) {
        continue;
    }

    $items = fetchSourceItems($source);
    if ($items === null) {
        $results[] = [
            'source' => $source['name'],
            'sourceId' => $source['id'],
            'error' => 'ニュースの取得に失敗しました。'
        ];
        continue;
    }

    $filtered = [];
    foreach ($items as $item) {
        $matchesCategory = empty($selectedCategories) || !empty(array_intersect($selectedCategories, $source['defaultCategories'] ?? []));
        $matchesKeyword = matchesKeywords($item, $keywords);
        if ($matchesCategory && $matchesKeyword) {
            $filtered[] = $item;
        }
    }

    $results[] = [
        'source' => $source['name'],
        'sourceId' => $source['id'],
        'categories' => $source['defaultCategories'] ?? [],
        'items' => $filtered,
        'empty' => empty($filtered)
    ];
}

echo json_encode([
    'generatedAt' => date('c'),
    'sources' => $results
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

function fetchSourceItems(array $source): ?array
{
    if (($source['type'] ?? '') !== 'rss') {
        return null;
    }

    $content = fetchFeedContent($source['url']);
    if ($content === null) {
        return null;
    }

    $content = normalizeEncoding($content);

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) {
        libxml_clear_errors();
        return null;
    }
    libxml_clear_errors();

    $items = [];
    $entries = $xml->channel->item ?? $xml->entry ?? [];
    $count = 0;
    foreach ($entries as $entry) {
        if ($count >= 12) {
            break;
        }
        $link = '';
        if (isset($entry->link)) {
            if ($entry->link instanceof SimpleXMLElement && isset($entry->link['href'])) {
                $link = (string)$entry->link['href'];
            } else {
                $link = (string)$entry->link;
            }
        }

        $items[] = [
            'title' => trim((string)($entry->title ?? '')),
            'link' => $link,
            'description' => mb_substr(strip_tags((string)($entry->description ?? $entry->summary ?? '')), 0, 160),
            'published' => formatDate((string)($entry->pubDate ?? $entry->updated ?? '')),
            'guid' => (string)($entry->guid ?? ''),
            'image' => extractImageFromEntry($entry)
        ];
        $count++;
    }

    return $items;
}

function matchesKeywords(array $item, array $keywords): bool
{
    if (empty($keywords)) {
        return true;
    }

    $haystack = mb_strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? ''));
    foreach ($keywords as $keyword) {
        $keyword = trim(mb_strtolower($keyword));
        if ($keyword === '') {
            continue;
        }
        if (mb_strpos($haystack, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

function formatDate(string $date): string
{
    if ($date === '') {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('Y-m-d H:i', $timestamp);
}

function extractImageFromEntry(SimpleXMLElement $entry): string
{
    if (isset($entry->enclosure)) {
        foreach ($entry->enclosure as $enclosure) {
            $url = (string)($enclosure['url'] ?? '');
            $type = (string)($enclosure['type'] ?? '');
            if ($url !== '' && ($type === '' || stripos($type, 'image') !== false)) {
                return $url;
            }
        }
    }

    $namespaces = $entry->getNameSpaces(true);
    if (isset($namespaces['media'])) {
        $media = $entry->children($namespaces['media']);
        if (isset($media->content)) {
            foreach ($media->content as $content) {
                $url = (string)($content['url'] ?? '');
                $type = (string)($content['type'] ?? '');
                if ($url !== '' && ($type === '' || stripos($type, 'image') !== false)) {
                    return $url;
                }
            }
        }
        if (isset($media->thumbnail)) {
            foreach ($media->thumbnail as $thumb) {
                $url = (string)($thumb['url'] ?? '');
                if ($url !== '') {
                    return $url;
                }
            }
        }
    }

    $description = (string)($entry->description ?? $entry->summary ?? '');
    if ($description !== '' && preg_match('/<img[^>]+src="([^"]+)"/i', $description, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    if (isset($namespaces['content'])) {
        $contentNode = $entry->children($namespaces['content']);
        if (isset($contentNode->encoded)) {
            $encoded = (string)$contentNode->encoded;
            if ($encoded !== '' && preg_match('/<img[^>]+src="([^"]+)"/i', $encoded, $matches)) {
                return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
            }
        }
    }

    return '';
}

function fetchFeedContent(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/xml;q=0.9, */*;q=0.8',
                'Accept-Language: ja,en;q=0.8'
            ],
            CURLOPT_ENCODING => ''
        ]);

        $content = curl_exec($ch);
        if ($content === false) {
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status >= 400) {
            return null;
        }

        return $content;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => implode("\r\n", [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0 Safari/537.36',
                'Accept: application/rss+xml, application/xml;q=0.9, */*;q=0.8',
                'Accept-Language: ja,en;q=0.8',
                'Accept-Encoding: gzip, deflate'
            ])
        ]
    ]);

    $handle = @fopen($url, 'rb', false, $context);
    if ($handle === false) {
        return null;
    }

    $content = stream_get_contents($handle);
    $meta = stream_get_meta_data($handle);
    fclose($handle);

    if ($content === false) {
        return null;
    }

    $headers = $meta['wrapper_data'] ?? [];
    foreach ($headers as $header) {
        if (stripos($header, 'Content-Encoding: gzip') !== false && function_exists('gzdecode')) {
            $decoded = @gzdecode($content);
            if ($decoded !== false) {
                $content = $decoded;
            }
            break;
        }
        if (stripos($header, 'Content-Encoding: deflate') !== false) {
            $decoded = function_exists('gzinflate') ? @gzinflate($content) : false;
            if ($decoded === false && function_exists('gzuncompress')) {
                $decoded = @gzuncompress($content);
            }
            if ($decoded !== false) {
                $content = $decoded;
            }
            break;
        }
    }

    return $content;
}

function normalizeEncoding(string $content): string
{
    if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $content, $matches)) {
        $encoding = strtoupper(trim($matches[1]));
        if ($encoding !== '' && $encoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($content, 'UTF-8', $encoding);
            if ($converted !== false) {
                return preg_replace('/(<\?xml[^>]*encoding=)["\'][^"\']+["\']/', '$1"UTF-8"', $converted, 1);
            }
        }
    }

    return $content;
}
