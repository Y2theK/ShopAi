<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_placing_an_order_returns_the_order_code_in_the_message(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 25, 'stock' => 10]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'delivery_address' => $this->deliveryAddress(),
        ]);

        $response->assertCreated();

        $order = Order::latest('id')->first();

        $this->assertNotNull($order->order_code);
        $this->assertStringStartsWith('ORD-', $order->order_code);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(
            "Your order has been placed successfully: {$order->order_code}",
            $response->json('message')
        );
        $this->assertSame($order->order_code, $response->json('data.order_code'));
        $this->assertSame(Order::STATUS_PENDING, $response->json('data.status'));
    }

    public function test_placing_an_order_stores_the_delivery_address(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 25, 'stock' => 10]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'delivery_address' => $this->deliveryAddress(),
        ]);

        $response->assertCreated();

        $order = Order::latest('id')->first();

        $this->assertSame('0912345678', $order->phone);
        $this->assertSame('123 Main Street', $order->address);
        $this->assertSame('Yangon', $order->city);
        $this->assertSame('Yangon Region', $order->state);
        $this->assertSame('Myanmar', $order->country);
        $this->assertSame('123 Main Street', $response->json('data.address'));
    }

    public function test_placing_an_order_requires_a_delivery_address(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 25, 'stock' => 10]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['delivery_address']);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_placing_an_order_requires_the_delivery_address_fields(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 25, 'stock' => 10]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'delivery_address' => ['phone' => '0912345678'],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'delivery_address.address',
            'delivery_address.city',
            'delivery_address.state',
            'delivery_address.country',
        ]);
    }

    /**
     * @return array{phone: string, secondary_phone: string, address: string, city: string, state: string, country: string}
     */
    private function deliveryAddress(): array
    {
        return [
            'phone' => '0912345678',
            'secondary_phone' => '',
            'address' => '123 Main Street',
            'city' => 'Yangon',
            'state' => 'Yangon Region',
            'country' => 'Myanmar',
        ];
    }

    public function test_orders_index_returns_only_the_authenticated_users_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownOrder = Order::factory()->for($user)->create();
        Order::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertOk();

        $orders = $response->json('data.data');

        $this->assertCount(1, $orders);
        $this->assertSame($ownOrder->order_code, $orders[0]['order_code']);
        $this->assertArrayHasKey('status', $orders[0]);
        $this->assertSame(1, $response->json('data.meta.total'));
    }

    public function test_orders_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }
}
