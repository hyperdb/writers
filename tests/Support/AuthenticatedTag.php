<?php

/**
 * テスト用: 認証を常に成功させるTag
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedTag extends Tag
{
    public function authenticate(): bool
    {
        return true;
    }
}
