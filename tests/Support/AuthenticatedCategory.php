<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するCategory
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedCategory extends Category
{
    use FakeAuthentication;
}
