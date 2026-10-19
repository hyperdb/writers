<?php

/**
 * テスト用: トークンを添付し、認証を常に成功させるContent
 * commonsに問い合わせずに、認証後の処理（下書きを含む取得など）をテストするために使う
 */
class AuthenticatedContent extends Content
{
    public function __construct()
    {
        parent::__construct();
        // トークン付きのリクエストとして扱う（GETで下書きも返す判定に使われる）
        $this->bearer_token = 'phpunit';
    }

    public function authenticate(): bool
    {
        return true;
    }
}
