<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: サイト執筆者（サイトと執筆者の紐付け）
 * 更新はなく、追加と削除のみ
 */
class SiteWriterModel
{
    /**
     * サイトに紐付いた執筆者の一覧を取得（紐付けた日時の古い順）
     *
     * @param int $siteId
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function listBySite(int $siteId, int $index, int $perPage): array
    {
        $sql = "SELECT w.*
                FROM site_writers sw
                INNER JOIN writers w ON w.id = sw.writer_id
                WHERE sw.site_id = ?
                ORDER BY sw.created_at, w.id
                LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $siteId,
            $index,
            $perPage
        ]);
    }

    /**
     * 執筆者がサイトに紐付いているか
     *
     * @param int $siteId
     * @param int $writerId
     * @return boolean
     */
    public static function exists(int $siteId, int $writerId): bool
    {
        $sql = "SELECT COUNT(*) FROM site_writers WHERE site_id = ? AND writer_id = ?";
        return intval(BaseModel::fetchColumn($sql, [
            $siteId,
            $writerId
        ])[0] ?? 0) > 0;
    }

    /**
     * 執筆者をサイトに紐付ける（紐付け済みの場合は主キーの重複でPDOException）
     *
     * @param int $siteId
     * @param int $writerId
     * @return integer
     */
    public static function add(int $siteId, int $writerId): int
    {
        $sql = "INSERT INTO site_writers (site_id, writer_id) VALUES (?, ?)";
        return BaseModel::query($sql, [
            $siteId,
            $writerId
        ]);
    }

    /**
     * サイトと執筆者の紐付けを外す
     *
     * @param int $siteId
     * @param int $writerId
     * @return integer 影響行数（紐付けがなければ0）
     */
    public static function delete(int $siteId, int $writerId): int
    {
        $sql = "DELETE FROM site_writers WHERE site_id = ? AND writer_id = ?";
        return BaseModel::query($sql, [
            $siteId,
            $writerId
        ]);
    }
}
