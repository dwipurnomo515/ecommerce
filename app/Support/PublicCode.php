<?php

namespace App\Support;

/**
 * Short public references like ZTG-7K3M9QX2: easy to read out over the
 * phone, and they reveal nothing (an auto-increment id would tell anyone
 * how many orders the shop has, and invite guessing the next one).
 */
final class PublicCode
{
    // No 0/O, 1/I/L: they get mixed up when typed or read aloud.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function make(int $length = 8): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            // random_int: cryptographically secure, unlike rand().
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return 'ZTG-'.$code;
    }
}
