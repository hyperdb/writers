<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\TestCase;

/**
 * HashTools（Sqids）のテスト
 */
class HashToolsTest extends TestCase
{
    /**
     * 初期化済みのSqidsインスタンスを破棄する（`MY_ALPHABET`を読み直させるため）
     *
     * @return void
     */
    private function resetSqids(): void
    {
        (new ReflectionProperty(HashTools::class, 'sqids'))->setValue(null, null);
    }

    public function testSqidRoundTrip(): void
    {
        $this->assertSame(123, HashTools::decodeSqid(HashTools::generateSqid(123)));
    }

    public function testInvalidSqidDecodesToZero(): void
    {
        $this->assertSame(0, HashTools::decodeSqid('!!'));
    }

    public function testMissingAlphabetThrowsException(): void
    {
        $alphabet = $_ENV['MY_ALPHABET'];
        unset($_ENV['MY_ALPHABET']);
        $this->resetSqids();
        try
        {
            $this->expectException(RuntimeException::class);
            HashTools::generateSqid(1);
        }
        finally
        {
            // 他のテストに影響しないよう元に戻す
            $_ENV['MY_ALPHABET'] = $alphabet;
            $this->resetSqids();
        }
    }
}
