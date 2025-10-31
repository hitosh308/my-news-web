<?php
$configPath = __DIR__ . '/config/sources.json';
$config = [];
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true) ?: [];
}
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
        <p>JSONを編集してニュースビューやフィルターを管理します</p>
    </div>
    <div class="header-actions">
        <a href="index.php" class="ghost-button">トップに戻る</a>
    </div>
</header>

<main class="settings-main">
    <section class="settings-card">
        <p>ビューやカテゴリ、ニュースサイトの設定を編集して保存できます。</p>
        <textarea id="config-editor" spellcheck="false"></textarea>
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
