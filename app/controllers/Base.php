<?php

use App\Shared\Utils\HashTools;

require_once __DIR__ . "/../models/SiteModel.php";

/**
 * 基底クラス
 *
 * @version 1.0.0
 */
class Base
{
    /** commonsのトークン判定API */
    private const TOKEN_VALID_URL = 'https://commons.hyperdb.cloud/api/v1/tokens/valid';
    /** commonsへの問い合わせのタイムアウト（秒） */
    private const TOKEN_VALID_TIMEOUT = 5;

    protected string $bearer_token;
    protected string $route;

    /**
     * Undocumented function
     */
    public function __construct()
    {
        $this->bearer_token = $this->getBearerToken();
        $this->route = Flight::request()->url;
    }

    /**
     * BEARERトークンをヘッダーから取得
     *
     * @return string
     */
    public function getBearerToken(): string
    {
        $authHeader = Flight::request()->getHeader('Authorization');

        $token = '';
        if (str_starts_with($authHeader, 'Bearer '))
        {
            $token = str_replace('Bearer ', '', $authHeader);
        }
        return $token ?? '';
    }

    /**
     * UUID V4を生成する
     *
     * @return string
     */
    public function generateUUID(): string
    {
        return HashTools::generateUuidV4();
    }

    /**
     * UUID V4を短縮化する
     *
     * @param string $uuid
     * @return string
     */
    public function shortenID(string $uuid): string
    {
        return HashTools::getShortUuidV4($uuid);
    }

    /**
     * 短縮化されたUUID V4を復元する
     *
     * @param string $shortenId
     * @return string
     */
    public function expandID(string $shortenId): string
    {
        return HashTools::expandShortUuidV4($shortenId);
    }

    /**
     * 数値型のIDを文字列化して秘匿化
     *
     * @param integer $id
     * @return string
     */
    public function generateSqid(int $id): string
    {
        return HashTools::generateSqid($id);
    }

    /**
     * sqid化したIDを復元する
     *
     * @param string $sqid
     * @return integer
     */
    public function decodeSqid(string $sqid): int
    {
        return HashTools::decodeSqid($sqid);
    }


