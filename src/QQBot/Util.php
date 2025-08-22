<?php
namespace App\QQBot;

final class Util
{
    /** 安全强随机 */
    public static function jitter(int $ms): int
    {
        return $ms + random_int(0, (int)($ms * 0.2));
    }
}