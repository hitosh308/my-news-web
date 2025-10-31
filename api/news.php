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

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'header' => [
                'User-Agent: MyNewsWebAggregator/1.0'
            ]
        ]
    ]);

    $content = @file_get_contents($source['url'], false, $context);
    if ($content === false) {
        return null;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) {
        return null;
    }

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

    return '';
}
