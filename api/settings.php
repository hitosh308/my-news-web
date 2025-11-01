<?php
header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/../config/views.json';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => '設定ファイルが見つかりません。']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $config = file_get_contents($configPath);
    echo $config;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '許可されていないメソッドです。']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => '設定データが不正です。']);
    exit;
}

$views = $input['views'] ?? null;
if (!is_array($views)) {
    http_response_code(400);
    echo json_encode(['error' => 'ビューの形式が不正です。']);
    exit;
}

$conditions = $input['conditions'] ?? [];
if (!is_array($conditions)) {
    $conditions = [];
}

$normalizedViews = [];
$idRegistry = [];
foreach ($views as $view) {
    if (!is_array($view)) {
        continue;
    }

    $id = isset($view['id']) && is_string($view['id']) ? trim($view['id']) : '';
    $name = isset($view['name']) && is_string($view['name']) ? trim($view['name']) : '';

    if ($id === '' || $name === '') {
        continue;
    }

    if (isset($idRegistry[$id])) {
        continue;
    }
    $idRegistry[$id] = true;

    $normalizedViews[] = [
        'id' => $id,
        'name' => $name,
        'categories' => array_values(array_unique(array_filter(isset($view['categories']) && is_array($view['categories']) ? $view['categories'] : [], 'strlen'))),
        'sources' => array_values(array_unique(array_filter(isset($view['sources']) && is_array($view['sources']) ? $view['sources'] : [], 'strlen'))),
        'keywords' => array_values(array_unique(array_filter(isset($view['keywords']) && is_array($view['keywords']) ? $view['keywords'] : [], 'strlen')))
    ];
}

$normalizedConditions = [];
if (isset($conditions['keywords']) && is_array($conditions['keywords'])) {
    $normalizedConditions['keywords'] = array_values(array_unique(array_filter($conditions['keywords'], 'strlen')));
} else {
    $normalizedConditions['keywords'] = [];
}

$payload = [
    'views' => $normalizedViews,
    'conditions' => $normalizedConditions
];

$result = file_put_contents($configPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => '設定の保存に失敗しました。']);
    exit;
}

echo json_encode(['status' => 'ok']);
