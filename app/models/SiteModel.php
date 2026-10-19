<?php

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: サイト
 */
class SiteModel
{
    /**
     * 指定したIDのサイトを取得
     *
     * @param int $id
     * @return array
     */
    public static function detail(int $id): array
    {
        $sql = "SELECT * FROM sites WHERE id = ?";
        $row = BaseModel::fetchRow($sql, [
            $id
        ]);
        return $row?->getData() ?? [];
    }

    /**
     * サイト一覧を取得
     *
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function list(int $index, int $perPage): array
    {
        $sql = "SELECT * FROM sites ORDER BY id LIMIT ?, ?";
        return BaseModel::fetchAll($sql, [
            $index,
            $perPage
        ]);
    }

    /**
     * サイト追加
     * idは自動採番
     *
     * @param string $url
     * @param string $name
     * @return integer
     */
    public static function add(string $url, string $name): int
    {
        $sql = "INSERT INTO sites (url, name) VALUES (?, ?)";
        return BaseModel::query($sql, [
            $url,
            $name
        ]);
    }

    /**
     * サイト更新
     *
     * @param int $id
     * @param string $url
     * @param string $name
     * @return integer
     */
    public static function update(int $id, string $url, string $name): int
    {
        $sql = "UPDATE sites SET url = ?, name = ? WHERE id = ?";
        return BaseModel::query($sql, [
            $url,
            $name,
            $id
        ]);
    }

    /**
     * サイト削除
     *
     * @param int $id
     * @return integer
     */
    public static function delete(int $id): int
    {
        $sql = "DELETE FROM sites WHERE id = ?";
        return BaseModel::query($sql, [
            $id
        ]);
    }

    /**
     * サイトに紐付くデータ（コンテンツ・カテゴリー・タグ・サイト執筆者）の件数を取得
     *
     * @param int $id
     * @return integer
     */
    public static function countRelated(int $id): int
    {
        $sql = "SELECT
                (SELECT COUNT(*) FROM contents WHERE site_id = ?)
              + (SELECT COUNT(*) FROM categories WHERE site_id = ?)
              + (SELECT COUNT(*) FROM tags WHERE site_id = ?)
              + (SELECT COUNT(*) FROM site_writers WHERE site_id = ?)";
        return intval(BaseModel::fetchColumn($sql, [
            $id,
            $id,
            $id,
            $id
        ])[0] ?? 0);
    }
}
