<?php
require_once __DIR__ . '/lib/news_functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
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
