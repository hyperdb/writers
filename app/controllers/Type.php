<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/Base.php";
require_once __DIR__ . "/BaseMethod.php";
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/TypeModel.php";
require_once __DIR__ . "/../validators/TypeValidator.php";

/**
 * コンテンツタイプ管理クラス
 * 仕様は`/documents/api/types.md`を参照
 */
class Type extends Base implements BaseMethod
{
    /**
     * ルーティング
     *
     * @return void
     */
    public static function registerRoutes(): void
    {
        Flight::group('/types', function ()
        {
            Flight::route('GET /@id',       [self::class, 'getDetail']);
            Flight::route('PUT /@id',       [self::class, 'execUpdate']);
            Flight::route('DELETE /@id',    [self::class, 'execDelete']);
            Flight::route('GET /',          [self::class, 'getList']);
            Flight::route('POST /',         [self::class, 'execAdd']);
        });
    }

    /**
     * タイプ一覧の取得
     *
     * @return void
     */
    public function getList(): void
    {
        $paging = $this->getPagingParams();
        $list = TypeModel::list(
            $paging["page"] * $paging["rows"],
            $paging["rows"]
        );
        $rows = array_map(
            fn($row): array => $this->toResponseRow($row->getData()),
            $list
        );

        // 取得件数が0件でもOKにする
        $this->resultJson($rows, 200, $this->route, "一覧が取得できました");
    }

    /**
     * タイプ詳細の取得
     *
     * @param string $sqid_key
     * @return void
     */
    public function getDetail(string $sqid_key): void
    {
        $type = TypeModel::detail(HashTools::decodeSqid($sqid_key));

        if (count($type) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        $this->resultJson($this->toResponseRow($type, $sqid_key), 200, $this->route, "取得できました");
    }

    /**
     * タイプの追加
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
        $name = $this->getBodyString("name", "");
        if ($this->respondIfInvalid((new TypeValidator($name))->valid()))
        {
            return;
        }

        try
        {
            TypeModel::add($name);
        }
        catch (PDOException $e)
        {
            if (!$this->respondIfDuplicateName($e))
            {
                throw $e;
            }
            return;
        }
        $type = TypeModel::detail(intval(BaseModel::getInsertId()));
        $this->resultJson($this->toResponseRow($type), 201, $this->route, "登録できました");
    }

    /**
     * タイプの更新
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
        $name = $this->getBodyString("name", "");
        if ($this->respondIfInvalid((new TypeValidator($name))->valid()))
        {
            return;
        }

        $id = HashTools::decodeSqid($sqid_key);
        // 同じ値での更新は影響行数が0になるため、存在確認は事前に行う
        if (count(TypeModel::detail($id)) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }

        try
        {
            TypeModel::update($id, $name);
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
     * タイプの削除
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
        if (count(TypeModel::detail($id)) === 0)
        {
            $this->resultJson([], 404, $this->route, "見つかりませんでした");
            return;
        }
        if (TypeModel::countRelated($id) > 0)
        {
            $this->resultJson([], 409, $this->route, "コンテンツから参照されているため削除できません");
            return;
        }

        TypeModel::delete($id);
        $this->resultJson([], 200, $this->route, "削除できました");
    }

    /**
     * タイプ名のUNIQUE制約違反なら409を返す
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
        $this->resultJson([], 409, $this->route, "同じ名前のタイプが既に登録されています");
        return true;
    }
}
