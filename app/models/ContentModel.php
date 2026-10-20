<?php

use App\Enums\ContentStatus;

require_once __DIR__ . '/BaseModel.php';


/**
 * モデル: コンテンツ
 * タグの紐付け（content_tags）もこのモデルで扱う
 */
class ContentModel
{
    /** 公開中の条件（パラメーターにContentStatus::Publishedの値を渡す） */
    private const PUBLISHED_CONDITION = "c.status = ? AND c.published_at <= NOW()";

    /** 一覧の絞り込みに使える列 */
    private const FILTER_COLUMNS = ["type_id", "category_id", "writer_id"];

    /**
     * 指定したIDのコンテンツを取得
     *
     * @param int $id
     * @param bool $includeUnpublished 下書き・公開予約中も含めるか
     * @return array
     */
    public static function detail(int $id, bool $includeUnpublished): array
    {
        $sql = "SELECT c.* FROM contents c WHERE c.id = ?";
        $params = [$id];
        if (!$includeUnpublished)
        {
            $sql .= " AND " . self::PUBLISHED_CONDITION;
            $params[] = ContentStatus::Published->value;
        }
        $row = BaseModel::fetchRow($sql, $params);
        return $row?->getData() ?? [];
    }

    /**
     * サイトのコンテンツ一覧を取得（公開日時の新しい順、公開日時のない下書きは最後）
     *
     * @param int $siteId
     * @param array<string,int> $filters 絞り込み（type_id / category_id / writer_id / tag_id => ID）
     * @param bool $includeUnpublished 下書き・公開予約中も含めるか
     * @param int $index (一行目の行番号: ページ番号✕ページあたり行数)
     * @param int $perPage
     * @return array
     */
    public static function listBySite(
        int $siteId,
        array $filters,
        bool $includeUnpublished,
        int $index,
        int $perPage
    ): array {
        $where = ["c.site_id = ?"];
        $params = [$siteId];
        if (!$includeUnpublished)
        {
            $where[] = self::PUBLISHED_CONDITION;
            $params[] = ContentStatus::Published->value;
        }
        // 列名は固定の一覧から選ぶ（外部の値を列名に使わない）
        foreach (self::FILTER_COLUMNS as $column)
        {
            if (isset($filters[$column]))
            {
                $where[] = "c.{$column} = ?";
                $params[] = $filters[$column];
            }
        }
        if (isset($filters["tag_id"]))
        {
            $where[] = "EXISTS (SELECT 1 FROM content_tags ct WHERE ct.content_id = c.id AND ct.tag_id = ?)";
            $params[] = $filters["tag_id"];
        }
        $params[] = $index;
        $params[] = $perPage;

        $sql = "SELECT c.* FROM contents c WHERE " . implode(" AND ", $where)
            . " ORDER BY c.published_at IS NULL, c.published_at DESC, c.id DESC LIMIT ?, ?";
        return BaseModel::fetchAll($sql, $params);
    }

    /**
     * コンテンツに付けられたタグを取得
     *
     * @param array<int,int> $contentIds
     * @return array<int,array<int,array{id: int, tag: string}>> コンテンツID => タグの配列
     */
    public static function tagsOf(array $contentIds): array
    {
        $result = array_fill_keys($contentIds, []);
        if (count($contentIds) === 0)
        {
            return $result;
        }
        $sql = "SELECT ct.content_id, t.id, t.tag
                FROM content_tags ct
                INNER JOIN tags t ON t.id = ct.tag_id
                WHERE ct.content_id IN (?)
                ORDER BY t.id";
        foreach (BaseModel::fetchAll($sql, [$contentIds]) as $row)
        {
            $result[intval($row["content_id"])][] = [
                "id" => intval($row["id"]),
                "tag" => strval($row["tag"]),
            ];
        }
        return $result;
    }

    /**
     * コンテンツ追加（タグの紐付けも行う）
     * 公開で公開日時が未指定の場合は現在日時を設定する
     *
     * @param array{title: string, body: string, status: string, published_at: ?string, type_id: int, category_id: int, site_id: int, writer_id: int} $values
     * @param array<int,int> $tagIds
     * @return integer 追加したコンテンツのID
     */
    public static function create(array $values, array $tagIds): int
    {
        return BaseModel::transaction(function () use ($values, $tagIds): int
        {
            $sql = "INSERT INTO contents
                    (title, body, status, published_at, type_id, category_id, site_id, writer_id)
                    VALUES (?, ?, ?, IF(? = ?, COALESCE(?, NOW()), ?), ?, ?, ?, ?)";
            BaseModel::query($sql, [
                $values["title"],
                $values["body"],
                $values["status"],
                $values["status"],
                ContentStatus::Published->value,
                $values["published_at"],
                $values["published_at"],
                $values["type_id"],
                $values["category_id"],
                $values["site_id"],
                $values["writer_id"],
            ]);
            $id = intval(BaseModel::getInsertId());
            self::replaceTags($id, $tagIds);
            return $id;
        });
    }

    /**
     * コンテンツ更新（タグの紐付けも置き換える。所属サイトと執筆者は変更しない）
     * 公開で公開日時が未指定の場合は、既存の公開日時を維持（なければ現在日時）
     *
     * @param int $id
     * @param array{title: string, body: string, status: string, published_at: ?string, type_id: int, category_id: int} $values
     * @param array<int,int> $tagIds
     * @return void
     */
    public static function update(int $id, array $values, array $tagIds): void
    {
        BaseModel::transaction(function () use ($id, $values, $tagIds): void
        {
            $sql = "UPDATE contents SET
                    title = ?,
                    body = ?,
                    status = ?,
                    published_at = IF(? = ?, COALESCE(?, published_at, NOW()), ?),
                    type_id = ?,
                    category_id = ?
                    WHERE id = ?";
            BaseModel::query($sql, [
                $values["title"],
                $values["body"],
                $values["status"],
                $values["status"],
                ContentStatus::Published->value,
                $values["published_at"],
                $values["published_at"],
                $values["type_id"],
                $values["category_id"],
                $id,
            ]);
            self::replaceTags($id, $tagIds);
        });
    }

    /**
     * コンテンツ削除（タグの紐付けも削除する）
     *
     * @param int $id
     * @return void
     */
    public static function delete(int $id): void
    {
        BaseModel::transaction(function () use ($id): void
        {
            BaseModel::query("DELETE FROM content_tags WHERE content_id = ?", [$id]);
            BaseModel::query("DELETE FROM contents WHERE id = ?", [$id]);
        });
    }

    /**
     * タグの紐付けを置き換える（delete-insert）
     *
     * @param int $contentId
     * @param array<int,int> $tagIds
     * @return void
     */
    private static function replaceTags(int $contentId, array $tagIds): void
    {
        BaseModel::query("DELETE FROM content_tags WHERE content_id = ?", [$contentId]);
        foreach (array_unique($tagIds) as $tagId)
        {
            BaseModel::query("INSERT INTO content_tags (content_id, tag_id) VALUES (?, ?)", [
                $contentId,
                $tagId
            ]);
        }
    }
}
