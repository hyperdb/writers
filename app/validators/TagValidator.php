<?php

use App\Shared\Utils\BaseValidator;

/**
 * バリデーター: タグ
 * ルールは`/documents/api/tags.md`の「入力チェック」を参照
 */
class TagValidator extends BaseValidator
{
    /** 先頭に"#"を付けない */
    private const TAG_PATTERN = '/^[^#]/u';

    protected string $tag = "";

    protected array $rules = [
        "tag" => [
            "label" => "タグ名",
            "pattern" => ["required", ["maxLength", 255], ["matchPattern", self::TAG_PATTERN]],
        ],
    ];

    /**
     * @param string $tag
     */
    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }
}
