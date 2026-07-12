<?php

namespace App\Ai;

use Normalizer;

class TextNormalizer
{
    /**
     * Fold unicode evasion tricks before pattern matching: NFKC turns
     * compatibility characters (fullwidth digits ０９ → 09) into their
     * canonical forms, and zero-width/bidi-control characters that could
     * split a regex match ("ig​nore") are stripped entirely.
     */
    public static function normalize(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
        }

        return (string) preg_replace(
            '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u',
            '',
            $text
        );
    }
}
