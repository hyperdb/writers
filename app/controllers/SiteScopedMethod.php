<?php

/**
 * インターフェース: サイトに属するリソースのコントローラのベースメソッドを定義
 * 一覧取得と追加はサイト配下のURL（/sites/@site/...）で受け、サイトのkeyを引数に取る
 */
interface SiteScopedMethod
{
    public static function registerRoutes(): void;

    public function getList(string $site_key): void;
    public function execAdd(string $site_key): void;
    public function getDetail(string $key): void;
    public function execUpdate(string $key): void;
    public function execDelete(string $key): void;
}
