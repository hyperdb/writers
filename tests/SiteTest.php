<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * サイトAPI（Site）のテスト
 * 仕様は`/documents/api/sites.md`を参照
 * テスト用サイトの登録（createSite）は`DbTestCase`を利用（既定のURLはself::URLと同じ）
 */
class SiteTest extends DbTestCase
{
    /** テストで登録するサイトのURL */
    private const URL = 'https://phpunit.example.com';

    /*
     * GET /sites
     */

    public function testGetListReturnsKeyInsteadOfId(): void
    {
        $this->createSite();
        [$status, $rows] = $this->fetchAllPages(fn() => (new Site())->getList());

        $this->assertSame(200, $status);
        $this->assertContains(self::URL, array_column($rows, 'url'));
        foreach ($rows as $row)
        {
            $this->assertArrayHasKey('key', $row);
            $this->assertArrayNotHasKey('id', $row);
        }
    }

    /*
     * GET /sites/@id
     */

    public function testGetDetailReturnsSite(): void
    {
        $key = $this->createSite();

        (new Site())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(self::URL, $body['data']['url']);
        $this->assertSame($key, $body['data']['key']);
        $this->assertArrayNotHasKey('id', $body['data']);
    }

    public function testGetDetailMissingKeyReturns404(): void
    {
        (new Site())->getDetail($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * POST /sites
     */

    public function testExecAddReturns201AndTrimsTrailingSlash(): void
    {
        Flight::request()->data->setData(['url' => self::URL . '/', 'name' => 'テストサイト']);

        (new AuthenticatedSite())->execAdd();
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame(self::URL, $body['data']['url']);
        $this->assertArrayNotHasKey('id', $body['data']);
        $site = SiteModel::detail(HashTools::decodeSqid($body['data']['key']));
        $this->assertSame(self::URL, $site['url']);
    }

    /**
     * 入力チェックでエラーになる値
     *
     * @return array<string, array{0: array, 1: string}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'URL未入力' => [['name' => 'テスト'], 'url'],
            'サイト名未入力' => [['url' => self::URL], 'name'],
            'http(s)以外' => [['url' => 'ftp://example.com', 'name' => 'テスト'], 'url'],
            'スキームのみ' => [['url' => 'https://', 'name' => 'テスト'], 'url'],
            'URLが255文字超' => [['url' => 'https://example.com/' . str_repeat('a', 236), 'name' => 'テスト'], 'url'],
            'サイト名が255文字超' => [['url' => self::URL, 'name' => str_repeat('あ', 256)], 'name'],
        ];
    }

    #[DataProvider('invalidInputProvider')]
    public function testExecAddInvalidInputReturns400WithErrors(array $input, string $errorKey): void
    {
        Flight::request()->data->setData($input);

        (new AuthenticatedSite())->execAdd();
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey($errorKey, $body['data']);
    }

    public function testExecAddDuplicateUrlReturns409(): void
    {
        $this->createSite();
        // 末尾の"/"だけが違うURLも重複とみなす
        Flight::request()->data->setData(['url' => self::URL . '/', 'name' => '別のサイト']);

        (new AuthenticatedSite())->execAdd();

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * PUT /sites/@id
     */

    public function testExecUpdateChangesSite(): void
    {
        $key = $this->createSite();
        Flight::request()->data->setData(['url' => 'https://updated.example.com', 'name' => '更新後']);

        (new AuthenticatedSite())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $site = SiteModel::detail(HashTools::decodeSqid($key));
        $this->assertSame('https://updated.example.com', $site['url']);
        $this->assertSame('更新後', $site['name']);
    }

    public function testExecUpdateWithSameValueReturns200(): void
    {
        $key = $this->createSite();
        Flight::request()->data->setData(['url' => self::URL, 'name' => 'テストサイト']);

        (new AuthenticatedSite())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
    }

    public function testExecUpdateInvalidInputReturns400(): void
    {
        $key = $this->createSite();
        Flight::request()->data->setData(['url' => 'not-a-url', 'name' => '更新後']);

        (new AuthenticatedSite())->execUpdate($key);

        $this->assertSame(400, $this->response()[0]);
    }

    public function testExecUpdateMissingKeyReturns404(): void
    {
        Flight::request()->data->setData(['url' => self::URL, 'name' => '更新後']);

        (new AuthenticatedSite())->execUpdate($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecUpdateDuplicateUrlReturns409(): void
    {
        $this->createSite();
        $key = $this->createSite('https://other.example.com', '別のサイト');
        Flight::request()->data->setData(['url' => self::URL, 'name' => '別のサイト']);

        (new AuthenticatedSite())->execUpdate($key);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * DELETE /sites/@id
     */

    public function testExecDeleteRemovesSite(): void
    {
        $key = $this->createSite();

        (new AuthenticatedSite())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame([], SiteModel::detail(HashTools::decodeSqid($key)));
    }

    public function testExecDeleteMissingKeyReturns404(): void
    {
        (new AuthenticatedSite())->execDelete($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /**
     * サイトに紐付くデータを登録するSQL（`?`にサイトIDが入る）
     *
     * @return array<string, array{0: string}>
     */
    public static function relatedDataProvider(): array
    {
        return [
            'コンテンツ' => ["INSERT INTO contents (title, site_id, writer_id) VALUES ('テスト', ?, 1)"],
            'カテゴリー' => ["INSERT INTO categories (category, site_id) VALUES ('テスト', ?)"],
            'タグ' => ["INSERT INTO tags (tag, site_id) VALUES ('テスト', ?)"],
            'サイト執筆者' => ["INSERT INTO site_writers (site_id, writer_id) VALUES (?, 1)"],
        ];
    }

    #[DataProvider('relatedDataProvider')]
    public function testExecDeleteWithRelatedDataReturns409(string $sql): void
    {
        $key = $this->createSite();
        BaseModel::query($sql, [HashTools::decodeSqid($key)]);

        (new AuthenticatedSite())->execDelete($key);

        $this->assertSame(409, $this->response()[0]);
        // 削除されていない
        $this->assertNotSame([], SiteModel::detail(HashTools::decodeSqid($key)));
    }

    /*
     * 認証（POST / PUT / DELETE）
     */

    public function testWriteWithoutTokenReturns401(): void
    {
        Flight::request()->data->setData(['url' => self::URL, 'name' => 'テストサイト']);

        (new Site())->execAdd();

        $this->assertSame(401, $this->response()[0]);
        $this->assertSame([], BaseModel::fetchAll("SELECT id FROM sites WHERE url = ?", [self::URL]));
    }

    /*
     * 権限（管理者のみ）
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
    public function testWriteByNonAdminReturns403(string $method): void
    {
        $key = $this->createSite('https://phpunit-before.example.com', '変更前');
        // 一般の執筆者としてログイン
        $this->registerWriter('__phpunit_writer__');
        $this->loginAs('__phpunit_writer__');
        Flight::request()->data->setData(['url' => self::URL, 'name' => '変更後']);

        (new AuthenticatedSite())->$method(...($method === 'execAdd' ? [] : [$key]));

        $this->assertSame(403, $this->response()[0]);
        // 追加・変更・削除されていない
        $this->assertSame([], BaseModel::fetchAll("SELECT id FROM sites WHERE url = ?", [self::URL]));
        $this->assertSame('変更前', SiteModel::detail(HashTools::decodeSqid($key))['name']);
    }
}
