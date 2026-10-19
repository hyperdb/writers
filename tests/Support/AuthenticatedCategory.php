<?php

/**
 * テスト用: 認証を常に成功させるCategory
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedCategory extends Category
{
    public function authenticate(): bool
    {
        return true;
    }
}
