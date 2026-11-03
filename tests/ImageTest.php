<?php

use App\Shared\Utils\HashTools;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * イメージAPI（Image）のテスト
 * 仕様は`/documents/api/images.md`を参照
 * 画像ファイルは一時ディレクトリに保存する（`statics/images`には保存しない）
 */
class ImageTest extends DbTestCase
{
    /** 1x1のPNG */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    /** 1x1のGIF */
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** テスト用サイトのkey（setUpで登録） */
    private string $siteKey;
    /** 元の画像の保存先 */
    private string $originalStorageDir;

    protected function setUp(): void
    {
        parent::setUp();
        Flight::request()->files->setData([]);
        $this->siteKey = $this->createSite();
        $this->originalStorageDir = Image::$storageDir;
        Image::$storageDir = sys_get_temp_dir() . '/writers-images-' . uniqid();
        mkdir(Image::$storageDir);
    }

    protected function tearDown(): void
    {
        foreach ($this->storedFiles() as $name)
        {
            unlink(Image::$storageDir . '/' . $name);
        }
        rmdir(Image::$storageDir);
        Image::$storageDir = $this->originalStorageDir;
        // 保存されずに残ったアップロードの一時ファイルを削除する
        $tmpName = Flight::request()->files['image']['tmp_name'] ?? '';
        if ($tmpName !== '' && is_file($tmpName))
        {
            unlink($tmpName);
        }
        Flight::request()->files->setData([]);
        parent::tearDown();
    }

    /**
     * 保存先にあるファイル名の一覧（一時ファイルを含む）
     *
     * @return array<int,string>
     */
    private function storedFiles(): array
    {
        return array_values(array_diff(scandir(Image::$storageDir), ['.', '..']));
    }

    /**
     * 項目`image`にファイルがアップロードされた状態にする
     *
     * @param string $content ファイルの中身
     * @param string $name アップロード時のファイル名
     * @param int $error アップロードのエラーコード
     * @return void
     */
    private function upload(string $content, string $name = 'photo.png', int $error = UPLOAD_ERR_OK): void
    {
        $tmpName = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($tmpName, $content);
        Flight::request()->files->setData([
            'image' => [
                'name' => $name,
                'type' => 'application/octet-stream',
                'size' => strlen($content),
                'tmp_name' => $tmpName,
                'error' => $error,
            ],
        ]);
    }

    /**
     * テスト用のイメージを登録し（ファイルも作成）、keyを返す
     *
     * @param ?string $siteKey 所属サイトのkey（省略時はテスト用サイト）
     * @return string
     */
    private function createImage(?string $siteKey = null): string
    {
        ImageModel::add(HashTools::decodeSqid($siteKey ?? $this->siteKey), 'before.png', 'png');
        $key = HashTools::generateSqid(intval(BaseModel::getInsertId()));
        file_put_contents($this->filePath($key, 'png'), base64_decode(self::PNG));
        return $key;
    }

    /**
     * 保存先の画像ファイルのパス
     *
     * @param string $key
     * @param string $extension
     * @return string
     */
    private function filePath(string $key, string $extension): string
    {
        return Image::$storageDir . '/' . $key . '.' . $extension;
    }

    /*
     * GET /sites/@site/images
     */

