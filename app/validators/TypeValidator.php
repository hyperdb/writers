<?php

use App\Shared\Utils\BaseValidator;

/**
 * バリデーター: コンテンツタイプ
 * ルールは`/documents/api/types.md`の「入力チェック」を参照
 */
class TypeValidator extends BaseValidator
{
    protected string $name = "";

    protected array $rules = [
        "name" => [
            "label" => "タイプ名",
            "pattern" => "required|maxLength:255",
        ],
    ];

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
