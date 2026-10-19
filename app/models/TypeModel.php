<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: コンテンツタイプ
 */
class TypeModel
{
    /**
     * 指定したIDのタイプを取得
     *
     * @param int $id
     * @return array
     */
    public static function detail(int $id): array
    {
        $sql = "SELECT * FROM types WHERE id = ?";
        $row = BaseModel::fetchRow($sql, [
            $id
        ]);
        return $row?->getData() ?? [];
    }

    /**
     * タイプ一覧を取得
     *
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function list(int $index, int $perPage): array
    {
        $sql = "SELECT * FROM types ORDER BY id LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $index,
            $perPage
        ]);
    }

    /**
     * タイプ追加
     * idは自動採番
     *
     * @param string $name
     * @return integer
     */
    public static function add(string $name): int
    {
        $sql = "INSERT INTO types (name) VALUES (?)";
        return BaseModel::query($sql, [
            $name
        ]);
    }

    /**
     * タイプ更新
     *
     * @param int $id
     * @param string $name
     * @return integer
     */
    public static function update(int $id, string $name): int
    {
        $sql = "UPDATE types SET name = ? WHERE id = ?";
        return BaseModel::query($sql, [
            $name,
            $id
        ]);
    }

    /**
     * タイプ削除
     *
     * @param int $id
     * @return integer
     */
    public static function delete(int $id): int
    {
        $sql = "DELETE FROM types WHERE id = ?";
        return BaseModel::query($sql, [
            $id
        ]);
    }

    /**
     * タイプを参照しているコンテンツの件数を取得
     *
     * @param int $id
     * @return integer
     */
    public static function countRelated(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM contents WHERE type_id = ?";
        return intval(BaseModel::fetchColumn($sql, [
            $id
        ])[0] ?? 0);
    }
}
