<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するContentImage
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedContentImage extends ContentImage
{
    use FakeAuthentication;
}
