<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat Rate Limits
    |--------------------------------------------------------------------------
    |
    | Per-user caps on the AI chat endpoints. The per-minute limit smooths
    | bursts; the per-day limit is the spend ceiling per account, since every
    | message triggers an LLM call. Raise the daily limits in local .env
    | files to avoid hitting the cap while developing.
    |
    */

    'rate_limits' => [
        'per_minute' => env('CHAT_PER_MINUTE_LIMIT', 10),
        'per_day' => env('CHAT_PER_DAY_LIMIT', 50),

        'admin_per_minute' => env('ADMIN_CHAT_PER_MINUTE_LIMIT', 20),
        'admin_per_day' => env('ADMIN_CHAT_PER_DAY_LIMIT', 200),
    ],

];
