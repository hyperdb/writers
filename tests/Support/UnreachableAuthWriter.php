<?php

/**
 * テスト用: commonsに問い合わせできない状態を再現するWriter
 */
class UnreachableAuthWriter extends Writer
{
    protected function validToken(string $shortenToken): ?bool
    {
        return null;
    }
}
