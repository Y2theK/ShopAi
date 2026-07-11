<?php

namespace Tests\Feature;

use App\Ai\Tools\TrackOrderTool;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class TrackOrderToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_the_status_and_items_for_the_users_own_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Wireless Mouse', 'price' => 30]);
        $order = Order::factory()->for($user)->create(['status' => Order::STATUS_SHIPPED]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2, 'price' => 30]);

        $result = (string) (new TrackOrderTool($user))->handle(new Request([
            'order_code' => $order->order_code,
        ]));

        $this->assertStringContainsString($order->order_code, $result);
        $this->assertStringContainsString('Status: shipped', $result);
        $this->assertStringContainsString('Wireless Mouse x2', $result);
    }

    public function test_the_order_code_lookup_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $result = (string) (new TrackOrderTool($user))->handle(new Request([
            'order_code' => strtolower(" {$order->order_code} "),
        ]));

        $this->assertStringContainsString($order->order_code, $result);
    }

    public function test_it_does_not_reveal_other_users_orders(): void
    {
        $user = User::factory()->create();
        $otherOrder = Order::factory()->create();

        $result = (string) (new TrackOrderTool($user))->handle(new Request([
            'order_code' => $otherOrder->order_code,
        ]));

        $this->assertStringContainsString('No order with code', $result);
        $this->assertStringNotContainsString('Status:', $result);
    }

    public function test_it_handles_unknown_order_codes(): void
    {
        $user = User::factory()->create();

        $result = (string) (new TrackOrderTool($user))->handle(new Request([
            'order_code' => 'ORD-DOESNOTX',
        ]));

        $this->assertStringContainsString('No order with code ORD-DOESNOTX was found', $result);
    }
}
