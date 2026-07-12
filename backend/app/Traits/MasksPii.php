<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Field-level PII masking for values interpolated into AI tool results.
 *
 * Field rule: full street addresses and unmasked phone numbers must never be
 * interpolated into tool results — they reach the LLM provider and the stored
 * conversation transcript. Deliver them to the user via the AgentContext /
 * ChartContext side channels instead. City, state, and country may appear in
 * aggregate admin contexts only (e.g. "top shipping regions").
 */
trait MasksPii
{
    /**
     * Mask the local part of an email address so raw addresses never
     *
     * reach the LLM provider or conversation storage (jane@x.com → j***@x.com).
     */
    protected function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return Str::substr($local, 0, 1).'***@'.$domain;
    }

    /**
     * Mask a phone number down to its last two digits (0912345678 → ********78).
     */
    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (Str::length($digits) < 4) {
            return str_repeat('*', Str::length($phone));
        }

        return str_repeat('*', Str::length($digits) - 2).Str::substr($digits, -2);
    }
}
