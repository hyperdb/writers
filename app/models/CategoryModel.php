<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: カテゴリー
 */
class CategoryModel
{
    /**
     * 指定したIDのカテゴリーを取得
     *
     * @param int $id
     * @return array
     */
    public static function detail(int $id): array
    {
        $sql = "SELECT * FROM categories WHERE id = ?";
        $row = BaseModel::fetchRow($sql, [
            $id
        ]);
        return $row?->getData() ?? [];
    }

    /**
     * サイトのカテゴリー一覧を取得
     *
     * @param int $siteId
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function listBySite(int $siteId, int $index, int $perPage): array
    {
        $sql = "SELECT * FROM categories WHERE site_id = ? ORDER BY id LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $siteId,
            $index,
            $perPage
        ]);
    }

    /**
     * カテゴリー追加
     * idは自動採番
     *
     * @param int $siteId
     * @param int $parentId 最上位は0
     * @param string $category
     * @return integer
     */
    public static function add(int $siteId, int $parentId, string $category): int
    {
        $sql = "INSERT INTO categories (site_id, parent_id, category) VALUES (?, ?, ?)";
        return BaseModel::query($sql, [
            $siteId,
            $parentId,
            $category
        ]);
    }

    /**
     * カテゴリー更新（所属サイトは変更しない）
     *
     * @param int $id
     * @param int $parentId 最上位は0
     * @param string $category
     * @return integer
     */
    public static function update(int $id, int $parentId, string $category): int
    {
        $sql = "UPDATE categories SET parent_id = ?, category = ? WHERE id = ?";
        return BaseModel::query($sql, [
            $parentId,
            $category,
            $id
        ]);
    }

    /**
     * カテゴリー削除
     *
     * @param int $id
     * @return integer
     */
    public static function delete(int $id): int
    {
        $sql = "DELETE FROM categories WHERE id = ?";
        return BaseModel::query($sql, [
            $id
        ]);
    }

    /**
     * カテゴリーを参照しているデータ（子カテゴリー・コンテンツ）の件数を取得
     *
     * @param int $id
     * @return integer
     */
    public static function countRelated(int $id): int
    {
        $sql = "SELECT
                (SELECT COUNT(*) FROM categories WHERE parent_id = ?)
              + (SELECT COUNT(*) FROM contents WHERE category_id = ?)";
        return intval(BaseModel::fetchColumn($sql, [
            $id,
            $id
        ])[0] ?? 0);
    }
}
