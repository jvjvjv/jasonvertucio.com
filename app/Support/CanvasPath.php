<?php

namespace App\Support;

class CanvasPath
{
    public const PLACEHOLDER = '{canvas.path}';

    /**
     * The configured Canvas path as a root-relative URL path.
     */
    public static function href(): string
    {
        return '/'.trim((string) config('canvas.path'), '/');
    }

    /**
     * Replace the Canvas path placeholder in raw JSON or text.
     */
    public static function fill(string $contents): string
    {
        return str_replace(self::PLACEHOLDER, self::href(), $contents);
    }
}
