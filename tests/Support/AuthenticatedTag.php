<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するTag
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedTag extends Tag
{
    use FakeAuthentication;
}
