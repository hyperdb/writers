<?php

/**
 * テスト用: commonsのトークン判定の結果（トークンの持ち主のユーザーID）
 * 空文字は無効なトークンとして扱われる（401）
 */
final class FakeAuth
{
    public static string $userId = '';
}

/**
 * テスト用: commonsに問い合わせずに、`FakeAuth::$userId`のユーザーとして認証するトレイト
 * 執筆者の特定と権限の確認は本来の処理（Base）で行う
 */
trait FakeAuthentication
{
    public function __construct()
    {
        parent::__construct();
        // トークン付きのリクエストとして扱う（GETでトークンが任意の処理に使われる）
        $this->bearer_token = 'phpunit';
    }

    protected function validToken(string $shortenToken): ?string
    {
        return FakeAuth::$userId;
    }
}
