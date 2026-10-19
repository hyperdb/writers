<?php

use App\Shared\Utils\BaseValidator;

/**
 * バリデーター: 執筆者
 * ルールは`/documents/api/writers.md`の「入力チェック」を参照
 */
class WriterValidator extends BaseValidator
{
    protected string $user_id = "";
    protected string $nickname = "";

    protected array $rules = [
        "user_id" => [
            "label" => "ユーザーID",
            "pattern" => "required|maxLength:32",
        ],
        "nickname" => [
            "label" => "ニックネーム",
            "pattern" => "required|maxLength:32",
        ],
    ];

    /**
     * @param string $user_id
     * @param string $nickname
     */
    public function __construct(string $user_id, string $nickname)
    {
        $this->user_id = $user_id;
        $this->nickname = $nickname;
    }
}
