<?php

use App\Shared\Utils\BaseValidator;

/**
 * バリデーター: コンテンツ
 * ルールは`/documents/api/contents.md`の「入力チェック」を参照
 * 公開状態・参照先（執筆者・タイプ・カテゴリー・タグ）のチェックはコントローラーで行う
 */
class ContentValidator extends BaseValidator
{
    protected string $title = "";
    protected string $body = "";
    protected string $published_at = "";

    protected array $rules = [
        "title" => [
            "label" => "タイトル",
            "pattern" => "required|maxLength:255",
        ],
        "body" => [
            "label" => "本文",
            // TEXT型の上限（バイト数）
            "pattern" => "maxBytes:65535",
        ],
        "published_at" => [
            "label" => "公開日時",
            "pattern" => "dateTime",
        ],
    ];

    /**
     * @param string $title
     * @param string $body
     * @param string $published_at 未指定は空文字
     */
    public function __construct(string $title, string $body, string $published_at)
    {
        $this->title = $title;
        $this->body = $body;
        $this->published_at = $published_at;
    }

    /**
     * 最大バイト数チェック
     *
     * @param int $bytes
     * @return object
     */
    protected function maxBytes(int $bytes): object
    {
        if (is_string($this->value) && strlen($this->value) > $bytes)
        {
            $this->setErrors("{$this->label}が長すぎます（{$bytes}バイト以内）");
        }
        return $this;
    }

    /**
     * 日時形式（Y-m-d H:i:s）チェック（空文字は未指定としてチェックしない）
     *
     * @return object
     */
    protected function dateTime(): object
    {
        if (is_string($this->value) && $this->value !== "")
        {
            $date = DateTime::createFromFormat("Y-m-d H:i:s", $this->value);
            // 2026-02-30のような存在しない日付は繰り上げられるため、元の文字列と比較する
            if ($date === false || $date->format("Y-m-d H:i:s") !== $this->value)
            {
                $this->setErrors("{$this->label}は「YYYY-MM-DD hh:mm:ss」の形式で指定してください");
            }
        }
        return $this;
    }
}
