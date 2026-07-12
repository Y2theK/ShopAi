<?php

namespace App\Traits;

use App\Ai\PromptInjectionDetector;
use Illuminate\Support\Facades\Log;

/**
 * Advisory canary for indirect prompt injection: tool results that embed
 * customer-controlled text (e.g. registration names) bypass the inbound
 * message detector, so flag injection markers here before the result is
 * handed to the model. Detection only — the result is always returned.
 */
trait FlagsSuspiciousToolData
{
    protected function flagSuspiciousToolData(string $result): string
    {
        if ($pattern = (new PromptInjectionDetector)->detect($result)) {
            Log::warning('Possible prompt injection in tool data', [
                'tool' => static::class,
                'pattern' => $pattern,
            ]);
        }

        return $result;
    }
}
