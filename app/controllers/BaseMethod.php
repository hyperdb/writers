<?php

/**
 * インターフェース: コントローラのベースメソッドを定義
 */
interface BaseMethod
{
    public static function registerRoutes(): void;

    public function getList(): void;
    public function execAdd(): void;
    public function getDetail(string $key): void;
    public function execUpdate(string $key): void;
    public function execDelete(string $key): void;
}
