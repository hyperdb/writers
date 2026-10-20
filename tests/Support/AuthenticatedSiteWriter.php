<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するSiteWriter
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedSiteWriter extends SiteWriter
{
    use FakeAuthentication;
}
