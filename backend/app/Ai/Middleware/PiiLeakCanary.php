<?php

namespace App\Ai\Middleware;

use App\Ai\PiiMasker;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;

class PiiLeakCanary
{
    public function __construct(private PiiMasker $masker) {}

    /**
     * Defense-in-depth around the agent prompt cycle.
     *
     * Inbound masking here is a second layer only: RememberConversation runs
     * OUTSIDE agent middleware and stores the prompt it received, so the
     * primary masking must happen in the controllers before prompt(). This
     * layer guards any future call site that skips the controllers.
     *
     * Outbound is advisory (like PromptInjectionDetector): a raw PII pattern
     * in a reply means an upstream masking layer has a hole, so log the
     * pattern names — never the matched text — and leave the reply untouched.
     */
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $prompt = $prompt->revise($this->masker->mask($prompt->prompt));

        return $next($prompt)->then(function ($response) use ($prompt) {
            $patterns = $this->masker->detect((string) $response->text);

            if ($patterns !== []) {
                Log::warning('Possible PII leak in agent reply', [
                    'agent' => $prompt->agent::class,
                    'patterns' => $patterns,
                ]);
            }
        });
    }
}
