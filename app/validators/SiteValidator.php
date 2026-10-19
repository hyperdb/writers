<?php

use App\Shared\Utils\BaseValidator;

/**
 * バリデーター: サイト
 * ルールは`/documents/api/sites.md`の「入力チェック」を参照
 */
class SiteValidator extends BaseValidator
{
    /** http(s)で始まり、ホスト部を持つURL */
    private const URL_PATTERN = '/^https?:\/\/[^\s\/?#]+(?:[\/?#]\S*)?$/i';

    protected string $url = "";
    protected string $name = "";

    protected array $rules = [
        "url" => [
            "label" => "URL",
            "pattern" => ["required", ["maxLength", 255], ["matchPattern", self::URL_PATTERN]],
        ],
        "name" => [
            "label" => "サイト名",
            "pattern" => "required|maxLength:255",
        ],
    ];

    /**
     * @param string $url 末尾の"/"は除去して検証する
     * @param string $name
     */
    public function __construct(string $url, string $name)
    {
        $this->url = self::normalizeUrl($url);
        $this->name = $name;
    }

    /**
     * 保存用にURLを正規化する（前後の空白と末尾の"/"を除去）
     *
     * @param string $url
     * @return string
     */
    public static function normalizeUrl(string $url): string
    {
        return rtrim(trim($url), "/");
    }
}
