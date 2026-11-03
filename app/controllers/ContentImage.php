<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/Image.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/ContentModel.php";
require_once __DIR__ . "/../models/ImageModel.php";
require_once __DIR__ . "/../models/ContentImageModel.php";

/**
 * コンテンツイメージ（コンテンツとイメージの紐付け）管理クラス
 * 仕様は`/documents/api/content_images.md`を参照
 * 更新がないため`BaseMethod` / `SiteScopedMethod`は実装しない
 */
class ContentImage extends Base
{
    /** レスポンスで文字列化する参照列 */
    private const FOREIGN_KEYS = ["site_id" => "site_key"];

    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::route('GET /contents/@id/images',               [self::class, 'getList']);
        Flight::route('POST /contents/@id/images',              [self::class, 'execAdd']);
        Flight::route('DELETE /contents/@id/images/@image',     [self::class, 'execDelete']);
    }

    /**
     * コンテンツに紐付いたイメージの一覧の取得
     * 公開中でないコンテンツは、サイトの執筆者（管理者を含む）以外には404とする（`GET /contents/@id`と同じ）
     *
     * @param string $content_key
     * @return void
     */
    public function getList(string $content_key): void
    {
        $authenticated = $this->authenticateIfToken();
        if ($authenticated === null)
        {
            return;
        }
        $id = HashTools::decodeSqid($content_key);
        $content = ContentModel::detail($id, true);
        if (count($content) > 0 && !($authenticated && $this->isSiteWriter(intval($content["site_id"]))))
        {
            $content = ContentModel::detail($id, false);
        }
        if (count($content) === 0)
        {
            $this->resultJson([], 404, $this->route, "コンテンツが見つかりませんでした");
            return;
        }

        $paging = $this->getPagingParams();
        $list = ContentImageModel::listByContent(
            $id,
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
     * コンテンツにイメージを紐付ける（同じサイトのイメージのみ）
     *
     * @param string $content_key
     * @return void
     */
    public function execAdd(string $content_key): void
    {
        // 認証できなければ中断（レスポンスは送信済み）
        if (!$this->authenticate())
        {
            return;
        }
        $id = HashTools::decodeSqid($content_key);
        $content = ContentModel::detail($id, true);
        if (count($content) === 0)
        {
            $this->resultJson([], 404, $this->route, "コンテンツが見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->canEditContent($content)))
        {
            return;
        }

        $imageKey = $this->getBodyString("image", "");
        $imageId = HashTools::decodeSqid($imageKey);
        $image = $imageId > 0 ? ImageModel::detail($imageId) : [];
        // 別のサイトのイメージは存在しないものとして扱う
        if (count($image) === 0 || intval($image["site_id"]) !== intval($content["site_id"]))
        {
            $message = $imageKey === "" ? "イメージは必須項目です" : "イメージが見つかりません";
            $this->respondIfInvalid(["image" => [$message]]);
            return;
        }

        try
        {
            ContentImageModel::add($id, $imageId);
        }
        catch (PDOException $e)
        {
            // 主キー（content_id, image_id）の重複なら409、それ以外のDBエラーはそのまま上げる
            if (!BaseModel::isDuplicateKey($e))
            {
                throw $e;
            }
            $this->resultJson([], 409, $this->route, "このイメージは既にコンテンツに紐付いています");
            return;
        }
        $this->resultJson($this->toImageResponseRow($image, $imageKey), 201, $this->route, "登録できました");
    }

    /**
     * コンテンツとイメージの紐付けを外す（イメージは削除しない）
     *
     * @param string $content_key
     * @param string $image_key
     * @return void
     */
    public function execDelete(string $content_key, string $image_key): void
    {
        // 認証できなければ中断（レスポンスは送信済み）
        if (!$this->authenticate())
        {
            return;
        }
        $id = HashTools::decodeSqid($content_key);
        $content = ContentModel::detail($id, true);
        if (count($content) === 0)
        {
            $this->resultJson([], 404, $this->route, "コンテンツが見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->canEditContent($content)))
        {
            return;
        }

        if (ContentImageModel::delete($id, HashTools::decodeSqid($image_key)) === 0)
        {
            $this->resultJson([], 404, $this->route, "紐付けが見つかりませんでした");
            return;
        }
        $this->resultJson([], 200, $this->route, "削除できました");
    }

    /**
     * イメージをレスポンス用の行に変換する（イメージAPIと同じ項目）
     *
     * @param array $row
     * @param ?string $key
     * @return array
     */
    private function toImageResponseRow(array $row, ?string $key = null): array
    {
        $row = $this->toResponseRow($row, $key, self::FOREIGN_KEYS);
        $row["url"] = Image::toPublicUrl($row["key"], $row["extension"]);
        return $row;
    }
}
