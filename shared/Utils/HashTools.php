<?php

namespace App\Shared\Utils;

use Hidehalo\Nanoid\Client;
use Sqids\Sqids;

/**
 * ハッシュなどのツールをまとめたライブラリ
 * 
 * @version 1.0.0
 * 
 * @method string generateUuidV4()
 * @method string getShortUuidV4(string $uuid = "")
 * @method string expandShortUuidV4(string $shortUuid)
 * @method string generateNanoId()
 */
class HashTools
{
    /** Sqidsのインスタンス（初回利用時に.envの`MY_ALPHABET`で初期化） */
    private static ?Sqids $sqids = null;

    /**
     * .envの`MY_ALPHABET`を使ったSqidsのインスタンスを取得する
     * 既定のアルファベットでは誰でも復元できるため、未設定の場合は例外とする
     *
     * @return Sqids
     * @throws \RuntimeException `MY_ALPHABET`が未設定の場合
     */
    private static function sqids(): Sqids
    {
        if (self::$sqids === null)
        {
            $alphabet = strval($_ENV['MY_ALPHABET'] ?? "");
            if ($alphabet === "")
            {
                throw new \RuntimeException("MY_ALPHABET is not set in .env");
            }
            // 最小の長さを揃える
            self::$sqids = new Sqids($alphabet, 8);
        }
        return self::$sqids;
    }

    /**
     * UUID V4を生成する
     *
     * @return string
     */
    public static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        // バージョンを 4 に設定 (第6バイトの上位4ビットを 0100 に)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // バリアントを RFC 4122 に設定 (第8バイトの上位2ビットを 10 に)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        // 16進数に変換してハイフンを挿入
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * UUID V4を短縮化する
     *
     * @param string $uuid
     * @return string
     */
    public static function getShortUuidV4(string $uuid = ""): string
    {
        // 引数が指定されなければUUID V4を生成する
        $uuid = empty($uuid) ? self::generateUuidV4() : $uuid;
        // ハイフンを削除してバイナリ（16バイト）に変換
        $binary = hex2bin(str_replace('-', '', $uuid));

        // Base64エンコードし、URLセーフな文字に置換してパディング(=)を削る
        $base64 = base64_encode($binary);
        $base64url = str_replace(['+', '/', '='], ['-', '_', ''], $base64);

        return $base64url; // 22文字
    }

    /**
     * 短縮化されたUUID V4を復元する
     *
     * @param string $shortUuid
     * @return string
     */
    public static function expandShortUuidV4(string $shortUuid): string
    {
        // URLセーフ文字を通常のエスケープに戻す
        $base64 = str_replace(['-', '_'], ['+', '/'], $shortUuid);
        // パディング(=)を復元
        $base64 = str_pad($base64, 4 * ceil(strlen($base64) / 4), '=', STR_PAD_RIGHT);

        // バイナリに戻して16進数文字列へ
        $hex = bin2hex(base64_decode($base64));

        // ハイフンを再挿入してUUIDの形に戻す
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    /**
     * パスワードをハッシュ化する
     *
     * @param string $password
     * @return string
     */
    public static function generateHash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }


    /**
     * NanoIDを生成する
     * 既存パッケージ（`hidehalo/nanoid-php`）に丸投げ
     *
     * @return string
     */
    public static function generateNanoId(): string
    {
        $client = new Client();

        return $client->generateId();
    }

    /**
     * 数値からsqidsを生成する
     * 自動採番の連番を秘匿化するために利用
     *
     * @param integer $id
     * @return string
     */
    public static function generateSqid(int $id): string
    {
        $sqids = self::sqids();

        $container = [$id];
        return $sqids->encode($container);
    }

    /**
     * Sqidsを数値に戻す
     *
     * @param string $sqid
     * @return int
     */
    public static function decodeSqid(string $sqid): int
    {
        $sqids = self::sqids();
        $container = $sqids->decode($sqid);
        /**
         * [id, dummy, ....]の形に戻して先頭を取り出す
         */
        return count($container) <= 0 ? 0 : $container[0];
    }
}
