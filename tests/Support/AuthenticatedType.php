<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するType
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedType extends Type
{
    use FakeAuthentication;
}
