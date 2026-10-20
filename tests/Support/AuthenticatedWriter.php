<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するWriter
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedWriter extends Writer
{
    use FakeAuthentication;
}
