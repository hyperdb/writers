<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * コンテンツAPI（Content）のテスト
 * 仕様は`/documents/api/contents.md`を参照
 */
class ContentTest extends DbTestCase
{
    /** テストで執筆者としてログインするユーザーID */
    private const WRITER_USER_ID = '__phpunit_writer__';

    /** テスト用データのID・key（setUpで登録） */
    private int $siteId;
    private string $siteKey;
    private string $writerKey;
    private string $typeKey;
    private string $categoryKey;
    private string $tagKey1;
    private string $tagKey2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteKey = $this->createSite();
        $this->siteId = HashTools::decodeSqid($this->siteKey);

        // 既定ではサイトの執筆者としてログインする（管理者の操作は個別のテストで切り替える）
        $this->writerKey = $this->registerWriter(self::WRITER_USER_ID);
        $this->joinSite($this->siteKey, $this->writerKey);
        $this->loginAs(self::WRITER_USER_ID);
        TypeModel::add('__phpunit_type__');
        $this->typeKey = $this->lastKey();
        CategoryModel::add($this->siteId, 0, 'テストカテゴリー');
        $this->categoryKey = $this->lastKey();
        TagModel::add($this->siteId, 'タグ1');
        $this->tagKey1 = $this->lastKey();
        TagModel::add($this->siteId, 'タグ2');
        $this->tagKey2 = $this->lastKey();
    }

    /**
     * 直前に追加したデータのkeyを返す
     *
     * @return string
     */
    private function lastKey(): string
    {
        return HashTools::generateSqid(intval(BaseModel::getInsertId()));
    }

    /**
     * テスト用のコンテンツを登録してkeyを返す
     *
     * @param array $values 既定値を上書きする値
     * @param array<int,string> $tagKeys
     * @return string
     */
    private function createContent(array $values = [], array $tagKeys = []): string
    {
        $id = ContentModel::create(
            $values + [
                'title' => 'テスト記事',
                'body' => '本文',
                'status' => 'published',
                'published_at' => '2026-01-01 00:00:00',
                'type_id' => 0,
                'category_id' => 0,
                'site_id' => $this->siteId,
                'writer_id' => HashTools::decodeSqid($this->writerKey),
            ],
            array_map(fn(string $key): int => HashTools::decodeSqid($key), $tagKeys)
        );
        return HashTools::generateSqid($id);
    }

    /**
     * 有効な入力値（ボディ）を返す
     *
     * @param array $values 上書きする値
     * @return array
     */
    private function validInput(array $values = []): array
    {
        return $values + [
            'title' => 'テスト記事',
            'body' => '# 見出し',
            'status' => 'published',
        ];
    }

    /*
     * GET /sites/@site/contents
     */

    public function testGetListWithoutTokenReturnsOnlyPublished(): void
    {
        $this->createContent(['title' => '公開中']);
        $this->createContent(['title' => '下書き', 'status' => 'draft', 'published_at' => null]);
        $this->createContent(['title' => '公開予約', 'published_at' => '2099-01-01 00:00:00']);

        (new Content())->getList($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(['公開中'], array_column($body['data'], 'title'));
    }

    public function testGetListWithTokenIncludesUnpublishedInOrder(): void
    {
        $this->createContent(['title' => '古い記事', 'published_at' => '2026-01-01 00:00:00']);
        $this->createContent(['title' => '下書き', 'status' => 'draft', 'published_at' => null]);
        $this->createContent(['title' => '新しい記事', 'published_at' => '2026-06-01 00:00:00']);

        (new AuthenticatedContent())->getList($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        // 公開日時の新しい順、公開日時のない下書きは最後
        $this->assertSame(['新しい記事', '古い記事', '下書き'], array_column($body['data'], 'title'));
    }

    public function testGetListReturnsKeysAndTags(): void
    {
        $this->createContent(['type_id' => HashTools::decodeSqid($this->typeKey)], [$this->tagKey1]);

        (new Content())->getList($this->siteKey);
        $row = $this->response()[1]['data'][0];

        foreach (['id', 'type_id', 'category_id', 'site_id', 'writer_id'] as $column)
        {
            $this->assertArrayNotHasKey($column, $row);
        }
        $this->assertSame(HashTools::decodeSqid($this->typeKey), HashTools::decodeSqid($row['type_key']));
        $this->assertNull($row['category_key']);
        $this->assertSame(['タグ1'], array_column($row['tags'], 'tag'));
    }

    /**
     * 絞り込みのテストケース（クエリパラメーター名、該当するコンテンツの登録値）
     *
     * @return array<string, array{0: string}>
     */
    public static function filterProvider(): array
    {
        return [
            'タイプ' => ['type'],
            'カテゴリー' => ['category'],
            'タグ' => ['tag'],
            '執筆者' => ['writer'],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testGetListFiltersByKey(string $param): void
    {
        // 対象: タイプ・カテゴリー・タグあり、対象外: すべて未定・別の執筆者
        $this->createContent(
            [
                'title' => '対象',
                'type_id' => HashTools::decodeSqid($this->typeKey),
                'category_id' => HashTools::decodeSqid($this->categoryKey),
            ],
            [$this->tagKey1]
        );
        WriterModel::add('__phpunit_other_writer__', '別の執筆者');
        $this->createContent(['title' => '対象外', 'writer_id' => intval(BaseModel::getInsertId())]);
        $keys = [
            'type' => $this->typeKey,
            'category' => $this->categoryKey,
            'tag' => $this->tagKey1,
            'writer' => $this->writerKey,
        ];
        Flight::request()->query->setData([$param => $keys[$param]]);

        (new Content())->getList($this->siteKey);

        $this->assertSame(['対象'], array_column($this->response()[1]['data'], 'title'));
    }

    public function testGetListFilterWithInvalidKeyReturnsEmpty(): void
    {
        // タイプ未定のコンテンツがあっても、不正なkeyでは一致しない
        $this->createContent();
        Flight::request()->query->setData(['type' => '!!']);

        (new Content())->getList($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame([], $body['data']);
    }

    public function testGetListRowsOverMaxIsLimitedToMax(): void
    {
        for ($i = 1; $i <= 12; $i++)
        {
            $this->createContent();
        }
        Flight::request()->query->setData(['rows' => '1000']);

        (new Content())->getList($this->siteKey);

        // 上限（10件）を超える値は上限として扱う
        $this->assertCount(10, $this->response()[1]['data']);
    }

    public function testGetListMissingSiteReturns404(): void
    {
        (new Content())->getList($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testGetListWithInvalidTokenReturns401(): void
    {
        $content = new class extends Content
        {
            public function __construct()
            {
                parent::__construct();
                $this->bearer_token = 'invalid';
            }
        };

        $content->getList($this->siteKey);

        $this->assertSame(401, $this->response()[0]);
    }

    /*
     * GET /sites/@site/contents/@type
     */

    public function testGetListByTypeReturnsContentsOfTypeIncludingDraftsForWriter(): void
    {
        $typeId = HashTools::decodeSqid($this->typeKey);
        $this->createContent(['title' => '公開', 'type_id' => $typeId, 'published_at' => '2026-01-01 00:00:00']);
        $this->createContent(['title' => '下書き', 'type_id' => $typeId, 'status' => 'draft', 'published_at' => null]);
        $this->createContent(['title' => '別タイプ']);
        // クエリの`type`は無視される（パスのタイプが優先）
        Flight::request()->query->setData(['type' => '!!']);

        (new AuthenticatedContent())->getList($this->siteKey, $this->typeKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(['公開', '下書き'], array_column($body['data'], 'title'));

        // トークンなしは公開中のみ
        Flight::response()->clear();
        (new Content())->getList($this->siteKey, $this->typeKey);
        $this->assertSame(['公開'], array_column($this->response()[1]['data'], 'title'));
    }

    public function testGetListByTypeCombinesOtherFilters(): void
    {
        $typeId = HashTools::decodeSqid($this->typeKey);
        $this->createContent(['title' => '対象', 'type_id' => $typeId], [$this->tagKey1]);
        $this->createContent(['title' => 'タグなし', 'type_id' => $typeId]);
        Flight::request()->query->setData(['tag' => $this->tagKey1]);

        (new Content())->getList($this->siteKey, $this->typeKey);

        $this->assertSame(['対象'], array_column($this->response()[1]['data'], 'title'));
    }

    public function testGetListByTypeMissingSiteOrTypeReturns404(): void
    {
        (new Content())->getList($this->missingKey(), $this->typeKey);
        $this->assertSame(404, $this->response()[0]);

        Flight::response()->clear();
        (new Content())->getList($this->siteKey, $this->missingKey());
        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * GET /sites/@site/contents/@type/latest
     */

    public function testGetLatestReturnsPublishedOfTypeInOrder(): void
    {
        $typeId = HashTools::decodeSqid($this->typeKey);
        $this->createContent(['title' => '古い記事', 'type_id' => $typeId, 'published_at' => '2026-01-01 00:00:00']);
        $this->createContent(['title' => '新しい記事', 'type_id' => $typeId, 'published_at' => '2026-06-01 00:00:00']);
        $this->createContent(['title' => '別タイプ']);
        $this->createContent(['title' => '下書き', 'type_id' => $typeId, 'status' => 'draft', 'published_at' => null]);

        // サイトの執筆者のトークンがあっても下書きは含めない
        (new AuthenticatedContent())->getLatest($this->siteKey, $this->typeKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(['新しい記事', '古い記事'], array_column($body['data'], 'title'));
    }

    public function testGetLatestLimitsRows(): void
    {
        $typeId = HashTools::decodeSqid($this->typeKey);
        for ($i = 1; $i <= 12; $i++)
        {
            $this->createContent(['type_id' => $typeId, 'published_at' => sprintf('2026-01-%02d 00:00:00', $i)]);
        }

        (new Content())->getLatest($this->siteKey, $this->typeKey);
        $this->assertCount(10, $this->response()[1]['data']);

        Flight::response()->clear();
        Flight::request()->query->setData(['limit' => '3']);
        (new Content())->getLatest($this->siteKey, $this->typeKey);
        $this->assertSame('2026-01-12 00:00:00', $this->response()[1]['data'][0]['published_at']);
        $this->assertCount(3, $this->response()[1]['data']);

        // 上限を超える値は上限（10件）とする
        Flight::response()->clear();
        Flight::request()->query->setData(['limit' => '20']);
        (new Content())->getLatest($this->siteKey, $this->typeKey);
        $this->assertCount(10, $this->response()[1]['data']);
    }

    public function testGetLatestMissingSiteOrTypeReturns404(): void
    {
        (new Content())->getLatest($this->missingKey(), $this->typeKey);
        $this->assertSame(404, $this->response()[0]);

        Flight::response()->clear();
        (new Content())->getLatest($this->siteKey, $this->missingKey());
        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * GET /contents/@id
     */

    public function testGetDetailReturnsPublishedContent(): void
    {
        $key = $this->createContent([], [$this->tagKey1, $this->tagKey2]);

        (new Content())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame($key, $body['data']['key']);
        $this->assertSame('本文', $body['data']['body']);
        $this->assertSame(['タグ1', 'タグ2'], array_column($body['data']['tags'], 'tag'));
    }

    public function testGetDetailDraftWithoutTokenReturns404(): void
    {
        $key = $this->createContent(['status' => 'draft', 'published_at' => null]);

        (new Content())->getDetail($key);

        $this->assertSame(404, $this->response()[0]);
    }

    public function testGetDetailDraftWithTokenReturns200(): void
    {
        $key = $this->createContent(['status' => 'draft', 'published_at' => null]);

        (new AuthenticatedContent())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame('draft', $body['data']['status']);
        $this->assertNull($body['data']['published_at']);
    }

    /*
     * POST /sites/@site/contents
     */

    public function testExecAddReturns201WithReferencesAndTags(): void
    {
        Flight::request()->data->setData($this->validInput([
            'type' => $this->typeKey,
            'category' => $this->categoryKey,
            'tags' => [$this->tagKey1, $this->tagKey2],
        ]));

        (new AuthenticatedContent())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame('テスト記事', $body['data']['title']);
        $this->assertSame(HashTools::decodeSqid($this->writerKey), HashTools::decodeSqid($body['data']['writer_key']));
        $this->assertSame(HashTools::decodeSqid($this->categoryKey), HashTools::decodeSqid($body['data']['category_key']));
        $this->assertSame(['タグ1', 'タグ2'], array_column($body['data']['tags'], 'tag'));
        // 公開日時未指定の公開は現在日時が入る
        $this->assertNotNull($body['data']['published_at']);
    }

    public function testExecAddDraftHasNoPublishedAt(): void
    {
        Flight::request()->data->setData($this->validInput(['status' => 'draft']));

        (new AuthenticatedContent())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame('draft', $body['data']['status']);
        $this->assertNull($body['data']['published_at']);
    }

    public function testExecAddWithPublishedAtKeepsValue(): void
    {
        Flight::request()->data->setData($this->validInput(['published_at' => '2099-01-01 09:00:00']));

        (new AuthenticatedContent())->execAdd($this->siteKey);

        $this->assertSame('2099-01-01 09:00:00', $this->response()[1]['data']['published_at']);
    }

    /**
     * 入力チェックでエラーになる値（上書きする入力値、エラーになる項目）
     *
     * @return array<string, array{0: array, 1: string}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'タイトル未入力' => [['title' => ''], 'title'],
            'タイトルが255文字超' => [['title' => str_repeat('あ', 256)], 'title'],
            '本文が65535バイト超' => [['body' => str_repeat('a', 65536)], 'body'],
            '公開状態が不正' => [['status' => 'private'], 'status'],
            '公開日時の形式が不正' => [['published_at' => '2026/01/01'], 'published_at'],
            '存在しない日付' => [['published_at' => '2026-02-30 00:00:00'], 'published_at'],
            'タイプが存在しない' => [['type' => '!!'], 'type'],
            'タグが配列でない' => [['tags' => 'タグ1'], 'tags'],
        ];
    }

    #[DataProvider('invalidInputProvider')]
    public function testExecAddInvalidInputReturns400WithErrors(array $values, string $errorKey): void
    {
        Flight::request()->data->setData($this->validInput($values));

        (new AuthenticatedContent())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey($errorKey, $body['data']);
    }

    public function testExecAddWithCategoryAndTagOfOtherSiteReturns400(): void
    {
        $otherSiteId = HashTools::decodeSqid($this->createSite('https://other.example.com'));
        CategoryModel::add($otherSiteId, 0, '別サイト');
        $otherCategoryKey = $this->lastKey();
        TagModel::add($otherSiteId, '別サイト');
        $otherTagKey = $this->lastKey();
        Flight::request()->data->setData($this->validInput([
            'category' => $otherCategoryKey,
            'tags' => [$otherTagKey],
        ]));

        (new AuthenticatedContent())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('category', $body['data']);
        $this->assertArrayHasKey('tags', $body['data']);
    }

    public function testExecAddMissingSiteReturns404(): void
    {
        Flight::request()->data->setData($this->validInput());

        (new AuthenticatedContent())->execAdd($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * PUT /contents/@id
     */

    public function testExecUpdateReplacesValuesAndTags(): void
    {
        $key = $this->createContent([], [$this->tagKey1]);
        Flight::request()->data->setData($this->validInput([
            'title' => '更新後',
            'category' => $this->categoryKey,
            'tags' => [$this->tagKey2],
        ]));

        (new AuthenticatedContent())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $id = HashTools::decodeSqid($key);
        $content = ContentModel::detail($id, true);
        $this->assertSame('更新後', $content['title']);
        $this->assertSame(HashTools::decodeSqid($this->categoryKey), intval($content['category_id']));
        $this->assertSame(['タグ2'], array_column(ContentModel::tagsOf([$id])[$id], 'tag'));
    }

    public function testExecUpdatePublishedKeepsPublishedAt(): void
    {
        $key = $this->createContent(['published_at' => '2026-01-01 00:00:00']);
        Flight::request()->data->setData($this->validInput(['title' => '更新後']));

        (new AuthenticatedContent())->execUpdate($key);

        $content = ContentModel::detail(HashTools::decodeSqid($key), true);
        $this->assertSame('2026-01-01 00:00:00', $content['published_at']);
    }

    public function testExecUpdateWithoutTagsRemovesTags(): void
    {
        $key = $this->createContent([], [$this->tagKey1]);
        Flight::request()->data->setData($this->validInput());

        (new AuthenticatedContent())->execUpdate($key);

        $id = HashTools::decodeSqid($key);
        $this->assertSame([], ContentModel::tagsOf([$id])[$id]);
    }

    public function testExecUpdateMissingKeyReturns404(): void
    {
        Flight::request()->data->setData($this->validInput());

        (new AuthenticatedContent())->execUpdate($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * DELETE /contents/@id
     */

    public function testExecDeleteRemovesContentAndTags(): void
    {
        $key = $this->createContent([], [$this->tagKey1]);
        $id = HashTools::decodeSqid($key);

        (new AuthenticatedContent())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame([], ContentModel::detail($id, true));
        $this->assertSame([], ContentModel::tagsOf([$id])[$id]);
    }

    public function testExecDeleteMissingKeyReturns404(): void
    {
        (new AuthenticatedContent())->execDelete($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * 認証
     */

    public function testWriteWithoutTokenReturns401(): void
    {
        Flight::request()->data->setData($this->validInput());

        (new Content())->execAdd($this->siteKey);

        $this->assertSame(401, $this->response()[0]);
    }

    /*
     * 権限
     */

    public function testExecAddSetsLoginWriterIgnoringBody(): void
    {
        $otherKey = $this->registerWriter('__phpunit_other__', '別の執筆者');
        Flight::request()->data->setData($this->validInput(['writer' => $otherKey]));

        (new AuthenticatedContent())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        // ボディのwriterは無視し、トークンの持ち主が執筆者になる
        $this->assertSame(201, $status);
        $this->assertSame(HashTools::decodeSqid($this->writerKey), HashTools::decodeSqid($body['data']['writer_key']));
    }

    public function testExecAddByNonSiteWriterReturns403(): void
    {
        $this->registerWriter('__phpunit_other__', '別の執筆者');
        $this->loginAs('__phpunit_other__');
        Flight::request()->data->setData($this->validInput());

        (new AuthenticatedContent())->execAdd($this->siteKey);

        $this->assertSame(403, $this->response()[0]);
    }

    public function testExecAddByAdminWithoutSiteReturns201(): void
    {
        $this->loginAs(self::ADMIN_USER_ID);
        Flight::request()->data->setData($this->validInput());

        (new AuthenticatedContent())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame(HashTools::decodeSqid($this->adminKey), HashTools::decodeSqid($body['data']['writer_key']));
    }

    /**
     * 更新・削除の操作
     *
     * @return array<string, array{0: string}>
     */
    public static function editOperationProvider(): array
    {
        return [
            'PUT' => ['execUpdate'],
            'DELETE' => ['execDelete'],
        ];
    }

    #[DataProvider('editOperationProvider')]
    public function testEditOtherWritersContentReturns403(string $method): void
    {
        // 同じサイトの執筆者でも、他人のコンテンツは変更できない
        $key = $this->createContent(['title' => '他人の記事']);
        $otherKey = $this->registerWriter('__phpunit_other__', '別の執筆者');
        $this->joinSite($this->siteKey, $otherKey);
        $this->loginAs('__phpunit_other__');
        Flight::request()->data->setData($this->validInput(['title' => '更新後']));

        (new AuthenticatedContent())->$method($key);

        $this->assertSame(403, $this->response()[0]);
        $this->assertSame('他人の記事', ContentModel::detail(HashTools::decodeSqid($key), true)['title']);
    }

    #[DataProvider('editOperationProvider')]
    public function testEditOwnContentAfterLeavingSiteReturns403(string $method): void
    {
        $key = $this->createContent();
        SiteWriterModel::delete($this->siteId, HashTools::decodeSqid($this->writerKey));
        Flight::request()->data->setData($this->validInput());

        (new AuthenticatedContent())->$method($key);

        $this->assertSame(403, $this->response()[0]);
    }

    public function testExecUpdateByAdminKeepsWriter(): void
    {
        $key = $this->createContent();
        $this->loginAs(self::ADMIN_USER_ID);
        Flight::request()->data->setData($this->validInput(['title' => '管理者が更新']));

        (new AuthenticatedContent())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $content = ContentModel::detail(HashTools::decodeSqid($key), true);
        $this->assertSame('管理者が更新', $content['title']);
        $this->assertSame(HashTools::decodeSqid($this->writerKey), intval($content['writer_id']));
    }

    public function testExecDeleteByAdminReturns200(): void
    {
        $key = $this->createContent();
        $this->loginAs(self::ADMIN_USER_ID);

        (new AuthenticatedContent())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
    }

    public function testGetByNonSiteWriterReturnsOnlyPublished(): void
    {
        $this->createContent(['title' => '公開中']);
        $draftKey = $this->createContent(['title' => '下書き', 'status' => 'draft', 'published_at' => null]);
        // トークンは有効だが、このサイトの執筆者ではない
        $this->registerWriter('__phpunit_other__', '別の執筆者');
        $this->loginAs('__phpunit_other__');

        (new AuthenticatedContent())->getList($this->siteKey);
        [$listStatus, $list] = $this->response();
        Flight::response()->clear();
        (new AuthenticatedContent())->getDetail($draftKey);

        $this->assertSame(200, $listStatus);
        $this->assertSame(['公開中'], array_column($list['data'], 'title'));
        $this->assertSame(404, $this->response()[0]);
    }

    public function testGetDraftByAdminReturns200(): void
    {
        $key = $this->createContent(['status' => 'draft', 'published_at' => null]);
        $this->loginAs(self::ADMIN_USER_ID);

        (new AuthenticatedContent())->getDetail($key);

        $this->assertSame(200, $this->response()[0]);
    }

    /*
     * 執筆者の削除（コンテンツから参照されている場合）
     */

    public function testDeleteWriterReferencedByContentReturns409(): void
    {
        $this->createContent();
        $this->loginAs(self::ADMIN_USER_ID);

        (new AuthenticatedWriter())->execDelete($this->writerKey);

        $this->assertSame(409, $this->response()[0]);
    }
}
