<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * コンテンツイメージAPI（ContentImage）のテスト
 * 仕様は`/documents/api/content_images.md`を参照
 * 紐付けだけを扱うため、画像ファイルは作成しない
 */
class ContentImageTest extends DbTestCase
{
    /** テスト用の執筆者（サイトに紐付け済み）のユーザーID */
    private const WRITER_USER_ID = '__phpunit_writer__';

    /** テスト用サイトのkey（setUpで登録） */
    private string $siteKey;
    /** テスト用の執筆者のkey（setUpで登録） */
    private string $writerKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteKey = $this->createSite();
        $this->writerKey = $this->registerWriter(self::WRITER_USER_ID);
        $this->joinSite($this->siteKey, $this->writerKey);
    }

    /**
     * テスト用のコンテンツを登録してkeyを返す
     *
     * @param string $status
     * @return string
     */
    private function createContent(string $status = 'published'): string
    {
        $id = ContentModel::create([
            'title' => 'テスト記事',
            'body' => '本文',
            'status' => $status,
            'published_at' => '2026-01-01 00:00:00',
            'type_id' => 0,
            'category_id' => 0,
            'site_id' => HashTools::decodeSqid($this->siteKey),
            'writer_id' => HashTools::decodeSqid($this->writerKey),
        ], []);
        return HashTools::generateSqid($id);
    }

    /**
     * テスト用のイメージを登録してkeyを返す（ファイルは作成しない）
     *
     * @param ?string $siteKey 所属サイトのkey（省略時はテスト用サイト）
     * @return string
     */
    private function createImage(?string $siteKey = null): string
    {
        ImageModel::add(HashTools::decodeSqid($siteKey ?? $this->siteKey), 'photo.png', 'png');
        return HashTools::generateSqid(intval(BaseModel::getInsertId()));
    }

    /**
     * コンテンツにイメージを紐付ける
     *
     * @param string $contentKey
     * @param string $imageKey
     * @return void
     */
    private function link(string $contentKey, string $imageKey): void
    {
        ContentImageModel::add(HashTools::decodeSqid($contentKey), HashTools::decodeSqid($imageKey));
    }

    /*
     * GET /contents/@id/images
     */

    public function testGetListReturnsLinkedImages(): void
    {
        $contentKey = $this->createContent();
        $imageKey = $this->createImage();
        $this->createImage();
        $this->link($contentKey, $imageKey);

        (new ContentImage())->getList($contentKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame([$imageKey], array_column($body['data'], 'key'));
        $this->assertSame("/statics/images/{$imageKey}.png", $body['data'][0]['url']);
        $this->assertArrayNotHasKey('site_id', $body['data'][0]);
    }

    public function testGetListMissingContentReturns404(): void
    {
        (new ContentImage())->getList($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testGetListDraftWithoutTokenReturns404(): void
    {
        $contentKey = $this->createContent('draft');

        (new ContentImage())->getList($contentKey);

        $this->assertSame(404, $this->response()[0]);
    }

    public function testGetListDraftBySiteWriterReturns200(): void
    {
        $contentKey = $this->createContent('draft');
        $this->loginAs(self::WRITER_USER_ID);

        (new AuthenticatedContentImage())->getList($contentKey);

        $this->assertSame(200, $this->response()[0]);
    }

    /*
     * POST /contents/@id/images
     */

    public function testExecAddLinksImage(): void
    {
        $contentKey = $this->createContent();
        $imageKey = $this->createImage();
        Flight::request()->data->setData(['image' => $imageKey]);

        (new AuthenticatedContentImage())->execAdd($contentKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame($imageKey, $body['data']['key']);
        $this->assertSame(1, ImageModel::countRelated(HashTools::decodeSqid($imageKey)));
    }

    public function testExecAddMissingContentReturns404(): void
    {
        Flight::request()->data->setData(['image' => $this->createImage()]);

        (new AuthenticatedContentImage())->execAdd($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /**
     * 紐付けできないイメージ
     *
     * @return array<string, array{0: string}>
     */
    public static function invalidImageProvider(): array
    {
        return [
            '未指定' => ['none'],
            '存在しない' => ['missing'],
            '別のサイト' => ['other_site'],
        ];
    }

    #[DataProvider('invalidImageProvider')]
    public function testExecAddInvalidImageReturns400(string $case): void
    {
        $contentKey = $this->createContent();
        $imageKey = match ($case)
        {
            'none' => '',
            'missing' => $this->missingKey(),
            'other_site' => $this->createImage($this->createSite('https://other.example.com')),
        };
        Flight::request()->data->setData(['image' => $imageKey]);

        (new AuthenticatedContentImage())->execAdd($contentKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('image', $body['data']);
    }

    public function testExecAddAlreadyLinkedReturns409(): void
    {
        $contentKey = $this->createContent();
        $imageKey = $this->createImage();
        $this->link($contentKey, $imageKey);
        Flight::request()->data->setData(['image' => $imageKey]);

        (new AuthenticatedContentImage())->execAdd($contentKey);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * DELETE /contents/@id/images/@image
     */

    public function testExecDeleteUnlinksImage(): void
    {
        $contentKey = $this->createContent();
        $imageKey = $this->createImage();
        $this->link($contentKey, $imageKey);

        (new AuthenticatedContentImage())->execDelete($contentKey, $imageKey);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame(0, ImageModel::countRelated(HashTools::decodeSqid($imageKey)));
        // イメージ自体は残る
        $this->assertNotSame([], ImageModel::detail(HashTools::decodeSqid($imageKey)));
    }

    public function testExecDeleteNotLinkedReturns404(): void
    {
        $contentKey = $this->createContent();

        (new AuthenticatedContentImage())->execDelete($contentKey, $this->createImage());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecDeleteMissingContentReturns404(): void
    {
        (new AuthenticatedContentImage())->execDelete($this->missingKey(), $this->createImage());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * コンテンツの削除
     */

    public function testDeleteContentRemovesLinks(): void
    {
        $contentKey = $this->createContent();
        $imageKey = $this->createImage();
        $this->link($contentKey, $imageKey);

        (new AuthenticatedContent())->execDelete($contentKey);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame(0, ImageModel::countRelated(HashTools::decodeSqid($imageKey)));
        $this->assertNotSame([], ImageModel::detail(HashTools::decodeSqid($imageKey)));
    }

    /*
     * 認証
     */

    public function testWriteWithoutTokenReturns401(): void
    {
        $contentKey = $this->createContent();
        Flight::request()->data->setData(['image' => $this->createImage()]);

        (new ContentImage())->execAdd($contentKey);

        $this->assertSame(401, $this->response()[0]);
    }

    /*
     * 権限（コンテンツの本人）
     */

    public function testWriteByOwnerSucceeds(): void
    {
        $contentKey = $this->createContent();
        $imageKey = $this->createImage();
        $this->loginAs(self::WRITER_USER_ID);
        Flight::request()->data->setData(['image' => $imageKey]);

        (new AuthenticatedContentImage())->execAdd($contentKey);
        $this->assertSame(201, $this->response()[0]);

        Flight::response()->clear();
        (new AuthenticatedContentImage())->execDelete($contentKey, $imageKey);
        $this->assertSame(200, $this->response()[0]);
    }

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
    public function testWriteByOtherSiteWriterReturns403(string $method): void
    {
        // 同じサイトの執筆者でも、本人のコンテンツでなければ403
        $contentKey = $this->createContent();
        $imageKey = $this->createImage();
        $this->link($contentKey, $imageKey);
        $this->joinSite($this->siteKey, $this->registerWriter('__phpunit_other__'));
        $this->loginAs('__phpunit_other__');
        Flight::request()->data->setData(['image' => $imageKey]);

        (new AuthenticatedContentImage())->$method(...($method === 'execAdd' ? [$contentKey] : [$contentKey, $imageKey]));

        $this->assertSame(403, $this->response()[0]);
        $this->assertSame(1, ImageModel::countRelated(HashTools::decodeSqid($imageKey)));
    }
}
