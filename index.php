<?php
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
    <title>My News Web - パーソナライズニュース</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="header-title">
        <h1>My News Web</h1>
        <p>複数のニュースサイトから好みのニュースをピックアップ</p>
    </div>
    <div class="header-actions">
        <button id="mobile-menu-toggle" class="secondary-button mobile-menu-button" type="button" aria-label="検索条件を開く" aria-controls="filter-panel" aria-expanded="false" data-filter-toggle data-label-open="検索条件を開く" data-label-close="検索条件を閉じる">
            <span aria-hidden="true">☰</span>
        </button>
        <a href="settings.php" class="ghost-button">設定を編集</a>
        <button id="refresh-news" class="primary-button">ニュースを更新</button>
    </div>
</header>

<main class="app-main">
    <aside id="filter-panel" class="filter-panel">
        <button type="button" id="close-filters" class="filter-close" aria-label="フィルターを閉じる">閉じる</button>
        <section>
            <h2>ビュー</h2>
            <div id="view-list" class="view-list"></div>
        </section>
    </aside>
    <div id="filter-backdrop" class="filter-backdrop" hidden></div>
    <section class="news-area">
        <div class="news-toolbar">
            <button type="button" id="toggle-filters" class="secondary-button filter-toggle" aria-expanded="false" aria-controls="filter-panel" data-filter-toggle>フィルター</button>
            <span id="status-text">ニュースを読み込んでください</span>
        </div>
        <div id="news-grid" class="news-grid" aria-live="polite"></div>
    </section>
</main>

<script>
    window.NEWS_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
