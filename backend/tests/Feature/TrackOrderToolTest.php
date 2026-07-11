<?php

namespace Tests\Feature;

use App\Ai\AgentContext;
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

        $result = (string) (new TrackOrderTool($user, new AgentContext))->handle(new Request([
            'order_code' => $order->order_code,
        ]));

        $this->assertStringContainsString($order->order_code, $result);
        $this->assertStringContainsString('Status: shipped', $result);
        $this->assertStringContainsString('Wireless Mouse x2', $result);
    }

    public function test_the_delivery_address_goes_to_the_context_but_not_the_tool_result(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'phone' => '0912345678',
            'secondary_phone' => '0987654321',
            'address' => '123 Main Street',
            'city' => 'Yangon',
            'state' => 'Yangon Region',
            'country' => 'Myanmar',
        ]);

        $context = new AgentContext;

        $result = (string) (new TrackOrderTool($user, $context))->handle(new Request([
            'order_code' => $order->order_code,
        ]));

        $this->assertStringNotContainsString('123 Main Street', $result);
        $this->assertStringNotContainsString('0912345678', $result);

        $orderInfo = $context->getOrderInfo();
        $this->assertSame($order->order_code, $orderInfo['order_code']);
        $this->assertSame('123 Main Street', $orderInfo['address']);
        $this->assertSame('0912345678', $orderInfo['phone']);
        $this->assertSame('0987654321', $orderInfo['secondary_phone']);
        $this->assertSame('Yangon', $orderInfo['city']);
        $this->assertSame('Yangon Region', $orderInfo['state']);
        $this->assertSame('Myanmar', $orderInfo['country']);
    }

    public function test_no_order_info_is_set_when_no_address_is_stored(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $context = new AgentContext;

        (new TrackOrderTool($user, $context))->handle(new Request([
            'order_code' => $order->order_code,
        ]));

        $this->assertNull($context->getOrderInfo());
    }

    public function test_the_order_code_lookup_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $result = (string) (new TrackOrderTool($user, new AgentContext))->handle(new Request([
            'order_code' => strtolower(" {$order->order_code} "),
        ]));

        $this->assertStringContainsString($order->order_code, $result);
    }

    public function test_it_does_not_reveal_other_users_orders(): void
    {
        $user = User::factory()->create();
        $otherOrder = Order::factory()->create();

        $result = (string) (new TrackOrderTool($user, new AgentContext))->handle(new Request([
            'order_code' => $otherOrder->order_code,
        ]));

        $this->assertStringContainsString('No order with code', $result);
        $this->assertStringNotContainsString('Status:', $result);
    }

    public function test_it_handles_unknown_order_codes(): void
    {
        $user = User::factory()->create();

        $result = (string) (new TrackOrderTool($user, new AgentContext))->handle(new Request([
            'order_code' => 'ORD-DOESNOTX',
        ]));

        $this->assertStringContainsString('No order with code ORD-DOESNOTX was found', $result);
    }
}
