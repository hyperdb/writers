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
        // 管理者にはuser_idも返す
        [$status, $rows] = $this->fetchAllPages(fn() => (new AuthenticatedWriter())->getList());

        $this->assertSame(200, $status);
        $this->assertContains(self::USER_ID, array_column($rows, 'user_id'));
        foreach ($rows as $row)
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

        // 管理者にはuser_idも返す
        (new AuthenticatedWriter())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame(self::USER_ID, $body['data']['user_id']);
        $this->assertSame(0, $body['data']['is_admin']);
        $this->assertSame($key, $body['data']['key']);
        $this->assertArrayNotHasKey('id', $body['data']);
    }

    /**
     * 管理者以外の取得（トークンなし・一般の執筆者・未登録ユーザー）
     *
     * @return array<string, array{0: bool, 1: string}>
     */
    public static function nonAdminReaderProvider(): array
    {
        return [
            'トークンなし' => [false, ''],
            '一般の執筆者' => [true, self::USER_ID],
            '未登録ユーザー' => [true, '__phpunit_unregistered__'],
        ];
    }

    #[DataProvider('nonAdminReaderProvider')]
    public function testGetByNonAdminHidesUserIdAndIsAdmin(bool $withToken, string $userId): void
    {
        $key = $this->createWriter();
        $this->loginAs($userId);
        $writer = fn(): Writer => $withToken ? new AuthenticatedWriter() : new Writer();

        $writer()->getList();
        [$listStatus, $list] = $this->response();
        Flight::response()->clear();
        $writer()->getDetail($key);
        [$detailStatus, $detail] = $this->response();

        $this->assertSame(200, $listStatus);
        $this->assertSame(200, $detailStatus);
        foreach ([...$list['data'], $detail['data']] as $row)
        {
            $this->assertArrayHasKey('nickname', $row);
            $this->assertArrayNotHasKey('user_id', $row);
            $this->assertArrayNotHasKey('is_admin', $row);
        }
    }

    public function testGetWithInvalidTokenReturns401(): void
    {
        $this->loginAs('');

        (new AuthenticatedWriter())->getList();

        $this->assertSame(401, $this->response()[0]);
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
     * GET /writers/me
     */

    public function testGetMeReturnsLoginWriterWithUserId(): void
    {
        // 管理者以外でも本人の情報なのでuser_idとis_adminを返す
        $key = $this->createWriter();
        $this->loginAs(self::USER_ID);

        (new AuthenticatedWriter())->getMe();
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame($key, $body['data']['key']);
        $this->assertSame(self::USER_ID, $body['data']['user_id']);
        $this->assertSame(0, $body['data']['is_admin']);
        $this->assertArrayNotHasKey('id', $body['data']);
    }

    public function testGetMeByAdminReturnsIsAdmin(): void
    {
        (new AuthenticatedWriter())->getMe();
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame($this->adminKey, $body['data']['key']);
        $this->assertSame(1, $body['data']['is_admin']);
    }

    public function testGetMeByUnregisteredUserReturns404(): void
    {
        $this->loginAs('__phpunit_unregistered__');

        (new AuthenticatedWriter())->getMe();

        $this->assertSame(404, $this->response()[0]);
    }

    public function testGetMeWithoutTokenReturns401(): void
    {
        (new Writer())->getMe();

        $this->assertSame(401, $this->response()[0]);
    }

    public function testGetMeWhenAuthServerUnreachableReturns502(): void
    {
        (new UnreachableAuthWriter())->getMe();

        $this->assertSame(502, $this->response()[0]);
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

    public function testExecAddIgnoresIsAdmin(): void
    {
        Flight::request()->data->setData(['user_id' => self::USER_ID, 'nickname' => 'テスト', 'is_admin' => 1]);

        (new AuthenticatedWriter())->execAdd();

        $this->assertSame(201, $this->response()[0]);
        $this->assertSame(0, WriterModel::findByUserId(self::USER_ID)['is_admin']);
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

    public function testExecUpdateBySelfReturns200(): void
    {
        $key = $this->createWriter();
        $this->loginAs(self::USER_ID);
        Flight::request()->data->setData(['nickname' => '本人が更新']);

        (new AuthenticatedWriter())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame('本人が更新', WriterModel::detail(HashTools::decodeSqid($key))['nickname']);
    }

    public function testExecUpdateOtherWriterReturns403(): void
    {
        $this->createWriter();
        $otherKey = $this->createWriter('__phpunit_other__', '別の執筆者');
        $this->loginAs(self::USER_ID);
        Flight::request()->data->setData(['nickname' => '更新後']);

        (new AuthenticatedWriter())->execUpdate($otherKey);

        $this->assertSame(403, $this->response()[0]);
        $this->assertSame('別の執筆者', WriterModel::detail(HashTools::decodeSqid($otherKey))['nickname']);
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
    public function testWriteByUnregisteredUserReturns403(string $method, array $args): void
    {
        // トークンは有効だが、執筆者として登録されていない
        $this->loginAs('__phpunit_unregistered__');
        $args = $args === [] ? [] : [$this->adminKey];
        Flight::request()->data->setData(['user_id' => self::USER_ID, 'nickname' => 'テスト']);

        (new AuthenticatedWriter())->$method(...$args);

        $this->assertSame(403, $this->response()[0]);
    }

    /**
     * 管理者のみの操作
     *
     * @return array<string, array{0: string}>
     */
    public static function adminOperationProvider(): array
    {
        return [
            'POST' => ['execAdd'],
            'DELETE' => ['execDelete'],
        ];
    }

    #[DataProvider('adminOperationProvider')]
    public function testAdminOperationByWriterReturns403(string $method): void
    {
        $key = $this->createWriter();
        $this->loginAs(self::USER_ID);
        Flight::request()->data->setData(['user_id' => '__phpunit_new__', 'nickname' => 'テスト']);

        (new AuthenticatedWriter())->$method(...($method === 'execAdd' ? [] : [$key]));

        $this->assertSame(403, $this->response()[0]);
        $this->assertSame([], WriterModel::findByUserId('__phpunit_new__'));
        $this->assertNotSame([], WriterModel::detail(HashTools::decodeSqid($key)));
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteWhenAuthServerUnreachableReturns502(string $method, array $args): void
    {
        (new UnreachableAuthWriter())->$method(...$args);

        $this->assertSame(502, $this->response()[0]);
    }
}
