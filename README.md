# writers api

このプロジェクトは個人サイトで共通に利用するコンテンツ管理のREST APIサーバーを構築することを目的としています。

## 動作環境

- PHP8.5
- MySQL5.7（本番運用のお名前サーバがこのバージョンのため）

## 運用環境

- お名前サーバー RSプラン
  - `https://writers.hyperdb.cloud`

## 技術スタック

- PHP8.5
- FlightPHP3
- MySQL5.7

## セキュリティ対策

- 連番となる数値のIDは使用しない
  - ID番号を利用する場合は`HashTools::generateSqid`を利用して文字列化したものを使用する。したがってIDを含む行セットを返す場合には項目`key`を追加して文字列化したIDも合わせて返却する。
- 利用時には`commons.hyperdb.cloud`で発行されるトークンをアプリ側で保持してBearerトークンとして添付する。
  - 処理前に`commons.hyperdb.cloud`に対して添付されたトークンの有効性チェックを行い、有効になった場合のみ後続処理を行う

## テスト

- 各APIのテストはPHPUnitを利用して行う。
