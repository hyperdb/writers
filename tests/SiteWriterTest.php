<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * サイト執筆者API（SiteWriter）のテスト
 * 仕様は`/documents/api/site_writers.md`を参照
 */
class SiteWriterTest extends DbTestCase
{
    /** テストで登録する執筆者のユーザーID */
    private const USER_ID = '__phpunit_writer__';

    /** テスト用データのkey（setUpで登録） */
    private string $siteKey;
    private string $writerKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteKey = $this->createSite();
        $this->writerKey = $this->registerWriter(self::USER_ID);
    }

    /*
     * GET /sites/@site/writers
     */

    public function testGetListReturnsOnlySiteWriters(): void
    {
        $this->joinSite($this->siteKey, $this->writerKey);
        // 別のサイトにだけ紐付いた執筆者は含まない
        $this->joinSite($this->createSite('https://other.example.com'), $this->registerWriter('__phpunit_other__', '別の執筆者'));

        (new SiteWriter())->getList($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(['テスト執筆者'], array_column($body['data'], 'nickname'));
        // トークンなしにはuser_idとis_adminを返さない
        $this->assertArrayNotHasKey('user_id', $body['data'][0]);
        $this->assertArrayNotHasKey('is_admin', $body['data'][0]);
        $this->assertArrayNotHasKey('id', $body['data'][0]);
    }

    public function testGetListByAdminIncludesUserId(): void
    {
        $this->joinSite($this->siteKey, $this->writerKey);

        (new AuthenticatedSiteWriter())->getList($this->siteKey);

        $this->assertSame([self::USER_ID], array_column($this->response()[1]['data'], 'user_id'));
    }

    public function testGetListMissingSiteReturns404(): void
    {
        (new SiteWriter())->getList($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * POST /sites/@site/writers
     */

    public function testExecAddReturns201(): void
    {
        Flight::request()->data->setData(['writer' => $this->writerKey]);

        (new AuthenticatedSiteWriter())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame(self::USER_ID, $body['data']['user_id']);
        $this->assertTrue(SiteWriterModel::exists(HashTools::decodeSqid($this->siteKey), HashTools::decodeSqid($this->writerKey)));
    }

    /**
     * 執筆者の指定が不正な値
     *
     * @return array<string, array{0: array}>
     */
    public static function invalidWriterProvider(): array
    {
        return [
            '未指定' => [[]],
            '不正なkey' => [['writer' => '!!']],
        ];
    }

    #[DataProvider('invalidWriterProvider')]
    public function testExecAddInvalidWriterReturns400(array $data): void
    {
        Flight::request()->data->setData($data);

        (new AuthenticatedSiteWriter())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('writer', $body['data']);
    }

    public function testExecAddMissingWriterReturns400(): void
    {
        Flight::request()->data->setData(['writer' => $this->missingKey()]);

        (new AuthenticatedSiteWriter())->execAdd($this->siteKey);

        $this->assertSame(400, $this->response()[0]);
    }

    public function testExecAddMissingSiteReturns404(): void
    {
        Flight::request()->data->setData(['writer' => $this->writerKey]);

        (new AuthenticatedSiteWriter())->execAdd($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecAddDuplicateReturns409(): void
    {
        $this->joinSite($this->siteKey, $this->writerKey);
        Flight::request()->data->setData(['writer' => $this->writerKey]);

        (new AuthenticatedSiteWriter())->execAdd($this->siteKey);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * DELETE /sites/@site/writers/@writer
     */

    public function testExecDeleteRemovesLinkButKeepsContents(): void
    {
        $this->joinSite($this->siteKey, $this->writerKey);
        $siteId = HashTools::decodeSqid($this->siteKey);
        $writerId = HashTools::decodeSqid($this->writerKey);
        BaseModel::query("INSERT INTO contents (title, site_id, writer_id) VALUES ('テスト', ?, ?)", [$siteId, $writerId]);

        (new AuthenticatedSiteWriter())->execDelete($this->siteKey, $this->writerKey);

        $this->assertSame(200, $this->response()[0]);
        $this->assertFalse(SiteWriterModel::exists($siteId, $writerId));
        // 執筆者のコンテンツは残る
        $this->assertSame([1], BaseModel::fetchColumn("SELECT COUNT(*) FROM contents WHERE writer_id = ?", [$writerId]));
    }

    public function testExecDeleteNotLinkedReturns404(): void
    {
        (new AuthenticatedSiteWriter())->execDelete($this->siteKey, $this->writerKey);

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecDeleteMissingSiteReturns404(): void
    {
        (new AuthenticatedSiteWriter())->execDelete($this->missingKey(), $this->writerKey);

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * 認証・権限
     */

    /**
     * 書き込みの操作
     *
     * @return array<string, array{0: string}>
     */
    public static function writeOperationProvider(): array
    {
        return [
            'POST' => ['execAdd'],
            'DELETE' => ['execDelete'],
        ];
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteWithoutTokenReturns401(string $method): void
    {
        (new SiteWriter())->$method($this->siteKey, $this->writerKey);

        $this->assertSame(401, $this->response()[0]);
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteByNonAdminReturns403(string $method): void
    {
        // サイトの執筆者でも、紐付けの管理はできない
        $this->joinSite($this->siteKey, $this->writerKey);
        $otherKey = $this->registerWriter('__phpunit_other__', '別の執筆者');
        $this->loginAs(self::USER_ID);
        Flight::request()->data->setData(['writer' => $otherKey]);

        (new AuthenticatedSiteWriter())->$method($this->siteKey, $this->writerKey);

        $this->assertSame(403, $this->response()[0]);
        $siteId = HashTools::decodeSqid($this->siteKey);
        $this->assertTrue(SiteWriterModel::exists($siteId, HashTools::decodeSqid($this->writerKey)));
        $this->assertFalse(SiteWriterModel::exists($siteId, HashTools::decodeSqid($otherKey)));
    }
}
