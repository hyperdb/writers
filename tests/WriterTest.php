<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * 執筆者API（Writer）のテスト
 * 仕様は`/documents/api/writers.md`を参照
 */
class WriterTest extends DbTestCase
{
    /** テストで登録する執筆者のユーザーID */
    private const USER_ID = '__phpunit_user__';

    /**
     * テスト用の執筆者を登録してkeyを返す
     *
     * @param string $user_id
     * @param string $nickname
     * @return string
     */
    private function createWriter(string $user_id = self::USER_ID, string $nickname = 'テスト'): string
    {
        WriterModel::add($user_id, $nickname);
        return HashTools::generateSqid(intval(BaseModel::getInsertId()));
    }

    /*
     * GET /writers
     */

    public function testGetListReturnsKeyInsteadOfId(): void
    {
        $this->createWriter();
        Flight::request()->query->setData(['rows' => '1000']);

        (new Writer())->getList();
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertContains(self::USER_ID, array_column($body['data'], 'user_id'));
        foreach ($body['data'] as $row)
        {
            $this->assertArrayHasKey('key', $row);
            $this->assertArrayNotHasKey('id', $row);
        }
    }

    public function testGetListOutOfRangeReturnsEmpty(): void
    {
        Flight::request()->query->setData(['page' => '999999']);

        (new Writer())->getList();
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame([], $body['data']);
    }

    /*
     * GET /writers/@id
     */

    public function testGetDetailReturnsWriter(): void
    {
        $key = $this->createWriter();

        (new Writer())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(self::USER_ID, $body['data']['user_id']);
        $this->assertSame($key, $body['data']['key']);
        $this->assertArrayNotHasKey('id', $body['data']);
    }

    public function testGetDetailMissingKeyReturns404(): void
    {
        (new Writer())->getDetail($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testGetDetailInvalidKeyReturns404(): void
    {
        (new Writer())->getDetail('!!');

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * POST /writers
     */

    public function testExecAddReturns201WithKey(): void
    {
        Flight::request()->data->setData(['user_id' => self::USER_ID, 'nickname' => 'テスト']);

        (new AuthenticatedWriter())->execAdd();
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame(self::USER_ID, $body['data']['user_id']);
        $this->assertArrayNotHasKey('id', $body['data']);
        // 返却されたkeyで登録内容を取得できる
        $writer = WriterModel::detail(HashTools::decodeSqid($body['data']['key']));
        $this->assertSame(self::USER_ID, $writer['user_id']);
    }

    public function testExecAddWithoutNicknameReturns400(): void
    {
        Flight::request()->data->setData(['user_id' => self::USER_ID]);

        (new AuthenticatedWriter())->execAdd();

        $this->assertSame(400, $this->response()[0]);
    }

    public function testExecAddTooLongNicknameReturns400WithErrors(): void
    {
        Flight::request()->data->setData(['user_id' => self::USER_ID, 'nickname' => str_repeat('あ', 33)]);

        (new AuthenticatedWriter())->execAdd();
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('nickname', $body['data']);
    }

    public function testExecAddDuplicateUserIdReturns409(): void
    {
        $this->createWriter();
        Flight::request()->data->setData(['user_id' => self::USER_ID, 'nickname' => '別名']);

        (new AuthenticatedWriter())->execAdd();

        $this->assertSame(409, $this->response()[0]);
    }

    /*
     * PUT /writers/@id
     */

    public function testExecUpdateChangesNickname(): void
    {
        $key = $this->createWriter();
        Flight::request()->data->setData(['nickname' => '更新後']);

        (new AuthenticatedWriter())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame('更新後', WriterModel::detail(HashTools::decodeSqid($key))['nickname']);
    }

    public function testExecUpdateWithSameValueReturns200(): void
    {
        $key = $this->createWriter(nickname: '同じ値');
        Flight::request()->data->setData(['nickname' => '同じ値']);

        (new AuthenticatedWriter())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
    }

    public function testExecUpdateWithoutNicknameReturns400(): void
    {
        $key = $this->createWriter();

        (new AuthenticatedWriter())->execUpdate($key);

        $this->assertSame(400, $this->response()[0]);
    }

    public function testExecUpdateMissingKeyReturns404(): void
    {
        Flight::request()->data->setData(['nickname' => '更新後']);

        (new AuthenticatedWriter())->execUpdate($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * DELETE /writers/@id
     */

    public function testExecDeleteRemovesWriter(): void
    {
        $key = $this->createWriter();

        (new AuthenticatedWriter())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame([], WriterModel::detail(HashTools::decodeSqid($key)));
    }

    public function testExecDeleteMissingKeyReturns404(): void
    {
        (new AuthenticatedWriter())->execDelete($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * 認証（POST / PUT / DELETE）
     */

    /**
     * 認証が必要な操作
     *
     * @return array<string, array{0: string, 1: array}>
     */
    public static function writeOperationProvider(): array
    {
        return [
            'POST' => ['execAdd', []],
            'PUT' => ['execUpdate', ['dummy']],
            'DELETE' => ['execDelete', ['dummy']],
        ];
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteWithoutTokenReturns401(string $method, array $args): void
    {
        // Authorizationヘッダーなし（トークン形式不正のためcommonsには問い合わせない）
        (new Writer())->$method(...$args);

        $this->assertSame(401, $this->response()[0]);
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteWhenAuthServerUnreachableReturns502(string $method, array $args): void
    {
        (new UnreachableAuthWriter())->$method(...$args);

        $this->assertSame(502, $this->response()[0]);
    }
}
