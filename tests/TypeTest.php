<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * コンテンツタイプAPI（Type）のテスト
 * 仕様は`/documents/api/types.md`を参照
 */
class TypeTest extends DbTestCase
{
    /** テストで登録するタイプ名 */
    private const NAME = '__phpunit_type__';

    /**
     * テスト用のタイプを登録してkeyを返す
     *
     * @param string $name
     * @return string
     */
    private function createType(string $name = self::NAME): string
    {
        TypeModel::add($name);
        return HashTools::generateSqid(intval(BaseModel::getInsertId()));
    }

    /*
     * GET /types
     */

    public function testGetListReturnsKeyInsteadOfId(): void
    {
        $this->createType();
        Flight::request()->query->setData(['rows' => '1000']);

        (new Type())->getList();
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertContains(self::NAME, array_column($body['data'], 'name'));
        foreach ($body['data'] as $row)
        {
            $this->assertArrayHasKey('key', $row);
            $this->assertArrayNotHasKey('id', $row);
        }
    }

    /*
     * GET /types/@id
     */

    public function testGetDetailReturnsType(): void
    {
        $key = $this->createType();

        (new Type())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(self::NAME, $body['data']['name']);
        $this->assertSame($key, $body['data']['key']);
    }

    public function testGetDetailMissingKeyReturns404(): void
    {
        (new Type())->getDetail($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * POST /types
     */

    public function testExecAddReturns201(): void
    {
        Flight::request()->data->setData(['name' => self::NAME]);

        (new AuthenticatedType())->execAdd();
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame(self::NAME, $body['data']['name']);
        $this->assertArrayNotHasKey('id', $body['data']);
    }

    public function testExecAddInvalidInputReturns400WithErrors(): void
    {
        Flight::request()->data->setData(['name' => str_repeat('あ', 256)]);

        (new AuthenticatedType())->execAdd();
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('name', $body['data']);
    }

    public function testExecAddDuplicateNameReturns409(): void
    {
        $this->createType();
        Flight::request()->data->setData(['name' => self::NAME]);

        (new AuthenticatedType())->execAdd();

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * PUT /types/@id
     */

    public function testExecUpdateChangesName(): void
    {
        $key = $this->createType();
        Flight::request()->data->setData(['name' => '__phpunit_updated__']);

        (new AuthenticatedType())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame('__phpunit_updated__', TypeModel::detail(HashTools::decodeSqid($key))['name']);
    }

    public function testExecUpdateWithSameValueReturns200(): void
    {
        $key = $this->createType();
        Flight::request()->data->setData(['name' => self::NAME]);

        (new AuthenticatedType())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
    }

    public function testExecUpdateMissingKeyReturns404(): void
    {
        Flight::request()->data->setData(['name' => self::NAME]);

        (new AuthenticatedType())->execUpdate($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecUpdateDuplicateNameReturns409(): void
    {
        $this->createType();
        $key = $this->createType('__phpunit_other__');
        Flight::request()->data->setData(['name' => self::NAME]);

        (new AuthenticatedType())->execUpdate($key);

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * DELETE /types/@id
     */

    public function testExecDeleteRemovesType(): void
    {
        $key = $this->createType();

        (new AuthenticatedType())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame([], TypeModel::detail(HashTools::decodeSqid($key)));
    }

    public function testExecDeleteMissingKeyReturns404(): void
    {
        (new AuthenticatedType())->execDelete($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecDeleteReferencedByContentReturns409(): void
    {
        $key = $this->createType();
        BaseModel::query(
            "INSERT INTO contents (title, type_id, site_id, writer_id) VALUES ('テスト', ?, 1, 1)",
            [HashTools::decodeSqid($key)]
        );

        (new AuthenticatedType())->execDelete($key);

        $this->assertSame(409, $this->response()[0]);
        $this->assertNotSame([], TypeModel::detail(HashTools::decodeSqid($key)));
    }

    /*
     * 認証
     */

    public function testWriteWithoutTokenReturns401(): void
    {
        Flight::request()->data->setData(['name' => self::NAME]);

        (new Type())->execAdd();

        $this->assertSame(401, $this->response()[0]);
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
        $key = $this->createType('__phpunit_before__');
        // 一般の執筆者としてログイン
        $this->registerWriter('__phpunit_writer__');
        $this->loginAs('__phpunit_writer__');
        Flight::request()->data->setData(['name' => self::NAME]);

        (new AuthenticatedType())->$method(...($method === 'execAdd' ? [] : [$key]));

        $this->assertSame(403, $this->response()[0]);
        // 追加・変更・削除されていない
        $this->assertSame('__phpunit_before__', TypeModel::detail(HashTools::decodeSqid($key))['name']);
        $this->assertSame([], BaseModel::fetchAll("SELECT id FROM types WHERE name = ?", [self::NAME]));
    }
}
