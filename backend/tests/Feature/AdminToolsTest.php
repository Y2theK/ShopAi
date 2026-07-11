<?php

namespace Tests\Feature;

use App\Ai\ChartContext;
use App\Ai\Tools\BestSellingProductsTool;
use App\Ai\Tools\CustomerSummaryTool;
use App\Ai\Tools\InventorySummaryTool;
use App\Ai\Tools\LowStockProductsTool;
use App\Ai\Tools\MonthlySalesTrendTool;
use App\Ai\Tools\ProductLookupTool;
use App\Ai\Tools\RecentOrdersTool;
use App\Ai\Tools\SalesSummaryTool;
use App\Ai\Tools\TopCustomersTool;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_best_selling_products_are_ranked_by_units_sold(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->recycle($user)->create();

        $laptop = Product::factory()->create(['name' => 'Laptop']);
        $mouse = Product::factory()->create(['name' => 'Mouse']);
        Product::factory()->create(['name' => 'Unsold Keyboard']);

        OrderItem::factory()->recycle($order)->recycle($mouse)->create(['quantity' => 7, 'price' => 10]);
        OrderItem::factory()->recycle($order)->recycle($laptop)->create(['quantity' => 2, 'price' => 1000]);

        $context = new ChartContext;
        $result = (string) (new BestSellingProductsTool($context))->handle(new Request);

        $this->assertStringContainsString('Mouse: 7 units sold, $70.00 revenue', $result);
        $this->assertStringContainsString('Laptop: 2 units sold, $2,000.00 revenue', $result);
        $this->assertStringNotContainsString('Unsold Keyboard', $result);

        $charts = $context->getCharts();
        $this->assertCount(1, $charts);
        $this->assertSame('bar', $charts[0]['type']);
        $this->assertSame(['Mouse', 'Laptop'], $charts[0]['labels']);
        $this->assertSame([7, 2], $charts[0]['datasets'][0]['data']);
    }

    public function test_best_selling_products_respects_limit_and_days_filters(): void
    {
        $order = Order::factory()->create();

        $recent = Product::factory()->create(['name' => 'Recent Product']);
        $old = Product::factory()->create(['name' => 'Old Product']);

        OrderItem::factory()->recycle($order)->recycle($recent)->create(['quantity' => 1]);
        OrderItem::factory()->recycle($order)->recycle($old)->create([
            'quantity' => 99,
            'created_at' => now()->subDays(60),
        ]);

        $context = new ChartContext;
        $result = (string) (new BestSellingProductsTool($context))->handle(new Request(['days' => 30, 'limit' => 1]));

        $this->assertStringContainsString('Recent Product', $result);
        $this->assertStringNotContainsString('Old Product', $result);
    }

    public function test_best_selling_products_reports_when_there_are_no_sales(): void
    {
        $context = new ChartContext;
        $result = (string) (new BestSellingProductsTool($context))->handle(new Request);

        $this->assertSame('No sales recorded for the requested period.', $result);
        $this->assertCount(0, $context->getCharts());
    }

    public function test_low_stock_products_are_listed_below_the_threshold_lowest_first(): void
    {
        Product::factory()->create(['name' => 'Nearly Gone', 'stock' => 2]);
        Product::factory()->create(['name' => 'Sold Out', 'stock' => 0]);
        Product::factory()->create(['name' => 'Well Stocked', 'stock' => 50]);

        $context = new ChartContext;
        $result = (string) (new LowStockProductsTool($context))->handle(new Request);

        $this->assertStringContainsString('Sold Out: 0 left (OUT OF STOCK)', $result);
        $this->assertStringContainsString('Nearly Gone: 2 left', $result);
        $this->assertStringNotContainsString('Well Stocked', $result);

        $charts = $context->getCharts();
        $this->assertSame(['Sold Out', 'Nearly Gone'], $charts[0]['labels']);
        $this->assertSame([0, 2], $charts[0]['datasets'][0]['data']);
    }

    public function test_low_stock_products_accepts_a_custom_threshold(): void
    {
        Product::factory()->create(['name' => 'Mid Stock', 'stock' => 20]);

        $context = new ChartContext;
        $result = (string) (new LowStockProductsTool($context))->handle(new Request(['threshold' => 25]));

        $this->assertStringContainsString('Mid Stock: 20 left', $result);
    }

    public function test_monthly_sales_trend_groups_orders_by_month(): void
    {
        $user = User::factory()->create();

        Order::factory()->recycle($user)->create([
            'total_price' => 100,
            'created_at' => now()->startOfMonth(),
        ]);
        Order::factory()->recycle($user)->create([
            'total_price' => 50,
            'created_at' => now()->startOfMonth(),
        ]);
        Order::factory()->recycle($user)->create([
            'total_price' => 75,
            'created_at' => now()->subMonth()->startOfMonth(),
        ]);

        $context = new ChartContext;
        $result = (string) (new MonthlySalesTrendTool($context))->handle(new Request);

        $currentMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        $this->assertStringContainsString("{$currentMonth}: $150.00 revenue from 2 orders", $result);
        $this->assertStringContainsString("{$lastMonth}: $75.00 revenue from 1 orders", $result);

        $charts = $context->getCharts();
        $this->assertSame('line', $charts[0]['type']);
        $this->assertSame([$lastMonth, $currentMonth], $charts[0]['labels']);
        $this->assertSame([75.0, 150.0], $charts[0]['datasets'][0]['data']);
    }

    public function test_sales_summary_reports_totals_and_average_order_value(): void
    {
        $user = User::factory()->create();

        Order::factory()->recycle($user)->create(['total_price' => 100]);
        Order::factory()->recycle($user)->create(['total_price' => 50]);

        $result = (string) (new SalesSummaryTool)->handle(new Request);

        $this->assertStringContainsString('$150.00 total revenue across 2 orders', $result);
        $this->assertStringContainsString('average order value: $75.00', $result);
    }

    public function test_sales_summary_respects_the_days_filter(): void
    {
        $user = User::factory()->create();

        Order::factory()->recycle($user)->create(['total_price' => 100]);
        Order::factory()->recycle($user)->create([
            'total_price' => 900,
            'created_at' => now()->subDays(60),
        ]);

        $result = (string) (new SalesSummaryTool)->handle(new Request(['days' => 30]));

        $this->assertStringContainsString('$100.00 total revenue across 1 orders', $result);
    }

    public function test_inventory_summary_reports_counts_units_value_and_out_of_stock(): void
    {
        Product::factory()->create(['price' => 100, 'stock' => 0]);
        Product::factory()->create(['price' => 50, 'stock' => 2]);
        Product::factory()->create(['price' => 10, 'stock' => 10]);

        $result = (string) (new InventorySummaryTool)->handle(new Request);

        $this->assertStringContainsString('3 products in the catalog', $result);
        $this->assertStringContainsString('12 total units in stock', $result);
        $this->assertStringContainsString('total inventory value $200.00', $result);
        $this->assertStringContainsString('1 products out of stock', $result);
    }

    public function test_product_lookup_uses_historical_prices_for_revenue(): void
    {
        $laptop = Product::factory()->create(['name' => 'Gaming Laptop', 'price' => 1000, 'stock' => 4]);
        Product::factory()->create(['name' => 'Desk Chair']);

        // Sold at an old price of $900 — revenue must reflect that, not the current $1000
        OrderItem::factory()->recycle($laptop)->create(['quantity' => 3, 'price' => 900]);

        $result = (string) (new ProductLookupTool)->handle(new Request(['query' => 'Gaming']));

        $this->assertStringContainsString('Gaming Laptop: price $1000.00, 4 in stock, 3 units sold, $2,700.00 revenue', $result);
        $this->assertStringNotContainsString('Desk Chair', $result);
    }

    public function test_customer_summary_reports_totals_and_repeat_rate(): void
    {
        $repeatBuyer = User::factory()->create();
        $oneTimeBuyer = User::factory()->create();
        User::factory()->create(); // never ordered

        Order::factory()->recycle($repeatBuyer)->count(2)->create();
        Order::factory()->recycle($oneTimeBuyer)->create();

        $context = new ChartContext;
        $result = (string) (new CustomerSummaryTool($context))->handle(new Request);

        $this->assertStringContainsString('3 registered customers', $result);
        $this->assertStringContainsString('3 new this month', $result);
        $this->assertStringContainsString('1 have never placed an order', $result);
        $this->assertStringContainsString('2 have ordered at least once', $result);
        $this->assertStringContainsString('repeat-purchase rate 50%', $result);

        $charts = $context->getCharts();
        $this->assertCount(1, $charts);
        $this->assertSame([now()->format('Y-m')], $charts[0]['labels']);
        $this->assertSame([3], $charts[0]['datasets'][0]['data']);
    }

    public function test_top_customers_are_ranked_by_total_spent(): void
    {
        $bigSpender = User::factory()->create(['name' => 'Big Spender']);
        $smallSpender = User::factory()->create(['name' => 'Small Spender']);
        User::factory()->create(['name' => 'Window Shopper']);

        Order::factory()->recycle($bigSpender)->create(['total_price' => 500]);
        Order::factory()->recycle($bigSpender)->create(['total_price' => 250]);
        Order::factory()->recycle($smallSpender)->create(['total_price' => 100]);

        $context = new ChartContext;
        $result = (string) (new TopCustomersTool($context))->handle(new Request);

        $this->assertStringContainsString('Big Spender', $result);
        $this->assertStringContainsString('$750.00 across 2 orders', $result);
        $this->assertStringNotContainsString('Window Shopper', $result);

        $charts = $context->getCharts();
        $this->assertSame(['Big Spender', 'Small Spender'], $charts[0]['labels']);
        $this->assertSame([750.0, 100.0], $charts[0]['datasets'][0]['data']);
    }

    public function test_recent_orders_lists_latest_orders_with_customer_and_item_count(): void
    {
        $customer = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $order = Order::factory()->recycle($customer)->create(['total_price' => 42.5]);
        OrderItem::factory()->recycle($order)->count(2)->create();

        $result = (string) (new RecentOrdersTool)->handle(new Request);

        $this->assertStringContainsString("Order #{$order->id} — Jane Doe (jane@example.com): $42.50, 2 item(s)", $result);
    }

    public function test_recent_orders_filters_by_customer_name_or_email(): void
    {
        $jane = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $john = User::factory()->create(['name' => 'John Roe', 'email' => 'john@example.com']);

        Order::factory()->recycle($jane)->create();
        Order::factory()->recycle($john)->create();

        $result = (string) (new RecentOrdersTool)->handle(new Request(['customer' => 'jane']));

        $this->assertStringContainsString('Jane Doe', $result);
        $this->assertStringNotContainsString('John Roe', $result);
    }
}
