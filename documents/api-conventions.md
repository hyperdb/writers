# API共通ルール

全エンドポイントに共通するルールを記載します。個別のエンドポイント仕様は`/documents/api/<リソース名>.md`を参照してください。

## ベースURL

- `https://writers.hyperdb.cloud/api/v1`

## リクエスト

- `POST` / `PUT`のボディはJSON（`Content-Type: application/json`）で送信する
- 一覧取得のページング（クエリパラメーター）
  - `page`: ページ番号（0始まり、既定値`0`、負の値は`0`として扱う）
  - `rows`: 1ページあたりの件数（既定値`10`、0以下は既定値として扱う）

## レスポンス形式

すべてのレスポンスは以下のJSON形式で返却する（`404 Not Found`のルート未定義時も同様）。

```json
{
    "api_version": "1.0.0",
    "api_route": "/api/v1/writers",
    "send_at": "2026-10-19 12:34:56",
    "message": "一覧が取得できました",
    "data": []
}
```

| 項目 | 内容 |
| --- | --- |
| `api_version` | APIのバージョン（`index.php`の`API_VERSION`） |
| `api_route` | リクエストされたURL |
| `send_at` | レスポンス送信日時（`Y-m-d H:i:s`） |
| `message` | 処理結果のメッセージ（日本語） |
| `data` | 処理結果のデータ（ない場合は空配列） |

## ID（key）の扱い

- 連番となる数値のIDはレスポンスに含めない
- IDは`HashTools::generateSqid`で文字列化し、項目`key`として返却する
- パスパラメーター`@id`には`key`の値を指定する
- 他のリソースを参照する列（`site_id`など）も数値のまま返さず、`<名前>_key`（`site_key`など）に文字列化して返す
  - 参照先がない値（`0`）は`null`とする

## サイトに属するリソース

- カテゴリー・タグ・コンテンツはサイトごとに管理し、一覧取得と追加はサイト配下のURLで行う
  - 例: `GET /sites/@site/categories`、`POST /sites/@site/categories`（`@site`はサイトの`key`）
  - 指定したサイトが存在しない場合は`404`
- 詳細取得・更新・削除はリソース自身のURLで行う（例: `GET /categories/@id`）
- 所属するサイトは追加後に変更できない

## 認証

- 執筆者の認証は`commons.hyperdb.cloud`で行い、このサーバーは認証情報を持たない
- クライアントは`commons.hyperdb.cloud`で発行されたトークンを`Authorization: Bearer <token>`で添付する
- このサーバーはトークンを以下のAPIで判定し、有効な場合のみ後続処理を行う
  - `POST https://commons.hyperdb.cloud/api/v1/tokens/valid`
  - ボディ: `{ "token": "..." }`
- 読み取り（`GET`）は認証不要、それ以外（`POST` / `PUT` / `DELETE`）は認証必須とする
  - トークンはコンテンツを利用するアプリ側で認証して取得し、Bearerトークンとして渡す
- 判定結果に応じたレスポンス
  - トークンが未添付・形式不正・無効: `401 Unauthorized`
  - `commons.hyperdb.cloud`に問い合わせできなかった: `502 Bad Gateway`

## エラー

| ステータス | 用途 |
| --- | --- |
| `400 Bad Request` | パラメーターが不足・不正 |
| `401 Unauthorized` | トークンが無効・未添付 |
| `404 Not Found` | 指定したリソース・ルートが存在しない |
| `409 Conflict` | 一意であるべき値が登録済み（重複） |
| `500 Internal Server Error` | サーバー側のエラー |
| `502 Bad Gateway` | `commons.hyperdb.cloud`への問い合わせ失敗 |

- エラー時も上記レスポンス形式で返却し、`data`は空配列とする
  - 例外: 入力チェックエラー（`400`）の場合は、`data`に項目名をキーとしたエラーメッセージの配列を入れる

```json
"data": {
    "url": ["URLは形式が正しくありません"],
    "name": ["サイト名は必須項目です"]
}
```

## CORS

- `Origin`ヘッダー付きのリクエストにはCORSヘッダーを付与する（`app/utils/CorsUtil.php`）
- 許可するオリジンは`.env`の`CORS_ALLOWED_ORIGINS`に`,`区切りで記載する
  - 例: `CORS_ALLOWED_ORIGINS="http://localhost:5173,https://blog.example.com"`
  - 一覧にないオリジンには`Access-Control-Allow-Origin`を付与しない
