<?php

use App\Shared\Utils\HashTools;

/**
 * カテゴリーAPI（Category）のテスト
 * 仕様は`/documents/api/categories.md`を参照
 */
class CategoryTest extends DbTestCase
{
    /** テスト用サイトのkey（setUpで登録） */
    private string $siteKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteKey = $this->createSite();
    }

    /**
     * テスト用のカテゴリーを登録してkeyを返す
     *
     * @param string $category
     * @param string $parentKey 親カテゴリーのkey（最上位は空文字）
     * @param ?string $siteKey 所属サイトのkey（省略時はテスト用サイト）
     * @return string
     */
    private function createCategory(string $category = 'テスト', string $parentKey = '', ?string $siteKey = null): string
    {
        CategoryModel::add(
            HashTools::decodeSqid($siteKey ?? $this->siteKey),
            $parentKey === '' ? 0 : HashTools::decodeSqid($parentKey),
            $category
        );
        return HashTools::generateSqid(intval(BaseModel::getInsertId()));
    }

    /**
     * カテゴリーの親IDを取得する
     *
     * @param string $key
     * @return integer
     */
    private function parentIdOf(string $key): int
    {
        return intval(CategoryModel::detail(HashTools::decodeSqid($key))['parent_id']);
    }

    /*
     * GET /sites/@site/categories
     */

    public function testGetListReturnsOnlySiteCategories(): void
    {
        $parentKey = $this->createCategory('親');
        $this->createCategory('子', $parentKey);
        // 別サイトのカテゴリーは含まれない
        $this->createCategory('別サイト', '', $this->createSite('https://other.example.com'));

        (new Category())->getList($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(['親', '子'], array_column($body['data'], 'category'));
        foreach ($body['data'] as $row)
        {
            $this->assertArrayNotHasKey('id', $row);
            $this->assertArrayNotHasKey('parent_id', $row);
            $this->assertArrayNotHasKey('site_id', $row);
        }
        // 最上位の親はnull、子の親は親カテゴリーを指す
        $this->assertNull($body['data'][0]['parent_key']);
        $this->assertSame(
            HashTools::decodeSqid($parentKey),
            HashTools::decodeSqid($body['data'][1]['parent_key'])
        );
    }

    public function testGetListMissingSiteReturns404(): void
    {
        (new Category())->getList($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * GET /categories/@id
     */

    public function testGetDetailReturnsCategory(): void
    {
        $key = $this->createCategory();

        (new Category())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame('テスト', $body['data']['category']);
        $this->assertSame($key, $body['data']['key']);
        $this->assertSame(HashTools::decodeSqid($this->siteKey), HashTools::decodeSqid($body['data']['site_key']));
    }

    public function testGetDetailMissingKeyReturns404(): void
    {
        (new Category())->getDetail($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * POST /sites/@site/categories
     */

    public function testExecAddTopLevelReturns201(): void
    {
        Flight::request()->data->setData(['category' => 'テスト']);

        (new AuthenticatedCategory())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame('テスト', $body['data']['category']);
        $this->assertNull($body['data']['parent_key']);
    }

    public function testExecAddWithParentReturns201(): void
    {
        $parentKey = $this->createCategory('親');
        Flight::request()->data->setData(['category' => '子', 'parent' => $parentKey]);

        (new AuthenticatedCategory())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame(HashTools::decodeSqid($parentKey), $this->parentIdOf($body['data']['key']));
    }

    public function testExecAddMissingSiteReturns404(): void
    {
        Flight::request()->data->setData(['category' => 'テスト']);

        (new AuthenticatedCategory())->execAdd($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecAddWithoutNameReturns400(): void
    {
        (new AuthenticatedCategory())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('category', $body['data']);
    }

    public function testExecAddWithParentOfOtherSiteReturns400(): void
    {
        $otherParentKey = $this->createCategory('別サイト', '', $this->createSite('https://other.example.com'));
        Flight::request()->data->setData(['category' => '子', 'parent' => $otherParentKey]);

        (new AuthenticatedCategory())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('parent', $body['data']);
    }

    public function testExecAddDuplicateNameInSameParentReturns409(): void
    {
        $this->createCategory();
        Flight::request()->data->setData(['category' => 'テスト']);

        (new AuthenticatedCategory())->execAdd($this->siteKey);

        $this->assertSame(409, $this->response()[0]);
    }

    public function testExecAddSameNameUnderOtherParentReturns201(): void
    {
        $this->createCategory();
        $parentKey = $this->createCategory('親');
        Flight::request()->data->setData(['category' => 'テスト', 'parent' => $parentKey]);

        (new AuthenticatedCategory())->execAdd($this->siteKey);

        $this->assertSame(201, $this->response()[0]);
    }

    /*
     * PUT /categories/@id
     */

    public function testExecUpdateChangesNameAndParent(): void
    {
        $parentKey = $this->createCategory('親');
        $key = $this->createCategory('子');
        Flight::request()->data->setData(['category' => '更新後', 'parent' => $parentKey]);

        (new AuthenticatedCategory())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame('更新後', CategoryModel::detail(HashTools::decodeSqid($key))['category']);
        $this->assertSame(HashTools::decodeSqid($parentKey), $this->parentIdOf($key));
    }

    public function testExecUpdateWithoutParentMovesToTop(): void
    {
        $parentKey = $this->createCategory('親');
        $key = $this->createCategory('子', $parentKey);
        Flight::request()->data->setData(['category' => '子']);

        (new AuthenticatedCategory())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame(0, $this->parentIdOf($key));
    }

    public function testExecUpdateSelfAsParentReturns400(): void
    {
        $key = $this->createCategory();
        Flight::request()->data->setData(['category' => 'テスト', 'parent' => $key]);

        (new AuthenticatedCategory())->execUpdate($key);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('parent', $body['data']);
    }

    public function testExecUpdateDescendantAsParentReturns400(): void
    {
        $key = $this->createCategory('親');
        $childKey = $this->createCategory('子', $key);
        $grandchildKey = $this->createCategory('孫', $childKey);
        Flight::request()->data->setData(['category' => '親', 'parent' => $grandchildKey]);

        (new AuthenticatedCategory())->execUpdate($key);

        $this->assertSame(400, $this->response()[0]);
        $this->assertSame(0, $this->parentIdOf($key));
    }

    public function testExecUpdateMissingKeyReturns404(): void
    {
        Flight::request()->data->setData(['category' => 'テスト']);

        (new AuthenticatedCategory())->execUpdate($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecUpdateDuplicateNameReturns409(): void
    {
        $this->createCategory();
        $key = $this->createCategory('別名');
        Flight::request()->data->setData(['category' => 'テスト']);

        (new AuthenticatedCategory())->execUpdate($key);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * DELETE /categories/@id
     */

    public function testExecDeleteRemovesCategory(): void
    {
        $key = $this->createCategory();

        (new AuthenticatedCategory())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame([], CategoryModel::detail(HashTools::decodeSqid($key)));
    }

    public function testExecDeleteMissingKeyReturns404(): void
    {
        (new AuthenticatedCategory())->execDelete($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecDeleteWithChildReturns409(): void
    {
        $key = $this->createCategory('親');
        $this->createCategory('子', $key);

        (new AuthenticatedCategory())->execDelete($key);

        $this->assertSame(409, $this->response()[0]);
    }

    public function testExecDeleteReferencedByContentReturns409(): void
    {
        $key = $this->createCategory();
        BaseModel::query(
            "INSERT INTO contents (title, category_id, site_id, writer_id) VALUES ('テスト', ?, ?, 1)",
            [HashTools::decodeSqid($key), HashTools::decodeSqid($this->siteKey)]
        );

        (new AuthenticatedCategory())->execDelete($key);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * 認証
     */

    public function testWriteWithoutTokenReturns401(): void
    {
        Flight::request()->data->setData(['category' => 'テスト']);

        (new Category())->execAdd($this->siteKey);

        $this->assertSame(401, $this->response()[0]);
    }
}
