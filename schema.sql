/*
 * テーブル定義の正本
 * 各エンティティの意味・業務ルールは`/documents/Entities.md`を参照のこと
 */


/**
 * エンティティ: コンテンツ（記事）の執筆者
 * 認証は`commons.hyperdb.cloud`に任せる
 */
CREATE TABLE IF NOT EXISTS writers (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '執筆者ID',
    `user_id` VARCHAR(32) NOT NULL COMMENT 'ユーザーID',
    `nickname` VARCHAR(32) NOT NULL COMMENT 'ニックネーム',
    `is_admin` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '管理者フラグ（1: 管理者）',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_writers_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='執筆者';

/**
 * エンティティ: サイト
 *
 */
CREATE TABLE IF NOT EXISTS sites (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'サイトID',
    `url` VARCHAR(255) NOT NULL COMMENT 'サイトURL',
    `name` VARCHAR(255) NOT NULL COMMENT 'サイト名',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sites_url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='サイト';

/**
 * エンティティマップ: サイト執筆者マップ
 * 更新は無しで、delete-insertで運用するため`updated_at`は持たない
 */
CREATE TABLE IF NOT EXISTS site_writers (
    `site_id` INT(10) UNSIGNED NOT NULL COMMENT 'サイトID',
    `writer_id` INT(10) UNSIGNED NOT NULL COMMENT '執筆者ID',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    PRIMARY KEY (`site_id`, `writer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='サイト執筆者';

/**
 * エンティティ: コンテンツ
 *
 */
CREATE TABLE IF NOT EXISTS contents (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'コンテンツID',
    `title` VARCHAR(255) NOT NULL COMMENT 'タイトル',
    `body` TEXT NULL COMMENT '本文',
    `status` VARCHAR(16) NOT NULL DEFAULT 'draft' COMMENT '公開状態（draft / published）',
    `published_at` DATETIME NULL DEFAULT NULL COMMENT '公開日時',
    `type_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'コンテンツタイプID',
    `category_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'カテゴリーID',
    `site_id` INT(10) UNSIGNED NOT NULL COMMENT 'サイトID',
    `writer_id` INT(10) UNSIGNED NOT NULL COMMENT '執筆者ID',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='コンテンツ';

/**
 * エンティティ: カテゴリー
 * コンテンツとカテゴリーは1対1
 * カテゴリーは階層を持つ（最上位では親カテゴリーは`0`
 */
CREATE TABLE IF NOT EXISTS categories (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'カテゴリーID',
    `category` VARCHAR(255) NOT NULL COMMENT 'カテゴリー名',
    `parent_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '親カテゴリーID',
    `site_id` INT(10) UNSIGNED NOT NULL COMMENT 'サイトID',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_site_parent_category` (`site_id`, `parent_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='カテゴリー';

/**
 * エンティティ: タグ
 * コンテンツとタグは1対N
 */
CREATE TABLE IF NOT EXISTS tags (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'タグID',
    `tag` VARCHAR(255) NOT NULL COMMENT 'タグ名',
    `site_id` INT(10) UNSIGNED NOT NULL COMMENT 'サイトID',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_site_tag` (`site_id`, `tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='タグ';

/**
 * エンティティマップ: コンテンツタグマップ
 * 更新は無しで、delete-insertで運用するため`updated_at`は持たない
 */
CREATE TABLE IF NOT EXISTS content_tags (
    `content_id` INT(10) UNSIGNED NOT NULL COMMENT 'コンテンツID',
    `tag_id` INT(10) UNSIGNED NOT NULL COMMENT 'タグID',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    PRIMARY KEY (`content_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='コンテンツタグ';

/**
 * エンティティ: タイプ
 * コンテンツとタイプは1対1
 */
CREATE TABLE IF NOT EXISTS types (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'タイプID',
    `name` VARCHAR(255) NOT NULL COMMENT 'タイプ名',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='タイプ';

