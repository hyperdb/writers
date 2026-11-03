# イメージAPI（images）

共通ルールは`/documents/api-conventions.md`、項目の意味は`/documents/Entities.md`を参照してください。

## エンドポイント一覧

| メソッド | パス | 内容 | 権限 |
| --- | --- | --- | --- |
| `GET` | `/sites/@site/images` | サイトのイメージ一覧取得 | 不要 |
| `GET` | `/sites/@site/images/count` | サイトのイメージ総件数取得 | 不要 |
| `POST` | `/sites/@site/images` | サイトにイメージを追加（アップロード） | サイトの執筆者 |
| `GET` | `/images/@id` | 詳細取得 | 不要 |
| `PUT` | `/images/@id` | 差し替え（ファイルを入れ替える） | サイトの執筆者 |
| `DELETE` | `/images/@id` | 削除 | サイトの執筆者 |

- `@site`はサイトの`key`。存在しないサイトを指定した場合は`404`
- 権限が必要なエンドポイントは、共通ルールのとおり`401` / `403` / `502`を返すことがある（管理者はすべて操作できる）

## 画像ファイル

- 保存先は`/statics/images/<key>.<extension>`。APIを通さずにそのまま配信する
- アップロードは`multipart/form-data`で、項目名は`image`
- 受け付ける形式は JPEG / PNG / GIF / WebP。拡張子ではなくファイルの中身で判定する
  - SVGはスクリプトを埋め込めるため受け付けない
- サイズは5MBまで
  - サーバーの`upload_max_filesize` / `post_max_size`がこれより小さい場合は、そちらが上限になる
- `PUT`では、ファイル名に英数字・`.`・`_`・`-`以外の文字（日本語など）が含まれると、`source_name`が`upload_...`という名前になる（FlightPHPの仕様）

## レスポンスの項目

| 項目 | 内容 |
| --- | --- |
| `key` | イメージのkey |
| `source_name` | アップロード時のファイル名 |
| `extension` | 拡張子（`jpg` / `png` / `gif` / `webp`） |
| `url` | 画像のパス（`/statics/images/<key>.<extension>`） |
| `site_key` | 所属するサイトのkey |
| `created_at` / `updated_at` | 作成日時・更新日時 |

## 入力チェック

| 項目 | ルール |
| --- | --- |
| `image` | 必須、5MB以内、JPEG / PNG / GIF / WebPのいずれか |

- チェックに失敗した場合は`400`を返し、`data`に項目ごとのエラーメッセージを入れる

## GET /sites/@site/images

- クエリパラメーター: `page`, `rows`（共通ルールのページング）
- レスポンス
  - `200`: イメージの配列（0件でも`200`）
  - `404`: サイトが存在しない

## GET /sites/@site/images/count

- `GET /sites/@site/images`で取得できるイメージの総件数を返す（一覧のページ数の計算用）
  - `page`, `rows`は無視する
- レスポンス
  - `200`: `data`に`count`（総件数、整数）を入れる（0件でも`200`）
  - `404`: サイトが存在しない

## POST /sites/@site/images

- ボディ（`multipart/form-data`）
  - `image`（必須）: 画像ファイル
- レスポンス
  - `201`: 登録したイメージ
  - `400`: 入力チェックエラー
  - `404`: サイトが存在しない

## GET /images/@id

- レスポンス
  - `200`: イメージ
  - `404`: 該当なし

## PUT /images/@id

- `key`は変えずに、古いファイルを削除して新しいファイルに差し替える
  - `source_name` / `extension`も新しいファイルのものに更新する（形式が変わると`url`も変わる）
- ボディ（`multipart/form-data`）
  - `image`（必須）: 画像ファイル
- レスポンス
  - `200`: 差し替えたイメージ
  - `400`: 入力チェックエラー
  - `404`: 該当なし

## DELETE /images/@id

- コンテンツから参照されている（`content_images`）場合は削除しない
- 削除するとファイルも削除する
- レスポンス
  - `200`: 削除成功（`data`は空配列）
  - `404`: 該当なし
  - `409`: コンテンツから参照されている
