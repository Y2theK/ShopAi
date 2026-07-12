<?php

namespace App\Ai\Middleware;

use App\Ai\PromptInjectionDetector;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;

class PromptInjectionCanary
{
    public function __construct(private PromptInjectionDetector $detector) {}

    /**
     * Advisory-only, like the controller-side detector: logs the pattern name
     * and never blocks or mutates — containment is enforced in code. Exists so
     * call sites that skip the chat controllers (jobs, commands) are covered.
     */
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        if ($pattern = $this->detector->detect($prompt->prompt)) {
            Log::warning('Possible prompt injection reaching agent', [
                'agent' => $prompt->agent::class,
                'pattern' => $pattern,
            ]);
        }

        return $next($prompt);
    }
}