    public function testGetListReturnsOnlySiteImages(): void
    {
        $key = $this->createImage();
        $this->createImage($this->createSite('https://other.example.com'));

        (new Image())->getList($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame([$key], array_column($body['data'], 'key'));
        $this->assertArrayNotHasKey('site_id', $body['data'][0]);
        $this->assertArrayHasKey('site_key', $body['data'][0]);
    }

    public function testGetListMissingSiteReturns404(): void
    {
        (new Image())->getList($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * GET /images/@id
     */

    public function testGetDetailReturnsImageWithUrl(): void
    {
        $key = $this->createImage();

        (new Image())->getDetail($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame($key, $body['data']['key']);
        $this->assertSame("/statics/images/{$key}.png", $body['data']['url']);
    }

    public function testGetDetailMissingKeyReturns404(): void
    {
        (new Image())->getDetail($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /*
     * POST /sites/@site/images
     */

    public function testExecAddStoresFile(): void
    {
        $this->upload(base64_decode(self::PNG), '写真.png');

        (new AuthenticatedImage())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame('写真.png', $body['data']['source_name']);
        $this->assertSame('png', $body['data']['extension']);
        $this->assertFileExists($this->filePath($body['data']['key'], 'png'));
    }

    public function testExecAddUsesDetectedExtension(): void
    {
        // ファイル名の拡張子ではなく中身（GIF）で拡張子を決める
        $this->upload(base64_decode(self::GIF), 'photo.png');

        (new AuthenticatedImage())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(201, $status);
        $this->assertSame('gif', $body['data']['extension']);
        $this->assertFileExists($this->filePath($body['data']['key'], 'gif'));
    }

    public function testExecAddMissingSiteReturns404(): void
    {
        $this->upload(base64_decode(self::PNG));

        (new AuthenticatedImage())->execAdd($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    /**
     * 不正なアップロード
     *
     * @return array<string, array{0: ?string, 1: int}>
     */
    public static function invalidUploadProvider(): array
    {
        return [
            '未送信' => [null, UPLOAD_ERR_OK],
            '画像でない' => ['<?php echo 1;', UPLOAD_ERR_OK],
            'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', UPLOAD_ERR_OK],
            'サーバーの上限超過' => ['', UPLOAD_ERR_INI_SIZE],
        ];
    }

    #[DataProvider('invalidUploadProvider')]
    public function testExecAddInvalidUploadReturns400(?string $content, int $error): void
    {
        if ($content !== null)
        {
            $this->upload($content, 'photo.png', $error);
        }

        (new AuthenticatedImage())->execAdd($this->siteKey);
        [$status, $body] = $this->response();

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('image', $body['data']);
        $this->assertSame([], $this->storedFiles());
    }

    public function testExecAddTooLargeReturns400(): void
    {
        // 中身は小さいが、サイズが上限を超えている扱いにする
        $this->upload(base64_decode(self::PNG));
        $file = Flight::request()->files['image'];
        $file['size'] = ImageValidator::MAX_FILE_SIZE + 1;
        Flight::request()->files->setData(['image' => $file]);

        (new AuthenticatedImage())->execAdd($this->siteKey);

        $this->assertSame(400, $this->response()[0]);
    }

    /*
     * PUT /images/@id
     */

    public function testExecUpdateReplacesFileWithSameKey(): void
    {
        $key = $this->createImage();
        $this->upload(base64_decode(self::GIF), 'after.gif');

        (new AuthenticatedImage())->execUpdate($key);
        [$status, $body] = $this->response();

        $this->assertSame(200, $status);
        $this->assertSame($key, $body['data']['key']);
        $this->assertSame('after.gif', $body['data']['source_name']);
        $this->assertSame("/statics/images/{$key}.gif", $body['data']['url']);
        $this->assertFileDoesNotExist($this->filePath($key, 'png'));
        $this->assertFileExists($this->filePath($key, 'gif'));
        // 一時ファイルが残っていない
        $this->assertSame(["{$key}.gif"], $this->storedFiles());
    }

    public function testExecUpdateSameExtensionReplacesFile(): void
    {
        $key = $this->createImage();
        file_put_contents($this->filePath($key, 'png'), 'old');
        $this->upload(base64_decode(self::PNG), 'after.png');

        (new AuthenticatedImage())->execUpdate($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame(base64_decode(self::PNG), file_get_contents($this->filePath($key, 'png')));
    }

    public function testExecUpdateMissingKeyReturns404(): void
    {
        $this->upload(base64_decode(self::PNG));

        (new AuthenticatedImage())->execUpdate($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecUpdateInvalidUploadKeepsFile(): void
    {
        $key = $this->createImage();
        $this->upload('not image');

        (new AuthenticatedImage())->execUpdate($key);

        $this->assertSame(400, $this->response()[0]);
        $this->assertFileExists($this->filePath($key, 'png'));
        $this->assertSame('before.png', ImageModel::detail(HashTools::decodeSqid($key))['source_name']);
    }

    /*
     * DELETE /images/@id
     */

    public function testExecDeleteRemovesImageAndFile(): void
    {
        $key = $this->createImage();

        (new AuthenticatedImage())->execDelete($key);

        $this->assertSame(200, $this->response()[0]);
        $this->assertSame([], ImageModel::detail(HashTools::decodeSqid($key)));
        $this->assertFileDoesNotExist($this->filePath($key, 'png'));
    }

    public function testExecDeleteMissingKeyReturns404(): void
    {
        (new AuthenticatedImage())->execDelete($this->missingKey());

        $this->assertSame(404, $this->response()[0]);
    }

    public function testExecDeleteReferencedByContentReturns409(): void
    {
        $key = $this->createImage();
        BaseModel::query(
            "INSERT INTO content_images (content_id, image_id) VALUES (1, ?)",
            [HashTools::decodeSqid($key)]
        );

        (new AuthenticatedImage())->execDelete($key);

        $this->assertSame(409, $this->response()[0]);
        $this->assertFileExists($this->filePath($key, 'png'));
    }

    /*
     * 認証
     */

    public function testWriteWithoutTokenReturns401(): void
    {
        $this->upload(base64_decode(self::PNG));

        (new Image())->execAdd($this->siteKey);

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
        $key = $this->createImage();
        $this->joinSite($this->siteKey, $this->registerWriter('__phpunit_writer__'));
        $this->loginAs('__phpunit_writer__');
        $this->upload(base64_decode(self::PNG));

        (new AuthenticatedImage())->$method(...($method === 'execAdd' ? [$this->siteKey] : [$key]));

        $expected = ['execAdd' => 201, 'execUpdate' => 200, 'execDelete' => 200];
        $this->assertSame($expected[$method], $this->response()[0]);
    }

    #[DataProvider('writeOperationProvider')]
    public function testWriteByOtherSiteWriterReturns403(string $method): void
    {
        $key = $this->createImage();
        $otherSiteKey = $this->createSite('https://other.example.com');
        $this->joinSite($otherSiteKey, $this->registerWriter('__phpunit_writer__'));
        $this->loginAs('__phpunit_writer__');
        $this->upload(base64_decode(self::PNG));

        (new AuthenticatedImage())->$method(...($method === 'execAdd' ? [$this->siteKey] : [$key]));

        $this->assertSame(403, $this->response()[0]);
        $this->assertFileExists($this->filePath($key, 'png'));
    }
}
