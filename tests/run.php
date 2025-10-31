<?php

declare(strict_types=1);

require __DIR__ . '/../api/lib/news_functions.php';

$tests = [];

$tests['fetchSourceItems_reads_local_fixture'] = function (): void {
    $source = [
        'id' => 'sample',
        'name' => 'Sample Feed',
        'type' => 'rss',
        'url' => __DIR__ . '/fixtures/sample_rss.xml'
    ];

    $items = fetchSourceItems($source);
    if ($items === null) {
        throw new RuntimeException('Expected items, got null');
    }

    if (count($items) !== 2) {
        throw new RuntimeException('Expected 2 items, got ' . count($items));
    }

    if ($items[0]['title'] !== 'First Article') {
        throw new RuntimeException('Unexpected first item title: ' . $items[0]['title']);
    }

    if ($items[0]['image'] !== 'https://example.com/images/1.jpg') {
        throw new RuntimeException('Expected image extracted from description.');
    }

    if ($items[1]['image'] !== 'https://example.com/images/2.jpg') {
        throw new RuntimeException('Expected image extracted from media thumbnail.');
    }

    if ($items[0]['published'] !== '2024-04-24 10:00') {
        throw new RuntimeException('Unexpected formatted date: ' . $items[0]['published']);
    }
};

$tests['fetchSourceItems_returns_null_for_invalid_file'] = function (): void {
    $source = [
        'id' => 'missing',
        'name' => 'Missing Feed',
        'type' => 'rss',
        'url' => __DIR__ . '/fixtures/does_not_exist.xml'
    ];

    if (fetchSourceItems($source) !== null) {
        throw new RuntimeException('Expected null for missing feed.');
    }
};

$tests['matchesKeywords_filters_correctly'] = function (): void {
    $item = [
        'title' => 'AI breakthrough',
        'description' => 'New AI model surpasses expectations.'
    ];

    if (!matchesKeywords($item, ['ai'])) {
        throw new RuntimeException('Keyword match should succeed.');
    }

    if (matchesKeywords($item, ['finance'])) {
        throw new RuntimeException('Unexpected keyword match.');
    }
};

$tests['normalizeEncoding_converts_non_utf8'] = function (): void {
    $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?><rss><channel><title>caf\xE9</title></channel></rss>";
    $converted = normalizeEncoding($xml);
    if (strpos($converted, 'encoding="UTF-8"') === false) {
        throw new RuntimeException('Expected encoding to be normalized to UTF-8.');
    }
};

$tests['fetchFeedContent_retries_and_fails'] = function (): void {
    $start = microtime(true);
    $result = fetchFeedContent('http://127.0.0.1:65500/nonexistent', [], 2);
    if ($result !== null) {
        throw new RuntimeException('Expected null for unreachable endpoint.');
    }
    $duration = microtime(true) - $start;
    if ($duration < 0.2) {
        throw new RuntimeException('Expected retry delay to make call take longer.');
    }
};

$failures = [];
foreach ($tests as $name => $test) {
    try {
        $test();
        echo ".";
    } catch (Throwable $exception) {
        $failures[$name] = $exception;
        echo "F";
    }
}

echo PHP_EOL;

if ($failures) {
    foreach ($failures as $name => $exception) {
        fwrite(STDERR, sprintf("[%s] %s\n", $name, $exception->getMessage()));
    }
    exit(1);
}

echo "All tests passed" . PHP_EOL;
