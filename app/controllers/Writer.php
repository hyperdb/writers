<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/BaseMethod.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/WriterModel.php";
require_once __DIR__ . "/../validators/WriterValidator.php";

/**
 * ユーザー管理クラス
 */
class Writer extends Base implements BaseMethod
{
    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::group('/writers', function ()
        {
            Flight::route('GET /@id',       [self::class, 'getDetail']);
            Flight::route('PUT /@id',       [self::class, 'execUpdate']);
            Flight::route('DELETE /@id',    [self::class, 'execDelete']);
            Flight::route('GET /',          [self::class, 'getList']);
            Flight::route('POST /',         [self::class, 'execAdd']);
        });
    }

    /**
     * 執筆者一覧の取得
     *
     * @return void
     */
    public function getList(): void
    {
        // クエリパラメータの取得（不正な値は補正済み）
        $paging = $this->getPagingParams();
        // 一覧の取得
        $list = WriterModel::list(
            $paging["page"] * $paging["rows"],
            $paging["rows"]
        );

        // fetchAllの各行はCollectionなので配列に変換してからkeyを付与する
        $rows = array_map(
            fn($row): array => $this->toResponseRow($row->getData()),
            $list
        );

        // 取得件数が0件でもOKにする
        $this->resultJson(
            $rows,
            200,
            $this->route,
            "一覧が取得できました"
        );
    }

    /**
     * 執筆者追加
     *
     * @param string $user_id
     * @param string $nickname
     * @return integer
     */
    public function add(string $user_id, string $nickname): int
    {
        return WriterModel::add(
            $user_id,
            $nickname
        );
    }

    /**
     * 執筆者詳細の取得
     *
     * @param string $sqid_key
     * @return void
     */
    public function getDetail(string $sqid_key): void
    {
        // 詳細の取得
        $writer = WriterModel::detail(HashTools::decodeSqid($sqid_key));

        if (count($writer) > 0)
        {
            $this->resultJson(
                $this->toResponseRow($writer, $sqid_key),
                200,
                $this->route,
                "取得できました"
            );
        }
        else
        {
            // IDを指定しているので取得できなければ404エラー
            $this->resultJson(
                [],
                404,
                $this->route,
                "見つかりませんでした"
            );
        }
    }

    /**
     * ユーザーの更新
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
        // POSTデータ取得
        $nickname = $this->getBodyString("nickname", "");

        // user_idは変更できないため、nicknameのみチェックする
        if ($this->respondIfInvalid((new WriterValidator("", $nickname))->valid(["nickname"])))
        {
            return;
        }
        else
        {
            $id = HashTools::decodeSqid($sqid_key);
            // 同じ値での更新は影響行数が0になるため、存在確認は事前に行う
            if (count(WriterModel::detail($id)) === 0)
            {
                $this->resultJson(
                    [],
                    404,
                    $this->route,
                    "見つかりませんでした"
                );
                return;
            }
            WriterModel::update($id, $nickname);

            $this->resultJson(
                [],
                200,
                $this->route,
                "更新できました"
            );
        }
    }

    /**
     * 執筆者の削除
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
        if (empty($sqid_key))
        {
            // 送信前に確認済みだが念のため入力されていなければBAD REQUEST
            $this->resultJson(
                [],
                400,
                $this->route,
                "パラメーターが正しくありません"
            );
        }
        else
        {
            $id = HashTools::decodeSqid($sqid_key);
            if (count(WriterModel::detail($id)) === 0)
            {
                $this->resultJson([], 404, $this->route, "見つかりませんでした");
                return;
            }
            if (WriterModel::countRelated($id) > 0)
            {
                $this->resultJson(
                    [],
                    409,
                    $this->route,
                    "コンテンツまたはサイトに紐付いているため削除できません"
                );
                return;
            }
            WriterModel::delete($id);
            $this->resultJson(
                [],
                200,
                $this->route,
                "削除できました"
            );
        }
    }

    /**
     * ユーザーの追加
     *
     * @return void
     */
    public function execAdd(): void
    {
        // 認証できなければ中断（レスポンスは送信済み）
        if (!$this->authenticate())
        {
            return;
        }
        // POSTデータ取得
        $user_id = $this->getBodyString("user_id", "");
        $nickname = $this->getBodyString("nickname", "");

        if ($this->respondIfInvalid((new WriterValidator($user_id, $nickname))->valid()))
        {
            return;
        }
        else
        {
            try
            {
                $this->add($user_id, $nickname);
            }
            catch (PDOException $e)
            {
                // user_idのUNIQUE制約違反なら409、それ以外のDBエラーはそのまま上げる
                if (!BaseModel::isDuplicateKey($e))
                {
                    throw $e;
                }
                $this->resultJson(
                    [],
                    409,
                    $this->route,
                    "このユーザーは既に執筆者として登録されています"
                );
                return;
            }
            $id = BaseModel::getInsertId();
            $writer = WriterModel::detail(intval($id));
            // 登録した執筆者情報を返す
            $this->resultJson(
                $this->toResponseRow($writer),
                201,
                $this->route,
                "登録できました"
            );
        }
    }
}
