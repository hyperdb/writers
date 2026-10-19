<?php

use flight\Engine;

/**
 * Undocumented class
 */
class CorsUtil
{
    protected Engine $app;

    /**
     * Undocumented function
     *
     * @param Engine $app
     */
    public function __construct(Engine $app)
    {
        $this->app = $app;
    }

    /**
     * Undocumented function
     *
     * @param array $params
     * @return void
     */
    public function set(array $params = []): void
    {
        $request = $this->app->request();
        $response = $this->app->response();
        if ($request->getVar('HTTP_ORIGIN') !== '')
        {
            $this->allowOrigins();
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Max-Age', '86400');
        }

        if ($request->method === 'OPTIONS')
        {
            if ($request->getVar('HTTP_ACCESS_CONTROL_REQUEST_METHOD') !== '')
            {
                $response->header(
                    'Access-Control-Allow-Methods',
                    'GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD'
                );
            }
            if ($request->getVar('HTTP_ACCESS_CONTROL_REQUEST_HEADERS') !== '')
            {
                $response->header(
                    "Access-Control-Allow-Headers",
                    $request->getVar('HTTP_ACCESS_CONTROL_REQUEST_HEADERS')
                );
            }

            $response->status(200);
            $response->send();
            exit;
        }
    }

    /**
     * 許可されたオリジンならAccess-Control-Allow-Originを付与する
     *
     * @return void
     */
    private function allowOrigins(): void
    {
        $allowed = $this->getAllowedOrigins();

        $request = $this->app->request();

        if (in_array($request->getVar('HTTP_ORIGIN'), $allowed, true) === true)
        {
            $response = $this->app->response();
            $response->header("Access-Control-Allow-Origin", $request->getVar('HTTP_ORIGIN'));
        }
    }

    /**
     * 許可するオリジンの一覧を.envの`CORS_ALLOWED_ORIGINS`（","区切り）から取得する
     *
     * @return array<int,string> (未設定の場合は空配列)
     */
    private function getAllowedOrigins(): array
    {
        $origins = explode(",", strval($_ENV['CORS_ALLOWED_ORIGINS'] ?? ""));
        // 前後の空白と末尾の"/"を除去し、空の要素は除外する
        $origins = array_map(fn(string $origin): string => rtrim(trim($origin), "/"), $origins);
        return array_values(array_filter($origins, fn(string $origin): bool => $origin !== ""));
    }
}
