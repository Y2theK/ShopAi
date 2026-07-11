<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_categories_endpoint_lists_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        Category::factory()->create(['name' => 'Clothing', 'slug' => 'clothing']);

        $this->actingAs($user)->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Electronics', 'slug' => 'electronics'])
            ->assertJsonFragment(['name' => 'Clothing', 'slug' => 'clothing']);
    }

    public function test_categories_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
    }

    public function test_products_can_be_filtered_by_category_slug(): void
    {
        $user = User::factory()->create();
        $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $clothing = Category::factory()->create(['name' => 'Clothing', 'slug' => 'clothing']);
        Product::factory()->create(['name' => 'Laptop', 'category_id' => $electronics->id]);
        Product::factory()->create(['name' => 'T-Shirt', 'category_id' => $clothing->id]);

        $this->actingAs($user)->getJson('/api/v1/products?category=electronics')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Laptop'])
            ->assertJsonMissing(['name' => 'T-Shirt']);
    }

    public function test_product_search_matches_category_name(): void
    {
        $user = User::factory()->create();
        $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        Product::factory()->create(['name' => 'Laptop', 'category_id' => $electronics->id]);
        Product::factory()->create(['name' => 'T-Shirt']);

        $this->actingAs($user)->getJson('/api/v1/products?search=Electronics')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Laptop'])
            ->assertJsonMissing(['name' => 'T-Shirt']);
    }

    public function test_product_listing_includes_category(): void
    {
        $user = User::factory()->create();
        $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        Product::factory()->create(['name' => 'Laptop', 'category_id' => $electronics->id]);

        $this->actingAs($user)->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Electronics', 'slug' => 'electronics']);
    }
}
