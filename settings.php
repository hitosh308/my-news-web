<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$sourceConfigPath = __DIR__ . '/config/sources.json';
$viewConfigPath = __DIR__ . '/config/views.json';

$sourcesConfig = [];
if (file_exists($sourceConfigPath)) {
    $sourcesConfig = json_decode(file_get_contents($sourceConfigPath), true) ?: [];
}

$viewsConfig = [];
if (file_exists($viewConfigPath)) {
    $viewsConfig = json_decode(file_get_contents($viewConfigPath), true) ?: [];
}

$config = [
    'categories' => $sourcesConfig['categories'] ?? [],
    'sources' => $sourcesConfig['sources'] ?? [],
    'views' => $viewsConfig['views'] ?? [],
    'conditions' => $viewsConfig['conditions'] ?? ['keywords' => []]
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My News Web - 表示設定</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="header-title">
        <h1>表示する内容の設定</h1>
        <p>ビューの内容やキーワードを画面から編集できます</p>
    </div>
    <div class="header-actions">
        <a href="index.php" class="ghost-button">トップに戻る</a>
    </div>
</header>

<main class="settings-main">
    <section class="settings-card">
        <p>表示したいカテゴリ・ニュースサイト・キーワードの組み合わせをビューとして登録できます。</p>
        <div class="view-editor-header">
            <h2>ビュー一覧</h2>
            <button id="add-view" type="button" class="secondary-button">ビューを追加</button>
        </div>
        <div id="view-editor" class="view-editor"></div>
        <section class="default-keywords">
            <h2>共通のキーワード</h2>
            <p>どのビューにも含めたいキーワードがあればカンマ区切りで入力してください。</p>
            <input type="text" id="default-keywords" placeholder="例: AI, 経済">
        </section>
        <div class="settings-actions">
            <button id="save-settings" class="primary-button">保存する</button>
        </div>
        <p class="settings-status" id="settings-status"></p>
    </section>
</main>

<script>
    window.NEWS_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
</script>
<script src="assets/js/settings.js"></script>
</body>
</html>
