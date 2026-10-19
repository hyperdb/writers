<?php

namespace App\Shared\Utils;

/**
 * バリデーション基底クラス
 * 
 * ---> バリデーションメソッド
 * @method object required()
 * @method object minLength(int $length)
 * @method object maxLength(int $length)
 * @method object matchPatterb(string $pattern)
 * @method object useAllChar()
 */
class BaseValidator
{
    /**
     * 組み込みパターン
     */
    // ---> 英大文字・英小文字・数字・記号が1文字以上存在するか
    protected static string $use_all_chars = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\x21-\x2f\x3a-\x40\x5b-\x60\x7b-\x7e]).+$/';

    /**
     * チェックする値
     * 
     * @var mixed
     */
    protected mixed $value = null;
    /**
     * 項目を識別するキー
     *
     * @var string
     */
    protected string $key = "";
    /**
     * 表示するラベル名
     * 
     * @var mixed
     */
    protected mixed $label = "";
    /**
     * エラー格納用配列
     *
     * @var array<string, array>
     */
    protected array $error_messages = [];

    /** @var array<string, array{label: string, pattern: string|array}> プロパティ名をキーとしたバリデーションルール */
    protected array $rules = [];

    /**
     * $rulesの定義に従ってバリデーションを実行
     *
     * @param array<int, string> $properties チェックする項目（省略時は全項目）
     * @return array
     */
    public function valid(array $properties = []): array
    {
        foreach ($this->rules as $property => $rule)
        {
            // ---> 項目の指定があれば、指定された項目のみチェックする
            if (count($properties) > 0 && !in_array($property, $properties, true))
            {
                continue;
            }
            $this->setValue(
                $this->{$property} ?? "",
                $property ?? "unknown",
                $rule["label"] ?? $property
            );
            $this->applyPattern($rule["pattern"] ?? "");
        }

        return $this->getErrors();
    }

    /**
     * "required|minLength:8"のようなルール文字列、または["matchPattern", "/^(a|b)$/"]のように
     * "|"や","を含む引数（正規表現など）を安全に渡すための配列表記を解釈し、対応するチェックメソッドを呼び出す
     *
     * @param string|array $pattern
     * @return void
     */
    private function applyPattern(string|array $pattern): void
    {
        $conditions = is_array($pattern) ? $pattern : explode("|", $pattern);

        foreach ($conditions as $condition)
        {
            if ($condition === "")
            {
                continue;
            }

            if (is_array($condition))
            {
                // ---> [メソッド名, 引数1, 引数2, ...] 形式
                $method = array_shift($condition);
                $params = $condition;
            }
            else
            {
                [$method, $paramStr] = array_pad(explode(":", $condition, 2), 2, null);
                $params = $paramStr !== null ? explode(",", $paramStr) : [];
            }

            if (!method_exists($this, $method))
            {
                continue;
            }

            $this->$method(...$params);
        }
    }

    /**
     * 検証する値をセット
     *
     * @param string $value
     * @param string $label
     * @return object
     */
    protected function setValue(string $value, string $key, string $label): object
    {
        $this->value = $value;
        $this->key = $key;
        $this->label = $label;

        return $this;
    }

    /**
     * エラーメッセージの取得
     *
     * @return array
     */
    protected function getErrors(): array
    {
        return $this->error_messages;
    }

    /**
     * エラーメッセージを追加する
     *
     * @param string $message
     * @return void
     */
    protected function setErrors(string $message): void
    {
        if (!isset($this->error_messages[$this->key]))
        {
            $this->error_messages[$this->key] = [];
        }
        $this->error_messages[$this->key][] = $message;
    }

    /**
     * 必須チェック
     *
     * @return object
     */
    protected function required(): object
    {
        // ---> 文字列のみをチェック対象に
        if (is_string($this->value))
        {
            if (empty($this->value))
            {
                $this->setErrors("{$this->label}は必須項目です");
            }
        }
        return $this;
    }

    /**
     * 最小文字数チェック
     *
     * @return object
     */
    protected function minLength(int $length): object
    {
        // ---> 文字列のみをチェック対象に
        if (is_string($this->value))
        {
            if (mb_strlen($this->value) < $length)
            {
                $this->setErrors("{$this->label}は{$length}文字以上です");
            }
        }
        return $this;
    }

    /**
     * 最小文字数チェック
     *
     * @return object
     */
    protected function maxLength(int $length): object
    {
        // ---> 文字列のみをチェック対象に
        if (is_string($this->value))
        {
            if (mb_strlen($this->value) > $length)
            {
                $this->setErrors("{$this->label}は{$length}文字以内です");
            }
        }
        return $this;
    }

    /**
     * 正規表現にマッチするかをチェック
     *
     * @param string $pattern
     * @return object
     */
    protected function matchPattern(string $pattern): object
    {
        // ---> 文字列のみをチェック対象に
        if (is_string($this->value))
        {
            if (!preg_match($pattern, $this->value))
            {
                $this->setErrors("{$this->label}は形式が正しくありません");
            }
        }
        return $this;
    }

    /**
     * すべての文字種を使っているか
     *
     * @return object
     */
    protected function useAllChar(): object
    {
        // ---> 文字列のみをチェック対象に
        if (is_string($this->value))
        {
            // ---> 英大文字・英小文字・数字・記号が1文字以上必要
            // $pattern = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\x21-\x2f\x3a-\x40\x5b-\x60\x7b-\x7e]).+$/';
            $pattern = self::$use_all_chars;
            if (!preg_match($pattern, $this->value))
            {
                $this->setErrors("{$this->label}には英大文字・英小文字・数字・記号が1文字以上必要です");
            }
        }
        return $this;
    }
}
