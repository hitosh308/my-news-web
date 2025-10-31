<?php

declare(strict_types=1);

function fetchSourceItems(array $source): ?array
{
    if (($source['type'] ?? '') !== 'rss') {
        return null;
    }

    $content = fetchFeedContent($source['url'], $source['request'] ?? []);
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
        $keyword = trim(mb_strtolower((string)$keyword));
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

    try {
        $dateTime = new DateTimeImmutable($date);
    } catch (Exception $exception) {
        return $date;
    }

    return $dateTime->format('Y-m-d H:i');
}

function extractImageFromEntry(SimpleXMLElement $entry): string
{
    if (isset($entry->enclosure)) {
        foreach ($entry->enclosure as $enclosure) {
            $url = getXmlAttribute($enclosure, 'url');
            $type = getXmlAttribute($enclosure, 'type');
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
                $url = getXmlAttribute($content, 'url');
                $type = getXmlAttribute($content, 'type');
                if ($url !== '' && ($type === '' || stripos($type, 'image') !== false)) {
                    return $url;
                }
            }
        }
        if (isset($media->thumbnail)) {
            foreach ($media->thumbnail as $thumb) {
                $url = getXmlAttribute($thumb, 'url');
                if ($url !== '') {
                    return $url;
                }
            }
        }
    }

    $description = (string)($entry->description ?? $entry->summary ?? '');
    if ($description !== '' && preg_match('/<img[^>]+src=\"([^\"]+)\"/i', $description, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    if (isset($namespaces['content'])) {
        $contentNode = $entry->children($namespaces['content']);
        if (isset($contentNode->encoded)) {
            $encoded = (string)$contentNode->encoded;
            if ($encoded !== '' && preg_match('/<img[^>]+src=\"([^\"]+)\"/i', $encoded, $matches)) {
                return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
            }
        }
    }

    return '';
}

function fetchFeedContent(string $url, array $options = [], int $attempts = 3): ?string
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null || $scheme === false || $scheme === '' || $scheme === 'file') {
        $localContent = @file_get_contents($url);
        if ($localContent !== false) {
            return $localContent;
        }
    }

    $userAgent = $options['userAgent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0 Safari/537.36';
    $headers = [
        'Accept: application/rss+xml, application/xml;q=0.9, */*;q=0.8',
        'Accept-Language: ja,en;q=0.8'
    ];

    if (!empty($options['referer'])) {
        $headers[] = 'Referer: ' . $options['referer'];
    }

    if (!empty($options['headers']) && is_array($options['headers'])) {
        foreach ($options['headers'] as $header) {
            if (is_string($header) && $header !== '') {
                $headers[] = $header;
            }
        }
    }

    for ($attempt = 1; $attempt <= max(1, $attempts); $attempt++) {
        $content = fetchFeedContentWithCurl($url, $headers, $userAgent);
        if ($content !== null) {
            return $content;
        }

        $content = fetchFeedContentWithStream($url, $headers, $userAgent);
        if ($content !== null) {
            return $content;
        }

        if ($attempt < $attempts) {
            usleep(200000);
        }
    }

    return null;
}

function fetchFeedContentWithCurl(string $url, array $headers, string $userAgent): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_1_1')
            ? CURL_HTTP_VERSION_1_1
            : (defined('CURL_HTTP_VERSION_NONE') ? CURL_HTTP_VERSION_NONE : 0),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => ''
    ]);

    $content = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($content !== false && $status < 400) {
        return $content;
    }

    return null;
}

function fetchFeedContentWithStream(string $url, array $headers, string $userAgent): ?string
{
    $normalizedHeaders = $headers;
    $hasUserAgentHeader = false;
    foreach ($normalizedHeaders as $header) {
        if (stripos($header, 'User-Agent:') === 0) {
            $hasUserAgentHeader = true;
            break;
        }
    }
    if (!$hasUserAgentHeader) {
        $normalizedHeaders[] = 'User-Agent: ' . $userAgent;
    }
    if (!array_filter($normalizedHeaders, static fn($header) => stripos($header, 'Accept-Encoding:') === 0)) {
        $normalizedHeaders[] = 'Accept-Encoding: gzip, deflate';
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => implode("\r\n", $normalizedHeaders)
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

    return decodeContentEncoding($content, $meta['wrapper_data'] ?? []);
}

function decodeContentEncoding(string $content, array $headers): string
{
    foreach ($headers as $header) {
        if (stripos($header, 'Content-Encoding: gzip') !== false && function_exists('gzdecode')) {
            $decoded = @gzdecode($content);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        if (stripos($header, 'Content-Encoding: deflate') !== false) {
            $decoded = function_exists('gzinflate') ? @gzinflate($content) : false;
            if ($decoded === false && function_exists('gzuncompress')) {
                $decoded = @gzuncompress($content);
            }
            if ($decoded !== false) {
                return $decoded;
            }
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

function getXmlAttribute(SimpleXMLElement $element, string $name): string
{
    $attributes = $element->attributes();
    if ($attributes !== null && isset($attributes[$name])) {
        return (string)$attributes[$name];
    }

    if (isset($element[$name])) {
        return (string)$element[$name];
    }

    return '';
}
