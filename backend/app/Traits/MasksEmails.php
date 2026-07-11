<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait MasksEmails
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
}
