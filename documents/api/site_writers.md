# サイト執筆者API（site_writers）

共通ルールは`/documents/api-conventions.md`、項目の意味は`/documents/Entities.md`を参照してください。

## エンドポイント一覧

| メソッド | パス | 内容 | 権限 |
| --- | --- | --- | --- |
| `GET` | `/sites/@site/writers` | サイトに紐付いた執筆者の一覧取得 | 不要（任意） |
| `POST` | `/sites/@site/writers` | サイトに執筆者を紐付ける | 管理者 |
| `DELETE` | `/sites/@site/writers/@writer` | サイトと執筆者の紐付けを外す | 管理者 |

- `@site`はサイトの`key`、`@writer`は執筆者の`key`。存在しないサイトを指定した場合は`404`
- 紐付けには更新がないため`PUT`はない
- 権限が必要なエンドポイントは、共通ルールのとおり`401` / `403` / `502`を返すことがある

## レスポンスの項目

執筆者API（`/documents/api/writers.md`）の執筆者と同じ項目を返す（`user_id`と`is_admin`は管理者のみ）。

## GET /sites/@site/writers

- クエリパラメーター: `page`, `rows`（共通ルールのページング）
- 並び順: 紐付けた日時の古い順
- レスポンス
  - `200`: 執筆者の配列（0件でも`200`）
  - `404`: サイトが存在しない

## POST /sites/@site/writers

- ボディ
  - `writer`（必須）: 執筆者の`key`
- レスポンス
  - `201`: 紐付けた執筆者
  - `400`: `writer`が未指定、または存在しない執筆者
  - `404`: サイトが存在しない
  - `409`: 紐付け済み

## DELETE /sites/@site/writers/@writer

- 紐付けを外しても、その執筆者のコンテンツは削除しない
- レスポンス
  - `200`: 削除成功（`data`は空配列）
  - `404`: サイトが存在しない、または紐付けがない
