<?php

namespace App\Ai;

class PiiMasker
{
    /**
     * Structured PII patterns, most specific first, keyed by a loggable name.
     * Each match is replaced with a typed placeholder like [nrc-redacted].
     *
     * Names, dates of birth, and free-form street addresses are deliberately
     * excluded: they are not reliably pattern-detectable and would mangle
     * legitimate shopping messages. Address fields are protected at the field
     * level instead (see MasksPii and the AgentContext side channel).
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        'email' => '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
        'nrc' => '/\b\d{1,2}\s*\/\s*[A-Za-z]{2,10}\s*\(\s*(?:NAING|EH|PYU|[NEPCT])\s*\)\s*\d{6}\b/i',
        'nrc_mm' => '/[\x{1040}-\x{1049}]{1,2}\s*\/\s*[\x{1000}-\x{103F}]{1,15}\s*\(\s*[\x{1000}-\x{103F}]{1,10}\s*\)\s*[\x{1040}-\x{1049}]{6}/u',
        'ssn' => '/\b\d{3}-\d{2}-\d{4}\b/',
        'mm_phone' => '/(?<!\d)(?:\+?95[\s-]?9|09)(?:[\s-]?\d){7,9}(?!\d)/',
        'passport' => '/\b[A-Z]{1,2}\d{6,7}\b/',
        'digit_run' => '/(?<!\d)\d(?:[\s-]?\d){8,}(?!\d)/',
    ];

    /**
     * Replace every detected PII value with a typed placeholder.
     */
    public function mask(string $text): string
    {
        foreach (self::PATTERNS as $name => $pattern) {
            $text = (string) preg_replace($pattern, "[{$name}-redacted]", $text);
        }

        return $text;
    }

    /**
     * Return the names of all matching PII patterns — never the matched
     * values — so detections can be logged safely.
     *
     * @return list<string>
     */
    public function detect(string $text): array
    {
        $matches = [];

        foreach (self::PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $matches[] = $name;
            }
        }

        return $matches;
    }
}
