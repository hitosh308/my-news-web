# My News Web

複数のニュースサイトのRSSフィードを読み込み、カテゴリやキーワード、サイト別の条件でフィルタリングしたパーソナライズドなニュース一覧を表示するPHP製のシングルページアプリケーションです。設定はJSONで保存され、ブラウザ上から編集できます。

## 特長

- 複数サイトを同時に表示し、カテゴリやキーワードで絞り込み
- ドラッグ&ドロップでニュースカードやセクションの配置を自由に変更
- JSON設定をブラウザ上から直接編集してニュース取得元や条件をカスタマイズ
- レスポンシブレイアウトでPC/モバイルに対応

## 動作要件

- PHP 8 以上
- 外部RSSフィードへアクセスできるネットワーク環境

## セットアップ

```bash
php -S 0.0.0.0:8080
```

上記で開発用サーバーを立ち上げ、`http://localhost:8080` にアクセスします。

## JSON設定について

`config/sources.json` に以下の形式で設定を保存します。サイトの設定画面から直接編集することも可能です。

```json
{
  "categories": ["テクノロジー", "ビジネス"],
  "conditions": {
    "keywords": ["AI", "スタートアップ"]
  },
  "sources": [
    {
      "id": "nhk",
      "name": "NHKニュース",
      "type": "rss",
      "url": "https://www3.nhk.or.jp/rss/news/cat0.xml",
      "defaultCategories": ["政治"],
      "defaultKeywords": []
    }
  ]
}
```

- `categories`: UIで選択できるカテゴリ一覧。
- `conditions.keywords`: 初期状態で有効なキーワード。
- `sources`: ニュース取得元。
  - `type` は現状 `rss` のみ対応。
  - `defaultCategories` はそのサイトが属するカテゴリ。
  - `defaultKeywords` はそのサイト固有のキーワード初期値 (UIでは情報用途)。

## ライセンス

MIT
