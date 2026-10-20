<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * タグAPI（Tag）のテスト
 * 仕様は`/documents/api/tags.md`を参照
 */
class TagTest extends DbTestCase
{
    /** テスト用サイトのkey（setUpで登録） */
    private string $siteKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteKey = $this->createSite();
    }

    /**
     * テスト用のタグを登録してkeyを返す
     *
     * @param string $tag
     * @param ?string $siteKey 所属サイトのkey（省略時はテスト用サイト）
     * @return string
     */
    private function createTag(string $tag = 'テスト', ?string $siteKey = null): string
    {
        TagModel::add(HashTools::decodeSqid($siteKey ?? $this->siteKey), $tag);
        return HashTools::generateSqid(intval(BaseModel::getInsertId()));
    }

    /*
     * GET /sites/@site/tags
     */

    public function testGetListReturnsOnlySiteTags(): void
    {
        $this->createTag('タグ1');
        $this->createTag('タグ2');
        $this->createTag('別サイト', $this->createSite('https://other.example.com'));

        (new Tag())->getList($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(['タグ1', 'タグ2'], array_column($body['data'], 'tag'));
        foreach ($body['data'] as $row)
        {
            $this->assertArrayNotHasKey('id', $row);
            $this->assertArrayNotHasKey('site_id', $row);
            $this->assertArrayHasKey('site_key', $row);
        }
    }

    public function testGetListMissingSiteReturns404(): void
    {
        (new Tag())->getList($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * GET /tags/@id
     */

    public function testGetDetailReturnsTag(): void
    {
        $key = $this->createTag();

        (new Tag())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame('テスト', $body['data']['tag']);
        $this->assertSame($key, $body['data']['key']);
    }

    public function testGetDetailMissingKeyReturns404(): void
    {
        (new Tag())->getDetail($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * POST /sites/@site/tags
     */

    public function testExecAddReturns201(): void
    {
        Flight::request()->data->setData(['tag' => 'テスト']);

        (new AuthenticatedTag())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame('テスト', $body['data']['tag']);
        $this->assertSame(HashTools::decodeSqid($this->siteKey), HashTools::decodeSqid($body['data']['site_key']));
    }

    public function testExecAddMissingSiteReturns404(): void
    {
        Flight::request()->data->setData(['tag' => 'テスト']);

        (new AuthenticatedTag())->execAdd($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecAddWithHashReturns400(): void
    {
        Flight::request()->data->setData(['tag' => '#テスト']);

        (new AuthenticatedTag())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('tag', $body['data']);
    }

    public function testExecAddDuplicateNameReturns409(): void
    {
        $this->createTag();
        Flight::request()->data->setData(['tag' => 'テスト']);

        (new AuthenticatedTag())->execAdd($this->siteKey);

        $this->assertSame(409, $this->response()[0]);
    }

    public function testExecAddSameNameInOtherSiteReturns201(): void
    {
        $this->createTag('テスト', $this->createSite('https://other.example.com'));
        Flight::request()->data->setData(['tag' => 'テスト']);

        (new AuthenticatedTag())->execAdd($this->siteKey);

        $this->assertSame(201, $this->response()[0]);
    }

    /*
     * PUT /tags/@id
     */

    public function testExecUpdateChangesName(): void
    {
        $key = $this->createTag();
        Flight::request()->data->setData(['tag' => '更新後']);

        (new AuthenticatedTag())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame('更新後', TagModel::detail(HashTools::decodeSqid($key))['tag']);
    }

    public function testExecUpdateMissingKeyReturns404(): void
    {
        Flight::request()->data->setData(['tag' => 'テスト']);

        (new AuthenticatedTag())->execUpdate($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecUpdateDuplicateNameReturns409(): void
    {
        $this->createTag();
        $key = $this->createTag('別名');
        Flight::request()->data->setData(['tag' => 'テスト']);

        (new AuthenticatedTag())->execUpdate($key);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * DELETE /tags/@id
     */

    public function testExecDeleteRemovesTag(): void
    {
        $key = $this->createTag();

        (new AuthenticatedTag())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame([], TagModel::detail(HashTools::decodeSqid($key)));
    }

    public function testExecDeleteMissingKeyReturns404(): void
    {
        (new AuthenticatedTag())->execDelete($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecDeleteAttachedToContentReturns409(): void
    {
        $key = $this->createTag();
        BaseModel::query(
            "INSERT INTO content_tags (content_id, tag_id) VALUES (1, ?)",
            [HashTools::decodeSqid($key)]
        );

        (new AuthenticatedTag())->execDelete($key);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * 認証
     */

    public function testWriteWithoutTokenReturns401(): void
    {
        Flight::request()->data->setData(['tag' => 'テスト']);

        (new Tag())->execAdd($this->siteKey);

        $this->assertSame(401, $this->response()[0]);
    }

    /*
     * 権限（サイトの執筆者）
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
            'PUT' => ['execUpdate'],
            'DELETE' => ['execDelete'],
        ];
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteBySiteWriterSucceeds(string $method): void
    {
        $key = $this->createTag('変更前');
        $this->joinSite($this->siteKey, $this->registerWriter('__phpunit_writer__'));
        $this->loginAs('__phpunit_writer__');
        Flight::request()->data->setData(['tag' => '変更後']);

        (new AuthenticatedTag())->$method(...($method === 'execAdd' ? [$this->siteKey] : [$key]));

        $expected = ['execAdd' => 201, 'execUpdate' => 200, 'execDelete' => 200];
        $this->assertSame($expected[$method], $this->response()[0]);
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteByOtherSiteWriterReturns403(string $method): void
    {
        $key = $this->createTag('変更前');
        // 別のサイトの執筆者としてログイン
        $this->joinSite($this->createSite('https://other.example.com'), $this->registerWriter('__phpunit_writer__'));
        $this->loginAs('__phpunit_writer__');
        Flight::request()->data->setData(['tag' => '変更後']);

        (new AuthenticatedTag())->$method(...($method === 'execAdd' ? [$this->siteKey] : [$key]));

        $this->assertSame(403, $this->response()[0]);
        // 変更・削除されていない
        $this->assertSame('変更前', TagModel::detail(HashTools::decodeSqid($key))['tag']);
    }
}
