<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/BaseMethod.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/SiteModel.php";
require_once __DIR__ . "/../validators/SiteValidator.php";

/**
 * サイト管理クラス
 * 仕様は`/documents/api/sites.md`を参照
 */
class Site extends Base implements BaseMethod
{
    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::group('/sites', function ()
        {
            Flight::route('GET /@id',       [self::class, 'getDetail']);
            Flight::route('PUT /@id',       [self::class, 'execUpdate']);
            Flight::route('DELETE /@id',    [self::class, 'execDelete']);
            Flight::route('GET /',          [self::class, 'getList']);
            Flight::route('POST /',         [self::class, 'execAdd']);
        });
    }

    /**
     * サイト一覧の取得
     *
     * @return void
     */
    public function getList(): void
    {
        // クエリパラメータの取得（不正な値は補正済み）
        $paging = $this->getPagingParams();
        $list = SiteModel::list(
            $paging["page"] * $paging["rows"],
            $paging["rows"]
        );
        // fetchAllの各行はCollectionなので配列に変換してからkeyを付与する
        $rows = array_map(
            fn($row): array => $this->toResponseRow($row->getData()),
            $list
        );

        // 取得件数が0件でもOKにする
        $this->resultJson($rows, 200, $this->route, "一覧が取得できました");
    }

    /**
     * サイト詳細の取得
     *
     * @param string $sqid_key
     * @return void
     */
    public function getDetail(string $sqid_key): void
    {
        $site = SiteModel::detail(HashTools::decodeSqid($sqid_key));

        if (count($site) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        $this->resultJson($this->toResponseRow($site, $sqid_key), 200, $this->route, "取得できました");
    }

    /**
     * サイトの追加
     *
     * @return void
     */
    public function execAdd(): void
    {
        // 認証できない・管理者でなければ中断（レスポンスは送信済み）
        if (!$this->authenticate() || $this->respondIfForbidden($this->isAdmin()))
        {
            return;
        }
        $input = $this->validateInput();
        if ($input === null)
        {
            return;
        }

        try
        {
            SiteModel::add($input["url"], $input["name"]);
        }
        catch (PDOException $e)
        {
            if (!$this->respondIfDuplicateUrl($e))
            {
                throw $e;
            }
            return;
        }
        $site = SiteModel::detail(intval(BaseModel::getInsertId()));
        $this->resultJson($this->toResponseRow($site), 201, $this->route, "登録できました");
    }

    /**
     * サイトの更新
     *
     * @param string $sqid_key
     * @return void
     */
    public function execUpdate(string $sqid_key): void
    {
        // 認証できない・管理者でなければ中断（レスポンスは送信済み）
        if (!$this->authenticate() || $this->respondIfForbidden($this->isAdmin()))
        {
            return;
        }
        $input = $this->validateInput();
        if ($input === null)
        {
            return;
        }

        $id = HashTools::decodeSqid($sqid_key);
        // 同じ値での更新は影響行数が0になるため、存在確認は事前に行う
        if (count(SiteModel::detail($id)) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }

        try
        {
            SiteModel::update($id, $input["url"], $input["name"]);
        }
        catch (PDOException $e)
        {
            if (!$this->respondIfDuplicateUrl($e))
            {
                throw $e;
            }
            return;
        }
        $this->resultJson([], 200, $this->route, "更新できました");
    }

    /**
     * サイトの削除
     * 紐付くデータが残っている場合は削除しない
     *
     * @param string $sqid_key
     * @return void
     */
    public function execDelete(string $sqid_key): void
    {
        // 認証できない・管理者でなければ中断（レスポンスは送信済み）
        if (!$this->authenticate() || $this->respondIfForbidden($this->isAdmin()))
        {
            return;
        }

        $id = HashTools::decodeSqid($sqid_key);
        if (count(SiteModel::detail($id)) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if (SiteModel::countRelated($id) > 0)
        {
            $this->resultJson(
                [],
                409,
                $this->route,
                "コンテンツ・カテゴリー・タグ・執筆者が紐付いているため削除できません"
            );
            return;
        }

        SiteModel::delete($id);
        $this->resultJson([], 200, $this->route, "削除できました");
    }

    /**
     * ボディの入力値を検証する（エラー時は400を返す）
     *
     * @return ?array{url: string, name: string} 検証済みの値（エラー時はnull）
     */
    private function validateInput(): ?array
    {
        $url = $this->getBodyString("url", "");
        $name = $this->getBodyString("name", "");

        if ($this->respondIfInvalid((new SiteValidator($url, $name))->valid()))
        {
            return null;
        }
        return [
            "url" => SiteValidator::normalizeUrl($url),
            "name" => $name,
        ];
    }

    /**
     * URLのUNIQUE制約違反なら409を返す
     *
     * @param PDOException $e
     * @return boolean 409を返した場合はtrue（それ以外のDBエラーは呼び出し元で再スローする）
     */
    private function respondIfDuplicateUrl(PDOException $e): bool
    {
        if (!BaseModel::isDuplicateKey($e))
        {
            return false;
        }
        $this->resultJson([], 409, $this->route, "このURLのサイトは既に登録されています");
        return true;
    }
}
