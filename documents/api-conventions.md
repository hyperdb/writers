# API共通ルール

全エンドポイントに共通するルールを記載します。個別のエンドポイント仕様は`/documents/api/<リソース名>.md`を参照してください。

## ベースURL

- `https://writers.hyperdb.cloud/api/v1`

## リクエスト

- `POST` / `PUT`のボディはJSON（`Content-Type: application/json`）で送信する
  - 例外: イメージのアップロードは`multipart/form-data`で送信する（`/documents/api/images.md`）
- 一覧取得のページング（クエリパラメーター）
  - `page`: ページ番号（0始まり、既定値`0`、負の値は`0`として扱う）
  - `rows`: 1ページあたりの件数（既定値`10`、上限`10`。0以下は既定値、上限を超える値は上限として扱う）

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

## 日時

- 形式は`Y-m-d H:i:s`（例: `2026-10-21 09:00:00`）。ISO 8601形式（`T`区切りやタイムゾーン付き）は受け付けない
- タイムゾーンは日本時間（JST）
  - DBの日時（`created_at` / `updated_at` / `published_at`）と公開の判定はMySQLの日時で、本番でJSTであることを確認済み
  - `send_at`はPHPの`date()`で作成しており、PHPの設定（php.ini）に依存する（本番は未確認）

## サイトに属するリソース

- カテゴリー・タグ・コンテンツ・イメージはサイトごとに管理し、一覧取得と追加はサイト配下のURLで行う
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
- 判定APIのレスポンス（`data`の項目）
  - `valid`: `"OK"`（有効）または`"NG"`（無効）
  - `user_id`: トークンの持ち主のユーザーID（`valid`が`"OK"`のときのみ）
  - `valid`が`"OK"`で`user_id`がない場合は、想定外の応答として問い合わせ失敗（`502`）と同じに扱う
- 判定結果に応じたレスポンス
  - トークンが未添付・形式不正・無効: `401 Unauthorized`
  - `commons.hyperdb.cloud`に問い合わせできなかった: `502 Bad Gateway`

## 権限

トークンの`user_id`で執筆者（`writers`）を特定し、操作ごとに次の権限を確認する。

| 権限 | 条件 |
| --- | --- |
| 管理者 | 執筆者として登録済みで、`is_admin`が`1` |
| サイトの執筆者 | 執筆者として登録済みで、対象のサイトに紐付いている（`site_writers`） |
| 本人 | 対象のデータの執筆者が自分 |

- 管理者は、すべての操作ができる
- 執筆者として登録されていないユーザーは、トークンが有効でも書き込みできない
- 権限がない場合は`403 Forbidden`を返す
- 各エンドポイントで必要な権限は、`/documents/api/<リソース名>.md`のエンドポイント一覧に記載する
- 権限は認証（`401` / `502`）の後、入力チェック（`400`）の前に確認する
  - 対象によって決まる権限（サイトの執筆者・本人）は、対象の存在確認（`404`）の後に確認する

## エラー

| ステータス | 用途 |
| --- | --- |
| `400 Bad Request` | パラメーターが不足・不正 |
| `401 Unauthorized` | トークンが無効・未添付 |
| `403 Forbidden` | 操作する権限がない |
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
