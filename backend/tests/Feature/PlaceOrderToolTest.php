<?php

namespace Tests\Feature;

use App\Ai\AgentContext;
use App\Ai\Tools\PlaceOrderTool;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class PlaceOrderToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_valid_order_is_placed_and_stock_is_decremented(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Mouse', 'price' => 10, 'stock' => 50]);

        $result = (string) (new PlaceOrderTool($user, new AgentContext))->handle(new Request([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $this->assertStringContainsString('placed successfully', $result);
        $this->assertSame(48, $product->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    public function test_quantities_above_the_per_item_cap_are_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Mouse', 'price' => 10, 'stock' => 100]);

        $result = (string) (new PlaceOrderTool($user, new AgentContext))->handle(new Request([
            'items' => [['product_id' => $product->id, 'quantity' => 21]],
        ]));

        $this->assertStringContainsString('must be between 1 and 20', $result);
        $this->assertSame(0, Order::count());
        $this->assertSame(100, $product->fresh()->stock);
    }

    public function test_orders_with_more_than_ten_distinct_products_are_rejected(): void
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(11)->create(['price' => 10, 'stock' => 100]);

        $items = $products->map(fn (Product $p) => ['product_id' => $p->id, 'quantity' => 1])->all();

        $result = (string) (new PlaceOrderTool($user, new AgentContext))->handle(new Request(['items' => $items]));

        $this->assertStringContainsString('at most 10 different products', $result);
        $this->assertSame(0, Order::count());
    }

    public function test_duplicate_product_lines_are_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10, 'stock' => 100]);

        $result = (string) (new PlaceOrderTool($user, new AgentContext))->handle(new Request([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]));

        $this->assertStringContainsString('may only appear once per order', $result);
        $this->assertSame(0, Order::count());
    }

    public function test_a_successful_order_suggests_other_popular_items(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Mouse', 'price' => 10, 'stock' => 50]);
        Product::factory()->create(['name' => 'Keyboard', 'price' => 40, 'stock' => 30]);

        $context = new AgentContext;

        $result = (string) (new PlaceOrderTool($user, $context))->handle(new Request([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $this->assertStringContainsString('might also like', $result);
        $this->assertStringContainsString('Keyboard', $result);
        $this->assertStringNotContainsString('- Mouse', $result);

        $suggestedNames = array_column($context->getProducts(), 'name');
        $this->assertContains('Keyboard', $suggestedNames);
        $this->assertNotContains('Mouse', $suggestedNames);
    }

    public function test_suggestions_come_from_the_same_category_as_the_order(): void
    {
        $user = User::factory()->create();
        $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $clothing = Category::factory()->create(['name' => 'Clothing', 'slug' => 'clothing']);
        $product = Product::factory()->create(['name' => 'Mouse', 'price' => 10, 'stock' => 50, 'category_id' => $electronics->id]);
        Product::factory()->create(['name' => 'Keyboard', 'price' => 40, 'stock' => 30, 'category_id' => $electronics->id]);
        Product::factory()->create(['name' => 'T-Shirt', 'price' => 15, 'stock' => 80, 'category_id' => $clothing->id]);

        $context = new AgentContext;

        $result = (string) (new PlaceOrderTool($user, $context))->handle(new Request([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $this->assertStringContainsString('Keyboard', $result);
        $this->assertStringNotContainsString('T-Shirt', $result);

        $suggestedNames = array_column($context->getProducts(), 'name');
        $this->assertContains('Keyboard', $suggestedNames);
        $this->assertNotContains('T-Shirt', $suggestedNames);
    }

    public function test_orders_above_the_total_cap_are_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Server Rack', 'price' => 6000, 'stock' => 10]);

        $result = (string) (new PlaceOrderTool($user, new AgentContext))->handle(new Request([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $this->assertStringContainsString('exceeds the $10,000 assistant checkout limit', $result);
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $product->fresh()->stock);
    }
}
