<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: タグ
 */
class TagModel
{
    /**
     * 指定したIDのタグを取得
     *
     * @param int $id
     * @return array
     */
    public static function detail(int $id): array
    {
        $sql = "SELECT * FROM tags WHERE id = ?";
        $row = BaseModel::fetchRow($sql, [
            $id
        ]);
        return $row?->getData() ?? [];
    }

    /**
     * サイトのタグ一覧を取得
     *
     * @param int $siteId
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function listBySite(int $siteId, int $index, int $perPage): array
    {
        $sql = "SELECT * FROM tags WHERE site_id = ? ORDER BY id LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $siteId,
            $index,
            $perPage
        ]);
    }

    /**
     * タグ追加
     * idは自動採番
     *
     * @param int $siteId
     * @param string $tag
     * @return integer
     */
    public static function add(int $siteId, string $tag): int
    {
        $sql = "INSERT INTO tags (site_id, tag) VALUES (?, ?)";
        return BaseModel::query($sql, [
            $siteId,
            $tag
        ]);
    }

    /**
     * タグ更新（所属サイトは変更しない）
     *
     * @param int $id
     * @param string $tag
     * @return integer
     */
    public static function update(int $id, string $tag): int
    {
        $sql = "UPDATE tags SET tag = ? WHERE id = ?";
        return BaseModel::query($sql, [
            $tag,
            $id
        ]);
    }

    /**
     * タグ削除
     *
     * @param int $id
     * @return integer
     */
    public static function delete(int $id): int
    {
        $sql = "DELETE FROM tags WHERE id = ?";
        return BaseModel::query($sql, [
            $id
        ]);
    }

    /**
     * タグが付けられているコンテンツの件数を取得
     *
     * @param int $id
     * @return integer
     */
    public static function countRelated(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM content_tags WHERE tag_id = ?";
        return intval(BaseModel::fetchColumn($sql, [
            $id
        ])[0] ?? 0);
    }
}
