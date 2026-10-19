<?php

namespace App\Enums;

/**
 * コンテンツの公開状態
 */
enum ContentStatus: string
{
    case Draft = 'draft';   // 下書き
    case Published = 'published';   // 公開（published_atが現在日時以前なら読者に公開）
}
