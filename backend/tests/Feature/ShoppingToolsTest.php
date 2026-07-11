<?php

namespace Tests\Feature;

use App\Ai\AgentContext;
use App\Ai\Tools\ListProductsTool;
use App\Ai\Tools\SearchProductsTool;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ShoppingToolsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_search_caps_results_at_five_best_sellers(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        Product::factory()->count(6)->create(['category_id' => $category->id, 'stock' => 10]);
        $bestSeller = Product::factory()->create([
            'name' => 'Hot Item',
            'category_id' => $category->id,
            'price' => 10,
            'stock' => 50,
        ]);
        $this->createSale($bestSeller, 10);

        $context = new AgentContext;

        $result = (string) (new SearchProductsTool($context))->handle(new Request([
            'query' => '',
            'category' => 'electronics',
        ]));

        $this->assertSame(5, substr_count($result, 'ID: '));
        $this->assertCount(5, $context->getProducts());
        $this->assertStringContainsString('2 more match', $result);
        $this->assertStringStartsWith("ID: {$bestSeller->id}, Name: Hot Item", $result);
    }

    public function test_list_caps_results_at_five_best_sellers(): void
    {
        Product::factory()->count(7)->create(['stock' => 10]);

        $context = new AgentContext;

        $result = (string) (new ListProductsTool($context))->handle(new Request([]));

        $this->assertSame(5, substr_count($result, 'ID: '));
        $this->assertCount(5, $context->getProducts());
        $this->assertStringContainsString('2 more available', $result);
    }

    private function createSale(Product $product, int $quantity): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'total_price' => (float) $product->price * $quantity,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->price,
        ]);
    }
}
