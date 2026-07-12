<?php

namespace App\Ai\Concerns;

trait ChatsViaConfiguredProviders
{
    /**
     * The providers to try in order, with automatic failover (see config/ai.php).
     *
     * @return array<string, string|null>
     */
    public function provider(): array
    {
        return array_filter(config('ai.chat.providers'), 'filled', ARRAY_FILTER_USE_KEY);
    }
}
