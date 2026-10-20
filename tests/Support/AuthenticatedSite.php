<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するSite
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedSite extends Site
{
    use FakeAuthentication;
}
