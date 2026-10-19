<?php

use flight\util\Collection;


/**
 * 基底クラス
 * 
 * @version 1.0.0
 */
class BaseModel
{
    /**
     * 更新クエリーの実行
     *
     * @param string $sql
     * @param mixed $params
     * @return int
     */
    public static function query(string $sql, mixed $params = []): int
    {
        $affected_rows = Flight::db()->runQuery($sql, $params);

        return $affected_rows->rowCount() ?? 0;
    }

    /**
     * 選択クエリーの実行（1行）
     *
     * @param string $sql
     * @param mixed $params
     * @return ?Collection
     */
    public static function fetchRow(string $sql, mixed $params = []): ?Collection
    {
        return Flight::db()->fetchRow($sql, $params);
    }

    /**
     * 選択クエリーの実行（全行）
     *
     * @param string $sql
     * @param mixed $params
     * @return array<int,Collection|array<string,mixed>>
     */
    public static function fetchAll(string $sql, mixed $params = []): array
    {
        return Flight::db()->fetchAll($sql, $params);
    }

    /**
     * 選択クエリーの実行（1列）
     *
     * @param string $sql
     * @param mixed $params
     * @return array<int,mixed>
     */
    public static function fetchColumn(string $sql, mixed $params = []): array
    {
        return Flight::db()->fetchColumn($sql, $params);
    }

    /**
     * 選択クエリーの実行（Key-Value列）
     *
     * @param string $sql
     * @param mixed $params
     * @return array<string|int,mixed>
     */
    public static function fetchKeyValue(string $sql, mixed $params = []): array
    {
        return Flight::db()->fetchPairs($sql, $params);
    }

    /**
     * 追加時のIDを返却
     * 元の`lastInsertId`がreturn stringなのでそのまま継承
     *
     * @return string
     */
    public static function getInsertId(): string
    {
        return Flight::db()->lastInsertId();
    }

    /**
     * トランザクション内で処理を実行する（例外時はロールバックして再スロー）
     * 既にトランザクション中（テストなど）の場合は、そのトランザクションの中で実行する
     *
     * @param callable $callback
     * @return mixed $callbackの戻り値
     */
    public static function transaction(callable $callback): mixed
    {
        $db = Flight::db();
        if ($db->inTransaction())
        {
            return $callback();
        }
        $db->beginTransaction();
        try
        {
            $result = $callback();
            $db->commit();
            return $result;
        }
        catch (Throwable $e)
        {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * UNIQUE制約違反（MySQLエラー1062: Duplicate entry）か判定する
     *
     * @param PDOException $e
     * @return boolean
     */
    public static function isDuplicateKey(PDOException $e): bool
    {
        return intval($e->errorInfo[1] ?? 0) === 1062;
    }
}
