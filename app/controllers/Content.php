<?php

use App\Enums\ContentStatus;
use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/SiteScopedMethod.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/ContentModel.php";
require_once __DIR__ . "/../models/TypeModel.php";
require_once __DIR__ . "/../models/CategoryModel.php";
require_once __DIR__ . "/../models/TagModel.php";
require_once __DIR__ . "/../validators/ContentValidator.php";

/**
 * コンテンツ管理クラス
 * 仕様は`/documents/api/contents.md`を参照
 */
class Content extends Base implements SiteScopedMethod
{
    /** レスポンスで文字列化する参照列 */
    private const FOREIGN_KEYS = [
        "type_id" => "type_key",
        "category_id" => "category_key",
        "site_id" => "site_key",
        "writer_id" => "writer_key",
    ];

    /** 一覧の絞り込み（クエリパラメーター名 => 列名） */
    private const FILTERS = [
        "type" => "type_id",
        "category" => "category_id",
        "writer" => "writer_id",
        "tag" => "tag_id",
    ];

    /** 新着一覧の取得件数の既定値・上限 */
    private const LATEST_LIMIT = 10;

    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::route('GET /sites/@site/contents',  [self::class, 'getList']);
        Flight::route('GET /sites/@site/contents/@type', [self::class, 'getList']);
        Flight::route('GET /sites/@site/contents/@type/latest', [self::class, 'getLatest']);
        Flight::route('POST /sites/@site/contents', [self::class, 'execAdd']);
        Flight::group('/contents', function ()
        {
            Flight::route('GET /@id',       [self::class, 'getDetail']);
            Flight::route('PUT /@id',       [self::class, 'execUpdate']);
            Flight::route('DELETE /@id',    [self::class, 'execDelete']);
        });
    }

    /**
     * サイトのコンテンツ一覧の取得
     * サイトの執筆者（管理者を含む）は下書き・公開予約中も含め、それ以外は公開中のみ
     *
     * @param string $site_key
     * @param ?string $type_key パスで指定されたタイプのkey（指定時はクエリの`type`より優先）
     * @return void
     */
    public function getList(string $site_key, ?string $type_key = null): void
    {
        $authenticated = $this->authenticateIfToken();
        if ($authenticated === null)
        {
            return;
        }
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }
        $includeUnpublished = $authenticated && $this->isSiteWriter($siteId);

        $filters = [];
        foreach (self::FILTERS as $param => $column)
        {
            $key = $this->getStringParam($param);
            if ($key !== "")
            {
                // 復元できないkeyは、どのデータにも一致しない値で絞り込む（0は「未定」に一致してしまうため）
                $id = HashTools::decodeSqid($key);
                $filters[$column] = $id > 0 ? $id : -1;
            }
        }
        if ($type_key !== null)
        {
            $typeId = HashTools::decodeSqid($type_key);
            if ($typeId === 0 || count(TypeModel::detail($typeId)) === 0)
            {
                $this->resultJson([], 404, $this->route, "タイプが見つかりませんでした");
                return;
            }
            $filters["type_id"] = $typeId;
        }

        $paging = $this->getPagingParams();
        $list = ContentModel::listBySite(
            $siteId,
            $filters,
            $includeUnpublished,
            $paging["page"] * $paging["rows"],
            $paging["rows"]
        );
        $rows = array_map(fn($row): array => $row->getData(), $list);

        // 取得件数が0件でもOKにする
        $this->resultJson($this->toResponseRows($rows), 200, $this->route, "一覧が取得できました");
    }

    /**
     * サイトの指定タイプの新着コンテンツ一覧の取得（公開中のみ、公開日時の新しい順）
     * 認証は不要（トークンが添付されていても認証しない）
     *
     * @param string $site_key
     * @param string $type_key
     * @return void
     */
    public function getLatest(string $site_key, string $type_key): void
    {
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }
        $typeId = HashTools::decodeSqid($type_key);
        if ($typeId === 0 || count(TypeModel::detail($typeId)) === 0)
        {
            $this->resultJson([], 404, $this->route, "タイプが見つかりませんでした");
            return;
        }

        // 0以下・数値でない値は既定値、上限を超える値は上限として扱う
        $limit = $this->getIntParam("limit", self::LATEST_LIMIT);
        $list = ContentModel::listBySite(
            $siteId,
            ["type_id" => $typeId],
            false,
            0,
            $limit > 0 ? min($limit, self::LATEST_LIMIT) : self::LATEST_LIMIT
        );
        $rows = array_map(fn($row): array => $row->getData(), $list);

        $this->resultJson($this->toResponseRows($rows), 200, $this->route, "一覧が取得できました");
    }

    /**
     * コンテンツ詳細の取得
     * 公開中でないものは、サイトの執筆者（管理者を含む）以外には404とする
     *
     * @param string $sqid_key
     * @return void
     */
    public function getDetail(string $sqid_key): void
    {
        $authenticated = $this->authenticateIfToken();
        if ($authenticated === null)
        {
            return;
        }
        $id = HashTools::decodeSqid($sqid_key);
        // 下書きを見られるかは所属サイトで決まるため、まず下書きも含めて取得する
        $content = ContentModel::detail($id, true);
        if (count($content) > 0 && !($authenticated && $this->isSiteWriter(intval($content["site_id"]))))
        {
            $content = ContentModel::detail($id, false);
        }

        if (count($content) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        $this->resultJson($this->toResponseRows([$content], $sqid_key)[0], 200, $this->route, "取得できました");
    }

    /**
     * サイトにコンテンツを追加
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
        $input = $this->validateInput($siteId);
        if ($input === null)
        {
            return;
        }

        // 執筆者はトークンの持ち主とする
        $id = ContentModel::create(
            $input["values"] + ["site_id" => $siteId, "writer_id" => $this->loginWriterId()],
            $input["tag_ids"]
        );
        $content = ContentModel::detail($id, true);
        $this->resultJson($this->toResponseRows([$content])[0], 201, $this->route, "登録できました");
    }

    /**
     * コンテンツの更新（所属サイトと執筆者は変更しない）
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
        // カテゴリー・タグのチェックに所属サイトが必要なため、存在確認を先に行う
        $current = ContentModel::detail($id, true);
        if (count($current) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->canEdit($current)))
        {
            return;
        }
        $input = $this->validateInput(intval($current["site_id"]));
        if ($input === null)
        {
            return;
        }

        ContentModel::update($id, $input["values"], $input["tag_ids"]);
        $this->resultJson([], 200, $this->route, "更新できました");
    }

    /**
     * コンテンツの削除（タグの紐付けも削除する）
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
        $current = ContentModel::detail($id, true);
        if (count($current) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if ($this->respondIfForbidden($this->canEdit($current)))
        {
            return;
        }

        ContentModel::delete($id);
        $this->resultJson([], 200, $this->route, "削除できました");
    }

    /**
     * ログイン中のユーザーがコンテンツを更新・削除できるか
     * 管理者は常に可、それ以外は本人のコンテンツで、かつサイトの執筆者である場合のみ
     *
     * @param array $content
     * @return boolean
     */
    private function canEdit(array $content): bool
    {
        if ($this->isAdmin())
        {
            return true;
        }
        return intval($content["writer_id"]) === $this->loginWriterId()
            && $this->isSiteWriter(intval($content["site_id"]));
    }

    /**
     * ボディの入力値を検証する（エラー時は400を返す）
     *
     * @param int $siteId 所属サイトのID
     * @return ?array{values: array, tag_ids: array<int,int>} 検証済みの値（エラー時はnull）
     */
    private function validateInput(int $siteId): ?array
    {
        $title = $this->getBodyString("title", "");
        $body = $this->getBodyString("body", "");
        $status = $this->getBodyString("status", ContentStatus::Draft->value);
        $publishedAt = $this->getBodyString("published_at", "");

        $errors = (new ContentValidator($title, $body, $publishedAt))->valid();
        if (ContentStatus::tryFrom($status) === null)
        {
            $errors["status"] = ["公開状態はdraftまたはpublishedで指定してください"];
        }

        // 参照先のチェック（空文字なら未定）
        $typeId = $this->findReferenceId("type", "タイプ", fn(int $id): bool => count(TypeModel::detail($id)) > 0, $errors);
        $categoryId = $this->findReferenceId(
            "category",
            "カテゴリー",
            fn(int $id): bool => intval(CategoryModel::detail($id)["site_id"] ?? 0) === $siteId,
            $errors
        );
        $tagIds = $this->findTagIds($siteId, $errors);

        if ($this->respondIfInvalid($errors))
        {
            return null;
        }
        return [
            "values" => [
                "title" => $title,
                "body" => $body,
                "status" => $status,
                "published_at" => $publishedAt === "" ? null : $publishedAt,
                "type_id" => $typeId,
                "category_id" => $categoryId,
            ],
            "tag_ids" => $tagIds,
        ];
    }

    /**
     * ボディで指定された参照先のkeyをIDに変換する（存在しなければエラーを追加）
     *
     * @param string $param ボディの項目名
     * @param string $label エラーメッセージに使う名称
     * @param callable(int): bool $exists 参照先として有効か判定する関数
     * @param array $errors エラーの追加先
     * @return integer 未指定・無効な場合は0
     */
    private function findReferenceId(string $param, string $label, callable $exists, array &$errors): int
    {
        $key = $this->getBodyString($param, "");
        if ($key === "")
        {
            return 0;
        }
        $id = HashTools::decodeSqid($key);
        if ($id === 0 || !$exists($id))
        {
            $errors[$param] = ["{$label}が見つかりません"];
            return 0;
        }
        return $id;
    }

    /**
     * ボディで指定されたタグのkeyの配列をIDの配列に変換する（同じサイトのタグに限る）
     *
     * @param int $siteId
     * @param array $errors エラーの追加先
     * @return array<int,int>
     */
    private function findTagIds(int $siteId, array &$errors): array
    {
        $keys = $this->getBodyArray("tags");
        if ($keys === null)
        {
            $errors["tags"] = ["タグはkeyの配列で指定してください"];
            return [];
        }
        $tagIds = [];
        foreach ($keys as $key)
        {
            $id = HashTools::decodeSqid($key);
            if ($id === 0 || intval(TagModel::detail($id)["site_id"] ?? 0) !== $siteId)
            {
                $errors["tags"] = ["存在しないタグが含まれています"];
                return [];
            }
            $tagIds[] = $id;
        }
        return array_values(array_unique($tagIds));
    }

    /**
     * レスポンス用の行に変換する（参照列をkeyにし、タグを付与する）
     *
     * @param array<int,array> $rows
     * @param ?string $key 1件のみ変換する場合に、keyとして返す値
     * @return array<int,array>
     */
    private function toResponseRows(array $rows, ?string $key = null): array
    {
        $tags = ContentModel::tagsOf(array_map(fn(array $row): int => intval($row["id"]), $rows));
        return array_map(
            function (array $row) use ($tags, $key): array
            {
                $contentTags = $tags[intval($row["id"])] ?? [];
                $response = $this->toResponseRow($row, $key, self::FOREIGN_KEYS);
                $response["tags"] = array_map(
                    fn(array $tag): array => ["key" => HashTools::generateSqid($tag["id"]), "tag" => $tag["tag"]],
                    $contentTags
                );
                return $response;
            },
            $rows
        );
    }
}
