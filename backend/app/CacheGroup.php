<?php

namespace App;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Group-invalidated caching for stores without tag support (e.g. database).
 *
 * Every key is prefixed with the group's current version; flushing a group
 * bumps the version, orphaning the old keys until their TTL expires.
 */
class CacheGroup
{
    private function __construct(private readonly string $group) {}

    public static function for(string $group): self
    {
        return new self($group);
    }

    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        return Cache::remember($this->qualifiedKey($key), $ttl, $callback);
    }

    public function get(string $key): mixed
    {
        return Cache::get($this->qualifiedKey($key));
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        Cache::put($this->qualifiedKey($key), $value, $ttl);
    }

    public function flush(): void
    {
        Cache::forever($this->versionKey(), $this->version() + 1);
    }

    private function qualifiedKey(string $key): string
    {
        return "{$this->group}:v{$this->version()}:{$key}";
    }

    private function version(): int
    {
        return (int) Cache::get($this->versionKey(), 1);
    }

    private function versionKey(): string
    {
        return "cache-group:{$this->group}:version";
    }
}
