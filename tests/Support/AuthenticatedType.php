<?php

/**
 * テスト用: 認証を常に成功させるType
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedType extends Type
{
    public function authenticate(): bool
    {
        return true;
    }
}
