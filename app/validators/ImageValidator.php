<?php

use App\Shared\Utils\BaseValidator;

/**
 * バリデーター: イメージ（アップロードされたファイル）
 * ルールは`/documents/api/images.md`の「入力チェック」を参照
 */
class ImageValidator extends BaseValidator
{
    /** ファイルサイズの上限（5MB） */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** 受け付ける形式（`getimagesize()`の形式 => 拡張子） */
    private const EXTENSIONS = [
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG => "png",
        IMAGETYPE_GIF => "gif",
        IMAGETYPE_WEBP => "webp",
    ];

    /** ファイルが送信されていればファイル名（未送信は空文字） */
    protected string $image = "";

    /** @var array<string,mixed> `$_FILES`形式の1ファイル分 */
    protected array $file = [];

    protected array $rules = [
        "image" => [
            "label" => "イメージ",
            "pattern" => ["required", ["maxFileSize", self::MAX_FILE_SIZE], "imageType"],
        ],
    ];

    /**
     * @param array{name: string, tmp_name: string, size: int, error: int} $file `$_FILES`形式の1ファイル分
     */
    public function __construct(array $file)
    {
        $this->file = $file;
        if (intval($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
        {
            $this->image = strval($file["name"] ?? "") !== "" ? strval($file["name"]) : "(no name)";
        }
    }

    /**
     * ファイルの中身から形式を判定し、拡張子を返す
     *
     * @param string $path
     * @return ?string 受け付けない形式・画像でない場合はnull
     */
    public static function detectExtension(string $path): ?string
    {
        if ($path === "" || !is_file($path))
        {
            return null;
        }
        $info = @getimagesize($path);
        return $info === false ? null : (self::EXTENSIONS[$info[2]] ?? null);
    }

    /**
     * ファイルサイズの上限チェック（サーバーの上限を超えた場合も含む）
     *
     * @param int $bytes
     * @return object
     */
    protected function maxFileSize(int $bytes): object
    {
        $error = intval($this->file["error"] ?? UPLOAD_ERR_NO_FILE);
        $tooLarge = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || intval($this->file["size"] ?? 0) > $bytes;
        if ($this->value !== "" && $tooLarge)
        {
            $mb = intdiv($bytes, 1024 * 1024);
            $this->setErrors("{$this->label}は{$mb}MB以内です");
        }
        return $this;
    }

    /**
     * 受け付ける形式（JPEG / PNG / GIF / WebP）かをチェック
     * サイズエラーなど、他のエラーがある場合はチェックしない
     *
     * @return object
     */
    protected function imageType(): object
    {
        if ($this->value === "" || isset($this->error_messages[$this->key]))
        {
            return $this;
        }
        $error = intval($this->file["error"] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || self::detectExtension(strval($this->file["tmp_name"] ?? "")) === null)
        {
            $this->setErrors("{$this->label}はJPEG・PNG・GIF・WebPのいずれかです");
        }
        return $this;
    }
}
