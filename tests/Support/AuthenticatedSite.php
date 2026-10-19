<?php

/**
 * テスト用: 認証を常に成功させるSite
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedSite extends Site
{
    public function authenticate(): bool
    {
        return true;
    }
}
