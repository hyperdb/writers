<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/SiteScopedMethod.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/ImageModel.php";
require_once __DIR__ . "/../validators/ImageValidator.php";

/**
 * イメージ管理クラス
 * 仕様は`/documents/api/images.md`を参照
 */
class Image extends Base implements SiteScopedMethod
{
    /** レスポンスで文字列化する参照列 */
    private const FOREIGN_KEYS = ["site_id" => "site_key"];
    /** 画像の公開パス */
    private const PUBLIC_PATH = "/statics/images";

    /** 画像の保存先（テストでは一時ディレクトリに差し替える） */
    public static string $storageDir = __DIR__ . "/../../statics/images";

    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::route('GET /sites/@site/images',  [self::class, 'getList']);
        Flight::route('GET /sites/@site/images/count', [self::class, 'getCount']);
        Flight::route('POST /sites/@site/images', [self::class, 'execAdd']);
        Flight::group('/images', function ()
        {
            Flight::route('GET /@id',       [self::class, 'getDetail']);
            Flight::route('PUT /@id',       [self::class, 'execUpdate']);
            Flight::route('DELETE /@id',    [self::class, 'execDelete']);
        });
    }

    /**
     * サイトのイメージ一覧の取得
     *
     * @param string $site_key
     * @return void
     */
    public function getList(string $site_key): void
    {
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }

        $paging = $this->getPagingParams();
        $list = ImageModel::listBySite(
            $siteId,
            $paging["page"] * $paging["rows"],
            $paging["rows"]
        );
        $rows = array_map(
            fn($row): array => $this->toImageResponseRow($row->getData()),
            $list
        );

        // 取得件数が0件でもOKにする
        $this->resultJson($rows, 200, $this->route, "一覧が取得できました");
    }

    /**
     * サイトのイメージ総件数の取得（取得範囲は一覧と同じ）
     *
     * @param string $site_key
     * @return void
     */
    public function getCount(string $site_key): void
    {
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }

        $count = ImageModel::countBySite($siteId);
        $this->resultJson(["count" => $count], 200, $this->route, "件数が取得できました");
    }

    /**
     * イメージ詳細の取得
     *
     * @param string $sqid_key
     * @return void
     */
    public function getDetail(string $sqid_key): void
    {
        $image = ImageModel::detail(HashTools::decodeSqid($sqid_key));

        if (count($image) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        $this->resultJson($this->toImageResponseRow($image, $sqid_key), 200, $this->route, "取得できました");
    }

    /**
     * サイトにイメージを追加（アップロード）
     *
     * @param string $site_key
     * @return void
     */
    public function execAdd(string $site_key): void
    {
        // 認証できなければ中断（レスポンスは送信済み）
        if (!$this->authenticate())
        {
            return;
        }
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->isSiteWriter($siteId)))
        {
            return;
        }
        $file = $this->getUploadedFile();
        if ($this->respondIfInvalid((new ImageValidator($file))->valid()))
        {
            return;
        }

        $extension = ImageValidator::detectExtension($file["tmp_name"]);
        // ファイルを保存できなければ登録も取り消す
        $id = BaseModel::transaction(function () use ($siteId, $file, $extension): int
        {
            ImageModel::add($siteId, $this->toSourceName($file["name"]), $extension);
            $id = intval(BaseModel::getInsertId());
            $this->storeFile($file["tmp_name"], $this->filePath(HashTools::generateSqid($id), $extension));
            return $id;
        });
        $this->resultJson(
            $this->toImageResponseRow(ImageModel::detail($id)),
            201,
            $this->route,
            "登録できました"
        );
    }

    /**
     * イメージの差し替え（keyと所属サイトは変更しない）
     *
     * @param string $sqid_key
     * @return void
     */
    public function execUpdate(string $sqid_key): void
    {
        // 認証できなければ中断（レスポンスは送信済み）
        if (!$this->authenticate())
        {
            return;
        }
        $id = HashTools::decodeSqid($sqid_key);
        $current = ImageModel::detail($id);
        if (count($current) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->isSiteWriter(intval($current["site_id"]))))
        {
            return;
        }
        $file = $this->getUploadedFile();
        if ($this->respondIfInvalid((new ImageValidator($file))->valid()))
        {
            return;
        }

        $extension = ImageValidator::detectExtension($file["tmp_name"]);
        // 新しいファイルは一時的な名前で保存し、DBの更新後に古いファイルと入れ替える
        $staging = $this->filePath("." . $sqid_key, $extension) . ".tmp";
        $this->storeFile($file["tmp_name"], $staging);
        try
        {
            BaseModel::transaction(function () use ($id, $sqid_key, $file, $extension, $current, $staging): void
            {
                ImageModel::update($id, $this->toSourceName($file["name"]), $extension);
                $this->removeFile($this->filePath($sqid_key, strval($current["extension"])));
                if (!rename($staging, $this->filePath($sqid_key, $extension)))
                {
                    throw new RuntimeException("画像ファイルを差し替えできませんでした");
                }
            });
        }
        finally
        {
            $this->removeFile($staging);
        }
        $this->resultJson(
            $this->toImageResponseRow(ImageModel::detail($id), $sqid_key),
            200,
            $this->route,
            "差し替えできました"
        );
    }

    /**
     * イメージの削除（ファイルも削除する）
     * コンテンツから参照されている場合は削除しない
     *
     * @param string $sqid_key
     * @return void
     */
    public function execDelete(string $sqid_key): void
    {
        // 認証できなければ中断（レスポンスは送信済み）
        if (!$this->authenticate())
        {
            return;
        }

        $id = HashTools::decodeSqid($sqid_key);
        $current = ImageModel::detail($id);
        if (count($current) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->isSiteWriter(intval($current["site_id"]))))
        {
            return;
        }
        if (ImageModel::countRelated($id) > 0)
        {
            $this->resultJson([], 409, $this->route, "コンテンツから参照されているため削除できません");
            return;
        }

        BaseModel::transaction(function () use ($id, $sqid_key, $current): void
        {
            ImageModel::delete($id);
            $this->removeFile($this->filePath($sqid_key, strval($current["extension"])));
        });
        $this->resultJson([], 200, $this->route, "削除できました");
    }

    /**
     * リクエストの項目`image`のファイルを`$_FILES`形式で取得する
     * 未送信・複数ファイルの場合は未送信（`UPLOAD_ERR_NO_FILE`）として扱う
     *
     * @return array{name: string, tmp_name: string, size: int, error: int}
     */
    private function getUploadedFile(): array
    {
        $file = Flight::request()->files["image"] ?? null;
        if (!is_array($file) || !is_string($file["tmp_name"] ?? null))
        {
            return ["name" => "", "tmp_name" => "", "size" => 0, "error" => UPLOAD_ERR_NO_FILE];
        }
        return [
            "name" => strval($file["name"] ?? ""),
            "tmp_name" => $file["tmp_name"],
            "size" => intval($file["size"] ?? 0),
            "error" => intval($file["error"] ?? UPLOAD_ERR_NO_FILE),
        ];
    }

    /**
     * アップロード時のファイル名を保存用に整える（パスを除き、255文字以内にする）
     *
     * @param string $name
     * @return string
     */
    private function toSourceName(string $name): string
    {
        return mb_substr(basename(str_replace("\\", "/", $name)), 0, 255);
    }

    /**
     * 画像ファイルの保存先のパス
     *
     * @param string $name 拡張子を除いたファイル名
     * @param string $extension
     * @return string
     */
    private function filePath(string $name, string $extension): string
    {
        return self::$storageDir . "/" . $name . "." . $extension;
    }

    /**
     * アップロードされたファイルを保存先に移動する
     * `POST`は`move_uploaded_file`、`PUT`（FlightPHPが一時ファイルを作成）は`rename`で移動する
     *
     * @param string $tmpName
     * @param string $path
     * @return void
     * @throws RuntimeException 移動できなかった場合
     */
    private function storeFile(string $tmpName, string $path): void
    {
        $moved = is_uploaded_file($tmpName) ? move_uploaded_file($tmpName, $path) : rename($tmpName, $path);
        if (!$moved)
        {
            throw new RuntimeException("画像ファイルを保存できませんでした");
        }
        // PUT の一時ファイルは 0600 のため、Web サーバーが配信できるようにする
        chmod($path, 0644);
    }

    /**
     * ファイルがあれば削除する
     *
     * @param string $path
     * @return void
     */
    private function removeFile(string $path): void
    {
        if (is_file($path))
        {
            unlink($path);
        }
    }

    /**
     * イメージをレスポンス用の行に変換する（画像のパスを付与する）
     *
     * @param array $row
     * @param ?string $key
     * @return array
     */
    private function toImageResponseRow(array $row, ?string $key = null): array
    {
        $row = $this->toResponseRow($row, $key, self::FOREIGN_KEYS);
        $row["url"] = self::toPublicUrl($row["key"], $row["extension"]);
        return $row;
    }

    /**
     * 画像の公開パス（`/statics/images/<key>.<extension>`）
     *
     * @param string $key
     * @param string $extension
     * @return string
     */
    public static function toPublicUrl(string $key, string $extension): string
    {
        return self::PUBLIC_PATH . "/" . $key . "." . $extension;
    }
}
