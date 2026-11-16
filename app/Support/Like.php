<?php

namespace App\Support;

/**
 * Safe LIKE / ILIKE patterns from user input.
 */
final class Like
{
    /**
     * In LIKE patterns % and _ are wildcards. Searching for "100%" must look
     * for the text "100%", not "100 followed by anything". Postgres uses \
     * as the default escape character.
     */
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** "%kaos%" with the input escaped: matches it anywhere. */
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }
}
