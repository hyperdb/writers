<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\TestCase;

/**
 * DBを使うテストの基底クラス
 * 各テストはトランザクション内で実行し、終了時にロールバックする
 * 既定では管理者（`ADMIN_USER_ID`）としてログインした状態で始まる
 */
abstract class DbTestCase extends TestCase
{
    /** 既定でログインする管理者のユーザーID */
    protected const ADMIN_USER_ID = '__phpunit_admin__';

    /** 既定でログインする管理者のkey */
    protected string $adminKey;

    protected function setUp(): void
    {
        Flight::db()->beginTransaction();
        Flight::response()->clear();
        Flight::request()->data->setData([]);
        Flight::request()->query->setData([]);
        $this->adminKey = $this->registerWriter(self::ADMIN_USER_ID, 'テスト管理者', true);
        $this->loginAs(self::ADMIN_USER_ID);
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
     * 指定したユーザーとして認証された状態にする（`Authenticated*`のコントローラーで有効）
     *
     * @param string $userId commonsのユーザーID（空文字は無効なトークン）
     * @return void
     */
    protected function loginAs(string $userId): void
    {
        FakeAuth::$userId = $userId;
    }

    /**
     * テスト用の執筆者を登録してkeyを返す
     *
     * @param string $userId
     * @param string $nickname
     * @param bool $isAdmin
     * @return string
     */
    protected function registerWriter(string $userId, string $nickname = 'テスト執筆者', bool $isAdmin = false): string
    {
        WriterModel::add($userId, $nickname);
        $id = intval(BaseModel::getInsertId());
        if ($isAdmin)
        {
            BaseModel::query("UPDATE writers SET is_admin = 1 WHERE id = ?", [$id]);
        }
        return HashTools::generateSqid($id);
    }

    /**
     * 執筆者をサイトに紐付ける
     *
     * @param string $siteKey
     * @param string $writerKey
     * @return void
     */
    protected function joinSite(string $siteKey, string $writerKey): void
    {
        SiteWriterModel::add(HashTools::decodeSqid($siteKey), HashTools::decodeSqid($writerKey));
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
