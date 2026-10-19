<?php

require './vendor/autoload.php';

use Monolog\Level;
// use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

require __DIR__ . '/app/utils/CorsUtil.php';

// APIのバージョン（レスポンスの`api_version`に出力）
const API_VERSION = '1.0.0';

/**
 * 初期設定
 */
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
// データベース接続の設定
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
// ログ出力の設定
$date_format = "Y-m-d H:i:s.v";
$logFormat = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
$formatter = new LineFormatter($logFormat, $date_format);

$stream = new RotatingFileHandler(__DIR__ . '/logs/api.log', 7, Level::Debug);
$stream->setFormatter($formatter);
Flight::register('logger', Monolog\Logger::class, [
    'writer',
    [
        $stream
    ]
]);

/**
 * ルーティング
 */
require_once './app/controllers/Writer.php';
require_once './app/controllers/Site.php';
require_once './app/controllers/Type.php';
require_once './app/controllers/Category.php';
require_once './app/controllers/Tag.php';
require_once './app/controllers/Content.php';

Flight::group('/api', function ()
{
    Flight::group('/v1', function ()
    {
        Writer::registerRoutes();
        Site::registerRoutes();
        Type::registerRoutes();
        Category::registerRoutes();
        Tag::registerRoutes();
        Content::registerRoutes();
    });
});

Flight::route('/', function ()
{
    echo 'writers ver.' . API_VERSION;
});

Flight::route('/json', function ()
{
    Flight::json([
        'version' => API_VERSION
    ]);
});

Flight::map('notFound', function ()
{
    Flight::json([
        'api_version' => API_VERSION,
        'api_route' => Flight::request()->url,
        'send_at' => date("Y-m-d H:i:s"),
        'message' => "Page Not Found.",
        'data' => []
    ], 404);
});

/**
 * 前処理
 */
// CORS対応
$app = Flight::app();
$cors = new CorsUtil($app);
$app->before('start', [$cors, 'set']);

/**
 * アクセスログ
 */

$log_message = sprintf(
    "%s %s",
    Flight::request()->method,
    Flight::request()->url
);
$log_params = [
    "ip" => Flight::request()->ip,
    "host" => Flight::request()->host,
];
Flight::logger()->debug($log_message, $log_params);

/**
 * 処理開始
 */
Flight::start();
