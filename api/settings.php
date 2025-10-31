<?php
header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/../config/sources.json';

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
if (!$input || !isset($input['config'])) {
    http_response_code(400);
    echo json_encode(['error' => '設定データが不正です。']);
    exit;
}

$config = $input['config'];
if (!is_array($config) || !isset($config['sources']) || !isset($config['categories'])) {
    http_response_code(400);
    echo json_encode(['error' => '必須項目が不足しています。']);
    exit;
}

$result = file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => '設定の保存に失敗しました。']);
    exit;
}

echo json_encode(['status' => 'ok']);
