<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: イメージ
 */
class ImageModel
{
    /**
     * 指定したIDのイメージを取得
     *
     * @param int $id
     * @return array
     */
    public static function detail(int $id): array
    {
        $sql = "SELECT * FROM images WHERE id = ?";
        $row = BaseModel::fetchRow($sql, [
            $id
        ]);
        return $row?->getData() ?? [];
    }

    /**
     * サイトのイメージ一覧を取得
     *
     * @param int $siteId
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function listBySite(int $siteId, int $index, int $perPage): array
    {
        $sql = "SELECT * FROM images WHERE site_id = ? ORDER BY id LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $siteId,
            $index,
            $perPage
        ]);
    }

    /**
     * イメージ追加
     * idは自動採番
     *
     * @param int $siteId
     * @param string $sourceName
     * @param string $extension
     * @return integer
     */
    public static function add(int $siteId, string $sourceName, string $extension): int
    {
        $sql = "INSERT INTO images (site_id, source_name, extension) VALUES (?, ?, ?)";
        return BaseModel::query($sql, [
            $siteId,
            $sourceName,
            $extension
        ]);
    }

    /**
     * イメージ更新（ファイルの差し替え。所属サイトは変更しない）
     *
     * @param int $id
     * @param string $sourceName
     * @param string $extension
     * @return integer
     */
    public static function update(int $id, string $sourceName, string $extension): int
    {
        $sql = "UPDATE images SET source_name = ?, extension = ? WHERE id = ?";
        return BaseModel::query($sql, [
            $sourceName,
            $extension,
            $id
        ]);
    }

    /**
     * イメージ削除
     *
     * @param int $id
     * @return integer
     */
    public static function delete(int $id): int
    {
        $sql = "DELETE FROM images WHERE id = ?";
        return BaseModel::query($sql, [
            $id
        ]);
    }

    /**
     * イメージを参照しているコンテンツの件数を取得
     *
     * @param int $id
     * @return integer
     */
    public static function countRelated(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM content_images WHERE image_id = ?";
        return intval(BaseModel::fetchColumn($sql, [
            $id
        ])[0] ?? 0);
    }
}
