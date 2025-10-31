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
        <button id="open-settings" class="ghost-button">設定を編集</button>
        <button id="refresh-news" class="primary-button">ニュースを更新</button>
    </div>
</header>

<main class="app-main">
    <aside class="filter-panel">
        <section>
            <h2>カテゴリ</h2>
            <div id="category-list" class="checkbox-list"></div>
        </section>
        <section>
            <h2>ニュースサイト</h2>
            <div id="source-list" class="checkbox-list"></div>
        </section>
        <section>
            <h2>キーワード</h2>
            <div class="keyword-controls">
                <input type="text" id="keyword-input" placeholder="キーワードを入力" />
                <button id="add-keyword" class="secondary-button">追加</button>
            </div>
            <div id="keyword-tags" class="keyword-tags"></div>
        </section>
        <section>
            <h2>レイアウトモード</h2>
            <div class="layout-toggle">
                <button data-layout="grid" class="layout-button active">カード</button>
                <button data-layout="column" class="layout-button">カラム</button>
            </div>
        </section>
    </aside>
    <section class="news-area">
        <div class="news-toolbar">
            <span id="status-text">ニュースを読み込んでください</span>
        </div>
        <div id="news-grid" class="news-grid" aria-live="polite"></div>
    </section>
</main>

<div id="settings-modal" class="modal" hidden>
    <div class="modal-content">
        <header class="modal-header">
            <h2>設定の編集</h2>
            <button id="close-settings" class="ghost-button">閉じる</button>
        </header>
        <p>JSON形式でニュースの取得元やカテゴリ・条件を編集できます。</p>
        <textarea id="config-editor" rows="18" spellcheck="false"></textarea>
        <div class="modal-actions">
            <button id="save-settings" class="primary-button">保存する</button>
        </div>
    </div>
</div>

<script>
    window.NEWS_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
