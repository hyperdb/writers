<?php

/**
 * PHPUnitのブートストラップ
 * `.env.local`のDBに接続する（DBを使うテストはトランザクション内で実行し、終了時にロールバックする）
 */

require __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\NullHandler;

// レスポンスの`api_version`に出力される値
const API_VERSION = 'test';

$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root, '.env.local')->load();

// データベース接続の設定（index.phpと同じオプション）
Flight::register('db', \flight\database\SimplePdo::class, [
    sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", $_ENV['DB_HOST'], $_ENV['DB_NAME']),
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
]);
// テスト中のログは出力しない
Flight::register('logger', Monolog\Logger::class, [
    'writer-test',
    [
        new NullHandler()
    ]
]);

require_once $root . '/app/controllers/Writer.php';
require_once $root . '/app/controllers/Site.php';
require_once $root . '/app/controllers/Type.php';
require_once $root . '/app/controllers/Category.php';
require_once $root . '/app/controllers/Tag.php';
require_once $root . '/app/controllers/Content.php';
require_once $root . '/app/controllers/SiteWriter.php';
require_once $root . '/app/controllers/Image.php';
require_once $root . '/app/controllers/ContentImage.php';
require_once __DIR__ . '/Support/DbTestCase.php';
require_once __DIR__ . '/Support/FakeAuthentication.php';
require_once __DIR__ . '/Support/AuthenticatedWriter.php';
require_once __DIR__ . '/Support/AuthenticatedSite.php';
require_once __DIR__ . '/Support/AuthenticatedType.php';
require_once __DIR__ . '/Support/AuthenticatedCategory.php';
require_once __DIR__ . '/Support/AuthenticatedTag.php';
require_once __DIR__ . '/Support/AuthenticatedContent.php';
require_once __DIR__ . '/Support/AuthenticatedSiteWriter.php';
require_once __DIR__ . '/Support/AuthenticatedImage.php';
require_once __DIR__ . '/Support/AuthenticatedContentImage.php';
require_once __DIR__ . '/Support/UnreachableAuthWriter.php';
