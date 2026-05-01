## POSアプリ 技術スタック・システム構成

### 概要

本システムは、フロントエンドとバックエンドを分離した **SPA + API構成** のPOSアプリです。
開発環境は `WSL + Docker` を前提とし、環境差異を排除します。

---

## 技術スタック

### フロントエンド

| 項目     | 技術           | 役割           |
| ------ | ------------ | ------------ |
| ビルドツール | Vite         | 高速な開発環境      |
| UI     | React        | コンポーネントベースUI |
| 言語     | TypeScript   | 型安全          |
| ルーティング | React Router | 画面遷移管理       |
| CSS    | Tailwind CSS | UIスタイリング     |

---

### バックエンド

| 項目    | 技術       | 役割       |
| ----- | -------- | -------- |
| 言語    | PHP（バニラ） | API処理    |
| DB接続  | PDO      | 安全なSQL実行 |
| API形式 | JSON     | フロントとの通信 |

---

### データベース

| 項目    | 技術         | 役割     |
| ----- | ---------- | ------ |
| DB    | MySQL      | データ永続化 |
| 管理ツール | phpMyAdmin | DB操作   |

---

### インフラ・開発環境

| 項目         | 技術             | 役割       |
| ---------- | -------------- | -------- |
| OS         | WSL2           | Linux環境  |
| コンテナ       | Docker         | 環境統一     |
| オーケストレーション | Docker Compose | 複数サービス管理 |

---

## システム構成

```txt
[ Browser ]
     ↓
[ React (Vite) ]
     ↓ fetch()
[ PHP API ]
     ↓ PDO
[ MySQL ]
```

---

## コンテナ構成

| サービス       | 内容           | ポート  |
| ---------- | ------------ | ---- |
| frontend   | Vite開発サーバ    | 3000 |
| backend    | Apache + PHP | 80 |
| db         | MySQL        | 3306 |
| phpmyadmin | DB管理         | 8081 |

---

## ディレクトリ構成

```txt
frontend/
   ├── src/
   │   ├── pages/
   │   ├── components/
   │   ├── routes/
   │   └── main.tsx
   └── package.json

pos_2026/
   ├── api/
   │   ├── products/
   │   ├── sales/
   │   └── categories/
   ├── lib/
   │   └── Database.php
   └── .env
```

---

## API設計方針

* REST風エンドポイントを採用
* JSON形式で通信
* フロントは `fetch()` を利用

### 例

```txt
GET    /api/products         商品一覧取得
POST   /api/products         商品登録
PUT    /api/products/:id     商品更新
DELETE /api/products/:id     商品削除

GET    /api/sales            売上一覧
POST   /api/sales            売上登録
GET    /api/sales/:id        売上詳細
```

---

## 特徴

### この構成のメリット

* Webアプリの基本構造を理解しやすい
* フロント・バックエンドの役割分離が明確
* SQL / CRUD の学習に直結
* Dockerで環境差異を排除
* 教材として段階的に拡張可能

---

## 補足

### なぜこの構成か

* Next.jsは便利だが抽象度が高い
* 今回は「仕組み理解」を優先
* SPA + API + DB の基本を押さえるため

---

## 今後の拡張

* 認証（セッション / JWT）
* 在庫管理
* 売上分析（グラフ）
* レシート印刷
* オフライン対応（PWA）

---

## まとめ

```txt
React（画面）
↓
PHP（API）
↓
MySQL（DB）
```