    /**
     * 結果をJSONで返却する
     *
     * @param array $data
     * @param integer $status
     * @param string $route
     * @return void
     */
    public function resultJson(array $data, int $status, string $route, string $message): void
    {
        Flight::json(
            [
                "api_version" => API_VERSION,
                "api_route" => $route,
                "send_at" => date("Y-m-d H:i:s"),
                "message" => $message,
                "data" => $data,
            ],
            $status,
            true,
            'utf-8',
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * レスポンス用の行に変換する（ID列は返却せず、sqid化したkeyを付与する）
     *
     * @param array $row
     * @param ?string $key 指定時はこの値をkeyにする（未指定時はIDから生成）
     * @param array<string,string> $foreignKeys 参照列名 => 返却時の項目名（例: ["site_id" => "site_key"]）
     * @return array
     */
    protected function toResponseRow(array $row, ?string $key = null, array $foreignKeys = []): array
    {
        $row['key'] = $key ?? HashTools::generateSqid(intval($row['id']));
        unset($row['id']);
        // 参照列もsqid化する（参照先なしの0はnull）
        foreach ($foreignKeys as $column => $name)
        {
            $id = intval($row[$column] ?? 0);
            $row[$name] = $id > 0 ? HashTools::generateSqid($id) : null;
            unset($row[$column]);
        }
        return $row;
    }

    /**
     * 入力チェックのエラーがあれば400を返す
     *
     * @param array<string,array<int,string>> $errors バリデーターの`valid()`の結果
     * @return boolean エラーがあり400を返した場合はtrue（呼び出し元は処理を中断すること）
     */
    protected function respondIfInvalid(array $errors): bool
    {
        if (count($errors) === 0)
        {
            return false;
        }
        $this->resultJson($errors, 400, $this->route, "入力内容に誤りがあります");
        return true;
    }

    /**
     * サイトのkeyからサイトIDを取得する
     *
     * @param string $site_key
     * @return integer サイトが存在しない場合は0
     */
    protected function findSiteId(string $site_key): int
    {
        $id = HashTools::decodeSqid($site_key);
        return $id > 0 && count(SiteModel::detail($id)) > 0 ? $id : 0;
    }

    /**
     * Bearerトークンで認証する
     * 無効な場合は401、commonsに問い合わせできない場合は502を返す
     *
     * @return boolean 認証できなければfalse（呼び出し元は処理を中断すること）
     */
    public function authenticate(): bool
    {
        $valid = $this->validToken($this->bearer_token);
        if ($valid === null)
        {
            $this->resultJson([], 502, $this->route, "認証サーバーに接続できませんでした");
            return false;
        }
        if ($valid === false)
        {
            $this->resultJson([], 401, $this->route, "認証できませんでした");
            return false;
        }
        return true;
    }

    /**
     * トークンの有効性をcommons APIに問い合わせる
     *
     * @param string $shortenToken
     * @return ?bool 有効ならtrue、無効ならfalse、問い合わせできなければnull
     */
    protected function validToken(string $shortenToken): ?bool
    {
        // 短縮トークンの形式（Base64URL・22文字）でなければ問い合わせずに無効とする
        if (!preg_match('/^[A-Za-z0-9_-]{22}$/', $shortenToken))
        {
            return false;
        }

        $ch = curl_init(self::TOKEN_VALID_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TOKEN_VALID_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TOKEN_VALID_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode(["token" => $shortenToken]),
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        // 無効なトークンでも200が返るため、200以外は問い合わせ失敗とする
        if ($response === false || $status !== 200)
        {
            Flight::logger()->error("トークン判定APIへの問い合わせに失敗しました", [
                "status" => $status,
                "error" => curl_error($ch),
            ]);
            return null;
        }

        // 判定結果は`data.valid`に"OK"（有効）か"NG"（無効）で返る
        $result = json_decode($response, true);
        $valid = is_array($result) ? ($result['data']['valid'] ?? null) : null;
        if ($valid !== "OK" && $valid !== "NG")
        {
            Flight::logger()->error("トークン判定APIの応答が想定外の形式です", [
                "response" => mb_substr(strval($response), 0, 200),
            ]);
            return null;
        }
        return $valid === "OK";
    }

    /**
     * string型のクエリパラメータを取得
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getStringParam(string $key, string $default = ""): string
    {
        $value = Flight::request()->query[$key] ?? null;
        // 未指定・配列などの文字列化できない値は既定値にする
        if (!is_scalar($value) || $this->isNullString(strval($value)))
        {
            return $default;
        }
        return strval($value);
    }

    /**
     * int型のクエリパラメータを取得
     *
     * @param string $key
     * @param integer $default
     * @return int
     */
    public function getIntParam(string $key, int $default = 0): int
    {
        $value = Flight::request()->query[$key] ?? null;
        // 未指定・配列などの数値化できない値は既定値にする
        if (!is_scalar($value) || $this->isNullString(strval($value)))
        {
            return $default;
        }
        return intval($value);
    }

    /**
     * 一覧取得のページングパラメータを取得（不正な値は補正する）
     *
     * @return array{page: int, rows: int}
     */
    public function getPagingParams(): array
    {
        $page = $this->getIntParam("page", 0);
        $rows = $this->getIntParam("rows", 10);

        return [
            // 負のページ番号は0として扱う
            "page" => max($page, 0),
            // 0以下の件数は既定値として扱う
            "rows" => $rows > 0 ? $rows : 10,
        ];
    }

    /**
     * string型のボディ（JSON）パラメータを取得（POST / PUT用）
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function getBodyString(string $key, string $default = ""): string
    {
        $value = Flight::request()->data[$key] ?? null;
        // 未指定・配列などの文字列化できない値は既定値にする
        if (!is_scalar($value) || strval($value) === "")
        {
            return $default;
        }
        return strval($value);
    }

    /**
     * 配列型のボディ（JSON）パラメータを取得（POST / PUT用）
     *
     * @param string $key
     * @return ?array<int,string> 未指定は空配列、配列でない値はnull（各要素は文字列化し、配列などの要素は除外）
     */
    public function getBodyArray(string $key): ?array
    {
        $value = Flight::request()->data[$key] ?? [];
        if (!is_array($value))
        {
            return null;
        }
        return array_values(array_map('strval', array_filter($value, 'is_scalar')));
    }

    /**
     * Null or Empty の判定
     *
     * @param ?string $value
     * @return boolean
     */
    public function isNullString(?string $value): bool
    {
        return $value === null || $value === "";
    }
}
