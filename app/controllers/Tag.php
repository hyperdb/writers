<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/SiteScopedMethod.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/TagModel.php";
require_once __DIR__ . "/../validators/TagValidator.php";

/**
 * タグ管理クラス
 * 仕様は`/documents/api/tags.md`を参照
 */
class Tag extends Base implements SiteScopedMethod
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
        Flight::route('GET /sites/@site/tags',  [self::class, 'getList']);
        Flight::route('POST /sites/@site/tags', [self::class, 'execAdd']);
        Flight::group('/tags', function ()
        {
            Flight::route('GET /@id',       [self::class, 'getDetail']);
            Flight::route('PUT /@id',       [self::class, 'execUpdate']);
            Flight::route('DELETE /@id',    [self::class, 'execDelete']);
        });
    }

    /**
     * サイトのタグ一覧の取得
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
        $list = TagModel::listBySite(
            $siteId,
            $paging["page"] * $paging["rows"],
            $paging["rows"]
        );
        $rows = array_map(
            fn($row): array => $this->toResponseRow($row->getData(), null, self::FOREIGN_KEYS),
            $list
        );

        // 取得件数が0件でもOKにする
        $this->resultJson($rows, 200, $this->route, "一覧が取得できました");
    }

    /**
     * タグ詳細の取得
     *
     * @param string $sqid_key
     * @return void
     */
    public function getDetail(string $sqid_key): void
    {
        $tag = TagModel::detail(HashTools::decodeSqid($sqid_key));

        if (count($tag) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        $this->resultJson(
            $this->toResponseRow($tag, $sqid_key, self::FOREIGN_KEYS),
            200,
            $this->route,
            "取得できました"
        );
    }

    /**
     * サイトにタグを追加
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
        $name = $this->getBodyString("tag", "");
        if ($this->respondIfInvalid((new TagValidator($name))->valid()))
        {
            return;
        }

        try
        {
            TagModel::add($siteId, $name);
        }
        catch (PDOException $e)
        {
            if (!$this->respondIfDuplicateName($e))
            {
                throw $e;
            }
            return;
        }
        $tag = TagModel::detail(intval(BaseModel::getInsertId()));
        $this->resultJson(
            $this->toResponseRow($tag, null, self::FOREIGN_KEYS),
            201,
            $this->route,
            "登録できました"
        );
    }

    /**
     * タグの更新（所属サイトは変更しない）
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
        // 同じ値での更新は影響行数が0になるため、存在確認は事前に行う
        $current = TagModel::detail($id);
        if (count($current) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->isSiteWriter(intval($current["site_id"]))))
        {
            return;
        }
        $name = $this->getBodyString("tag", "");
        if ($this->respondIfInvalid((new TagValidator($name))->valid()))
        {
            return;
        }

        try
        {
            TagModel::update($id, $name);
        }
        catch (PDOException $e)
        {
            if (!$this->respondIfDuplicateName($e))
            {
                throw $e;
            }
            return;
        }
        $this->resultJson([], 200, $this->route, "更新できました");
    }

    /**
     * タグの削除
     * コンテンツに付けられている場合は削除しない
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
        $current = TagModel::detail($id);
        if (count($current) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->isSiteWriter(intval($current["site_id"]))))
        {
            return;
        }
        if (TagModel::countRelated($id) > 0)
        {
            $this->resultJson([], 409, $this->route, "コンテンツに付けられているため削除できません");
            return;
        }

        TagModel::delete($id);
        $this->resultJson([], 200, $this->route, "削除できました");
    }

    /**
     * タグ名のUNIQUE制約違反なら409を返す
     *
     * @param PDOException $e
     * @return boolean 409を返した場合はtrue（それ以外のDBエラーは呼び出し元で再スローする）
     */
    private function respondIfDuplicateName(PDOException $e): bool
    {
        if (!BaseModel::isDuplicateKey($e))
        {
            return false;
        }
        $this->resultJson([], 409, $this->route, "同じ名前のタグが既に登録されています");
        return true;
    }
}
