<?php

namespace App\Ai\Concerns;

use App\Ai\ChartContext;
use App\CacheGroup;
use Closure;

trait CachesToolResults
{
    /**
     * Cache a tool result together with the charts it pushed to the ChartContext,
     * replaying those charts into the context on a cache hit so the frontend
     * still receives them when the tool body is skipped.
     */
    protected function cached(string $key, Closure $compute, int $ttl = 300, ?ChartContext $context = null): string
    {
        $cache = CacheGroup::for('ai-tools');

        $hit = $cache->get($key);

        if (is_array($hit)) {
            foreach ($hit['charts'] as $chart) {
                $context?->addChart($chart);
            }

            return $hit['result'];
        }

        $chartsBefore = $context ? count($context->getCharts()) : 0;
        $result = (string) $compute();
        $charts = $context ? array_slice($context->getCharts(), $chartsBefore) : [];

        $cache->put($key, ['result' => $result, 'charts' => $charts], $ttl);

        return $result;
    }
}
