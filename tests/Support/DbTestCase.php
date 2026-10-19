<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\TestCase;

/**
 * DBを使うテストの基底クラス
 * 各テストはトランザクション内で実行し、終了時にロールバックする
 */
abstract class DbTestCase extends TestCase
{
    protected function setUp(): void
    {
        Flight::db()->beginTransaction();
        Flight::response()->clear();
        Flight::request()->data->setData([]);
        Flight::request()->query->setData([]);
    }

    protected function tearDown(): void
    {
        Flight::db()->rollBack();
    }

    /**
     * レスポンスのステータスとボディを取得する
     *
     * @return array{0: int, 1: array}
     */
    protected function response(): array
    {
        return [
            Flight::response()->status(),
            json_decode(Flight::response()->getBody(), true),
        ];
    }

    /**
     * 存在しないデータのkeyを返す
     *
     * @return string
     */
    protected function missingKey(): string
    {
        return HashTools::generateSqid(4294967295);
    }

    /**
     * テスト用のサイトを登録してkeyを返す
     *
     * @param string $url
     * @param string $name
     * @return string
     */
    protected function createSite(string $url = 'https://phpunit.example.com', string $name = 'テストサイト'): string
    {
        SiteModel::add($url, $name);
        return HashTools::generateSqid(intval(BaseModel::getInsertId()));
    }
}
