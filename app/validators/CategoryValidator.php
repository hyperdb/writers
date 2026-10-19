<?php

use App\Shared\Utils\BaseValidator;

/**
 * バリデーター: カテゴリー
 * ルールは`/documents/api/categories.md`の「入力チェック」を参照
 * 親カテゴリーの存在・循環のチェックはDBを参照するためコントローラーで行う
 */
class CategoryValidator extends BaseValidator
{
    protected string $category = "";

    protected array $rules = [
        "category" => [
            "label" => "カテゴリー名",
            "pattern" => "required|maxLength:255",
        ],
    ];

    /**
     * @param string $category
     */
    public function __construct(string $category)
    {
        $this->category = $category;
    }
}
