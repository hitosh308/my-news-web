<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/../config/views.json';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => '設定ファイルが見つかりません。']);
    exit;
}

$existingConfig = json_decode((string)file_get_contents($configPath), true);
if (!is_array($existingConfig)) {
    $existingConfig = ['views' => [], 'conditions' => ['keywords' => []]];
}
if (!isset($existingConfig['views']) || !is_array($existingConfig['views'])) {
    $existingConfig['views'] = [];
}
if (!isset($existingConfig['conditions']) || !is_array($existingConfig['conditions'])) {
    $existingConfig['conditions'] = ['keywords' => []];
}
if (!isset($existingConfig['conditions']['keywords']) || !is_array($existingConfig['conditions']['keywords'])) {
    $existingConfig['conditions']['keywords'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($existingConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
$maxAutoNumber = 0;

$normalizeStringList = static function ($values): array {
    if (!is_array($values)) {
        return [];
    }

    $normalized = [];
    foreach ($values as $value) {
        if (!is_string($value)) {
            continue;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || isset($normalized[$trimmed])) {
            continue;
        }
        $normalized[$trimmed] = true;
    }

    return array_keys($normalized);
};

$collectAutoNumber = static function (string $id) use (&$maxAutoNumber): void {
    if (preg_match('/^view-(\d+)$/', $id, $matches)) {
        $number = (int)$matches[1];
        if ($number > $maxAutoNumber) {
            $maxAutoNumber = $number;
        }
    }
};

foreach ($existingConfig['views'] as $existingView) {
    if (!is_array($existingView)) {
        continue;
    }
    $existingId = isset($existingView['id']) && is_string($existingView['id'])
        ? trim($existingView['id'])
        : '';
    if ($existingId === '') {
        continue;
    }
    $collectAutoNumber($existingId);
}

foreach ($views as $view) {
    if (!is_array($view)) {
        continue;
    }

    $id = isset($view['id']) && is_string($view['id']) ? trim($view['id']) : '';
    $name = isset($view['name']) && is_string($view['name']) ? trim($view['name']) : '';

    if ($name === '') {
        continue;
    }

    if ($id !== '') {
        if (isset($idRegistry[$id])) {
            continue;
        }
        $collectAutoNumber($id);
    } else {
        do {
            $maxAutoNumber++;
            $id = 'view-' . $maxAutoNumber;
        } while (isset($idRegistry[$id]));
    }

    $idRegistry[$id] = true;

    $normalizedViews[] = [
        'id' => $id,
        'name' => $name,
        'categories' => $normalizeStringList($view['categories'] ?? []),
        'sources' => $normalizeStringList($view['sources'] ?? []),
        'keywords' => $normalizeStringList($view['keywords'] ?? [])
    ];
}

$normalizedConditions = [];
$normalizedConditions['keywords'] = $normalizeStringList($conditions['keywords'] ?? []);

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

echo json_encode([
    'status' => 'ok',
    'views' => $normalizedViews,
    'conditions' => $normalizedConditions
]);
