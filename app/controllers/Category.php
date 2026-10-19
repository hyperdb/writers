<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/SiteScopedMethod.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/CategoryModel.php";
require_once __DIR__ . "/../validators/CategoryValidator.php";

/**
 * カテゴリー管理クラス
 * 仕様は`/documents/api/categories.md`を参照
 */
class Category extends Base implements SiteScopedMethod
{
    /** レスポンスで文字列化する参照列 */
    private const FOREIGN_KEYS = ["parent_id" => "parent_key", "site_id" => "site_key"];

    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::route('GET /sites/@site/categories',    [self::class, 'getList']);
        Flight::route('POST /sites/@site/categories',   [self::class, 'execAdd']);
        Flight::group('/categories', function ()
        {
            Flight::route('GET /@id',       [self::class, 'getDetail']);
            Flight::route('PUT /@id',       [self::class, 'execUpdate']);
            Flight::route('DELETE /@id',    [self::class, 'execDelete']);
        });
    }

    /**
     * サイトのカテゴリー一覧の取得
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
        $list = CategoryModel::listBySite(
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
     * カテゴリー詳細の取得
     *
     * @param string $sqid_key
     * @return void
     */
    public function getDetail(string $sqid_key): void
    {
        $category = CategoryModel::detail(HashTools::decodeSqid($sqid_key));

        if (count($category) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        $this->resultJson(
            $this->toResponseRow($category, $sqid_key, self::FOREIGN_KEYS),
            200,
            $this->route,
            "取得できました"
        );
    }

    /**
     * サイトにカテゴリーを追加
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
        $input = $this->validateInput($siteId);
        if ($input === null)
        {
            return;
        }

        try
        {
            CategoryModel::add($siteId, $input["parent_id"], $input["category"]);
        }
        catch (PDOException $e)
        {
            if (!$this->respondIfDuplicateName($e))
            {
                throw $e;
            }
            return;
        }
        $category = CategoryModel::detail(intval(BaseModel::getInsertId()));
        $this->resultJson(
            $this->toResponseRow($category, null, self::FOREIGN_KEYS),
            201,
            $this->route,
            "登録できました"
        );
    }

    /**
     * カテゴリーの更新（所属サイトは変更しない）
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
        // 親カテゴリーのチェックに所属サイトが必要なため、存在確認を先に行う
        $current = CategoryModel::detail($id);
        if (count($current) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        $input = $this->validateInput(intval($current["site_id"]), $id);
        if ($input === null)
        {
            return;
        }

        try
        {
            CategoryModel::update($id, $input["parent_id"], $input["category"]);
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
     * カテゴリーの削除
     * 子カテゴリーがある、またはコンテンツから参照されている場合は削除しない
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
        if (count(CategoryModel::detail($id)) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if (CategoryModel::countRelated($id) > 0)
        {
            $this->resultJson(
                [],
                409,
                $this->route,
                "子カテゴリーがある、またはコンテンツから参照されているため削除できません"
            );
            return;
        }

        CategoryModel::delete($id);
        $this->resultJson([], 200, $this->route, "削除できました");
    }

    /**
     * ボディの入力値を検証する（エラー時は400を返す）
     *
     * @param int $siteId 所属サイトのID
     * @param int $selfId 更新対象のID（追加時は0）
     * @return ?array{category: string, parent_id: int} 検証済みの値（エラー時はnull）
     */
    private function validateInput(int $siteId, int $selfId = 0): ?array
    {
        $category = $this->getBodyString("category", "");
        $parentKey = $this->getBodyString("parent", "");

        $errors = (new CategoryValidator($category))->valid();
        $parentId = 0;
        if ($parentKey !== "")
        {
            $parentId = HashTools::decodeSqid($parentKey);
            $parentError = $this->checkParent($siteId, $parentId, $selfId);
            if ($parentError !== "")
            {
                $errors["parent"] = [$parentError];
            }
        }

        if ($this->respondIfInvalid($errors))
        {
            return null;
        }
        return [
            "category" => $category,
            "parent_id" => $parentId,
        ];
    }

    /**
     * 親カテゴリーとして指定できるか確認する
     *
     * @param int $siteId 所属サイトのID
     * @param int $parentId 親に指定されたカテゴリーのID
     * @param int $selfId 更新対象のID（追加時は0）
     * @return string エラーメッセージ（問題なければ空文字）
     */
    private function checkParent(int $siteId, int $parentId, int $selfId): string
    {
        $parent = CategoryModel::detail($parentId);
        // 別サイトのカテゴリーは存在しないものとして扱う
        if (count($parent) === 0 || intval($parent["site_id"]) !== $siteId)
        {
            return "親カテゴリーが見つかりません";
        }
        if ($selfId === 0)
        {
            return "";
        }
        // 親から最上位までたどり、自分自身が現れたら循環になる
        $ancestorId = $parentId;
        while ($ancestorId > 0)
        {
            if ($ancestorId === $selfId)
            {
                return "自分自身または子孫のカテゴリーは親にできません";
            }
            $ancestorId = intval(CategoryModel::detail($ancestorId)["parent_id"] ?? 0);
        }
        return "";
    }

    /**
     * カテゴリー名のUNIQUE制約違反なら409を返す
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
        $this->resultJson([], 409, $this->route, "同じ階層に同じ名前のカテゴリーが既に登録されています");
        return true;
    }
}
