<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_product_listing_is_served_from_cache_within_the_ttl(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Original Product']);

        $this->actingAs($user)->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Original Product']);

        Product::factory()->create(['name' => 'Sneaky New Product']);

        // Still cached — the new product is not visible yet.
        $this->actingAs($user)->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Sneaky New Product']);
    }

    public function test_placing_an_order_flushes_the_product_cache(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Widget', 'price' => 10, 'stock' => 5]);

        // Prime the cache with stock at 5.
        $this->actingAs($user)->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonFragment(['stock' => 5]);

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => [
                'phone' => '0912345678',
                'address' => '123 Main Street',
                'city' => 'Yangon',
                'state' => 'Yangon Region',
                'country' => 'Myanmar',
            ],
        ])->assertCreated();

        // The order flushed the tag, so the fresh stock level is served.
        $this->actingAs($user)->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonFragment(['stock' => 3]);
    }

    public function test_different_queries_use_distinct_cache_entries(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Alpha Keyboard']);
        Product::factory()->create(['name' => 'Beta Mouse']);

        $this->actingAs($user)->getJson('/api/v1/products?search=Alpha')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Alpha Keyboard'])
            ->assertJsonMissing(['name' => 'Beta Mouse']);

        $this->actingAs($user)->getJson('/api/v1/products?search=Beta')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Beta Mouse'])
            ->assertJsonMissing(['name' => 'Alpha Keyboard']);
    }
}
