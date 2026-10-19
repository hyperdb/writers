<?php

/**
 * テスト用: 認証を常に成功させるWriter
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedWriter extends Writer
{
    public function authenticate(): bool
    {
        return true;
    }
}
