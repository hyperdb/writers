<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: コンテンツイメージ（コンテンツとイメージの紐付け）
 * 更新はなく、追加と削除のみ
 */
class ContentImageModel
{
    /**
     * コンテンツに紐付いたイメージの一覧を取得（紐付けた日時の古い順）
     *
     * @param int $contentId
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function listByContent(int $contentId, int $index, int $perPage): array
    {
        $sql = "SELECT i.*
                FROM content_images ci
                INNER JOIN images i ON i.id = ci.image_id
                WHERE ci.content_id = ?
                ORDER BY ci.created_at, i.id
                LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $contentId,
            $index,
            $perPage
        ]);
    }

    /**
     * コンテンツにイメージを紐付ける（紐付け済みの場合は主キーの重複でPDOException）
     *
     * @param int $contentId
     * @param int $imageId
     * @return integer
     */
    public static function add(int $contentId, int $imageId): int
    {
        $sql = "INSERT INTO content_images (content_id, image_id) VALUES (?, ?)";
        return BaseModel::query($sql, [
            $contentId,
            $imageId
        ]);
    }

    /**
     * コンテンツとイメージの紐付けを外す
     *
     * @param int $contentId
     * @param int $imageId
     * @return integer 影響行数（紐付けがなければ0）
     */
    public static function delete(int $contentId, int $imageId): int
    {
        $sql = "DELETE FROM content_images WHERE content_id = ? AND image_id = ?";
        return BaseModel::query($sql, [
            $contentId,
            $imageId
        ]);
    }
}
