<?php

namespace Tests\Feature;

use App\Ai\ChartContext;
use App\Ai\Tools\MonthlySalesTrendTool;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AdminToolCachingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repeated_calls_return_the_cached_result_and_replay_the_chart(): void
    {
        $user = User::factory()->create();
        Order::factory()->recycle($user)->create(['total_price' => 100]);

        $firstContext = new ChartContext;
        $firstResult = (string) (new MonthlySalesTrendTool($firstContext))->handle(new Request);

        $this->assertCount(1, $firstContext->getCharts());

        // New data lands after the result was cached.
        Order::factory()->recycle($user)->create(['total_price' => 50]);

        $secondContext = new ChartContext;
        $secondResult = (string) (new MonthlySalesTrendTool($secondContext))->handle(new Request);

        // The stale cached result is returned...
        $this->assertSame($firstResult, $secondResult);
        $this->assertStringContainsString('$100.00 revenue', $secondResult);

        // ...and the chart side-channel is replayed on the cache hit.
        $this->assertSame($firstContext->getCharts(), $secondContext->getCharts());
    }

    public function test_different_parameters_bypass_the_cached_entry(): void
    {
        $user = User::factory()->create();
        Order::factory()->recycle($user)->create(['total_price' => 100]);

        (new MonthlySalesTrendTool(new ChartContext))->handle(new Request);

        Order::factory()->recycle($user)->create(['total_price' => 50]);

        // A different months value is a different cache key, so it computes fresh totals.
        $result = (string) (new MonthlySalesTrendTool(new ChartContext))->handle(new Request(['months' => 3]));

        $this->assertStringContainsString('$150.00 revenue', $result);
    }
}
