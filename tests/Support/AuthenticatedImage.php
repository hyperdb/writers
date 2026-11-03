<?php

/**
 * テスト用: `FakeAuth::$userId`のユーザーとして認証するImage
 * commonsに問い合わせずに、認証後の処理をテストするために使う
 */
class AuthenticatedImage extends Image
{
    use FakeAuthentication;
}
