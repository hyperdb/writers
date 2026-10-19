<?php

namespace App\Enums;

enum HttpStatus: int
{
    case OK = 200;  // リクエストOK
    case Created = 201; // リソースが作成された場合

    case MovedPermanently = 301;    // URLが変更された
    case Found = 302;   // URLが一時的に変更された（処理の都合でどこかに飛ばす）

    case BadRequest = 400;  // クライアント側のエラー
    case Unauthorized = 401;    // 認証エラー
    case Forbidden = 403;   // 要認証ページ
    case NotFound = 404;    // ページが見つからない
    case Conflict = 409;    // 一意であるべき値の重複

    case InternalServerError = 500; // サーバー側のエラー
    case NotImplemented = 501;  // メソッドが未対応 GET/HEADのみ必須
    case BadGateway = 502;  // 後続サービスのエラー
}
