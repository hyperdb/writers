<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/WriterModel.php";
require_once __DIR__ . "/../models/SiteWriterModel.php";

/**
 * サイト執筆者（サイトと執筆者の紐付け）管理クラス
 * 仕様は`/documents/api/site_writers.md`を参照
 * 更新がないため`BaseMethod` / `SiteScopedMethod`は実装しない
 */
class SiteWriter extends Base
{
    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::route('GET /sites/@site/writers',               [self::class, 'getList']);
        Flight::route('POST /sites/@site/writers',              [self::class, 'execAdd']);
        Flight::route('DELETE /sites/@site/writers/@writer',    [self::class, 'execDelete']);
    }

    /**
     * サイトに紐付いた執筆者の一覧の取得
     *
     * @param string $site_key
     * @return void
     */
    public function getList(string $site_key): void
    {
        // トークンがあれば認証する（管理者にはuser_idなどを返すため）
        if ($this->authenticateIfToken() === null)
        {
            return;
        }
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }

        $paging = $this->getPagingParams();
        $list = SiteWriterModel::listBySite(
            $siteId,
            $paging["page"] * $paging["rows"],
            $paging["rows"]
        );
        $rows = array_map(
            fn($row): array => $this->toWriterResponseRow($row->getData()),
            $list
        );

        // 取得件数が0件でもOKにする
        $this->resultJson($rows, 200, $this->route, "一覧が取得できました");
    }

    /**
     * サイトに執筆者を紐付ける
     *
     * @param string $site_key
     * @return void
     */
    public function execAdd(string $site_key): void
    {
        // 認証できない・管理者でなければ中断（レスポンスは送信済み）
        if (!$this->authenticate() || $this->respondIfForbidden($this->isAdmin()))
        {
            return;
        }
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }

        $writerKey = $this->getBodyString("writer", "");
        $writerId = HashTools::decodeSqid($writerKey);
        $writer = $writerId > 0 ? WriterModel::detail($writerId) : [];
        if (count($writer) === 0)
        {
            $message = $writerKey === "" ? "執筆者は必須項目です" : "執筆者が見つかりません";
            $this->respondIfInvalid(["writer" => [$message]]);
            return;
        }

        try
        {
            SiteWriterModel::add($siteId, $writerId);
        }
        catch (PDOException $e)
        {
            // 主キー（site_id, writer_id）の重複なら409、それ以外のDBエラーはそのまま上げる
            if (!BaseModel::isDuplicateKey($e))
            {
                throw $e;
            }
            $this->resultJson([], 409, $this->route, "この執筆者は既にサイトに紐付いています");
            return;
        }
        $this->resultJson($this->toWriterResponseRow($writer), 201, $this->route, "登録できました");
    }

    /**
     * サイトと執筆者の紐付けを外す（執筆者のコンテンツは削除しない）
     *
     * @param string $site_key
     * @param string $writer_key
     * @return void
     */
    public function execDelete(string $site_key, string $writer_key): void
    {
        // 認証できない・管理者でなければ中断（レスポンスは送信済み）
        if (!$this->authenticate() || $this->respondIfForbidden($this->isAdmin()))
        {
            return;
        }
        $siteId = $this->findSiteId($site_key);
        if ($siteId === 0)
        {
            $this->resultJson([], 404, $this->route, "サイトが見つかりませんでした");
            return;
        }

        if (SiteWriterModel::delete($siteId, HashTools::decodeSqid($writer_key)) === 0)
        {
            $this->resultJson([], 404, $this->route, "紐付けが見つかりませんでした");
            return;
        }
        $this->resultJson([], 200, $this->route, "削除できました");
    }
}
