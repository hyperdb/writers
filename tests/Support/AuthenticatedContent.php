<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するContent
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedContent extends Content
{
    use FakeAuthentication;
}
