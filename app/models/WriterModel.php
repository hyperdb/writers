<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: 執筆者
 */
class WriterModel
{
    /**
     * 指定したIDの執筆者を取得
     *
     * @param int $id
     * @return array
     */
    public static function detail(int $id): array
    {
        $sql = "SELECT * FROM writers WHERE id = ?";
        $row = BaseModel::fetchRow($sql, [
            $id
        ]);
        return $row?->getData() ?? [];
    }

    /**
     * 指定したユーザーIDの執筆者を取得
     *
     * @param string $user_id commonsのユーザーID
     * @return array 執筆者として登録されていなければ空配列
     */
    public static function findByUserId(string $user_id): array
    {
        $sql = "SELECT * FROM writers WHERE user_id = ?";
        $row = BaseModel::fetchRow($sql, [
            $user_id
        ]);
        return $row?->getData() ?? [];
    }

    /**
     * 執筆者一覧を取得
     * 
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function list(int $index, int $perPage): array
    {
        $sql = "SELECT * FROM writers LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $index,
            $perPage
        ]);
    }

    /**
     * 執筆者更新（ニックネーム変更）
     * user_idの付け替えは認めない
     *
     * @param int $id
     * @param string $nickname
     * @return integer
     */
    public static function update(int $id, string $nickname): int
    {
        $sql = "UPDATE writers SET nickname = ? WHERE id = ?";
        return BaseModel::query($sql, [
            $nickname,
            $id
        ]);
    }

    /**
     * 執筆者削除
     *
     * @param int $id
     * @return integer
     */
    public static function delete(int $id): int
    {
        $sql = "DELETE FROM writers WHERE id = ?";
        return BaseModel::query($sql, [
            $id
        ]);
    }

    /**
     * 執筆者を参照しているデータ（コンテンツ・サイト執筆者）の件数を取得
     *
     * @param int $id
     * @return integer
     */
    public static function countRelated(int $id): int
    {
        $sql = "SELECT
                (SELECT COUNT(*) FROM contents WHERE writer_id = ?)
              + (SELECT COUNT(*) FROM site_writers WHERE writer_id = ?)";
        return intval(BaseModel::fetchColumn($sql, [
            $id,
            $id
        ])[0] ?? 0);
    }

    /**
     * 執筆者追加
     * idは自動採番
     *
     * @param string $user_id
     * @param string $nickname
     * @return integer
     */
    public static function add(string $user_id, string $nickname): int
    {
        $sql = "INSERT INTO writers ( id, user_id, nickname) VALUES ( ?, ?, ? )";
        return BaseModel::query($sql, [
            null,
            $user_id,
            $nickname
        ]);
    }
}
