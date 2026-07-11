<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productsByCategory = [
            'Electronics' => [
                ['name' => 'iPhone 15 Pro', 'price' => 999.00, 'stock' => 45],
                ['name' => 'Samsung Galaxy S24', 'price' => 849.00, 'stock' => 38],
                ['name' => 'Sony WH-1000XM5 Headphones', 'price' => 349.00, 'stock' => 72],
                ['name' => 'Apple AirPods Pro', 'price' => 249.00, 'stock' => 91],
                ['name' => 'iPad Air 11"', 'price' => 599.00, 'stock' => 30],
                ['name' => 'MacBook Air M3', 'price' => 1299.00, 'stock' => 18],
                ['name' => 'Logitech MX Master 3S Mouse', 'price' => 99.00, 'stock' => 120],
                ['name' => 'Keychron K2 Mechanical Keyboard', 'price' => 89.00, 'stock' => 55],
            ],
            'Clothing' => [
                ['name' => 'Classic White T-Shirt', 'price' => 24.99, 'stock' => 200],
                ['name' => 'Slim Fit Chino Pants', 'price' => 59.99, 'stock' => 130],
                ['name' => 'Merino Wool Sweater', 'price' => 89.99, 'stock' => 65],
                ['name' => 'Running Shoes', 'price' => 119.99, 'stock' => 80],
            ],
            'Home & Kitchen' => [
                ['name' => 'Nespresso Vertuo Coffee Machine', 'price' => 179.00, 'stock' => 42],
                ['name' => 'Cast Iron Skillet 12"', 'price' => 44.99, 'stock' => 95],
                ['name' => 'Bamboo Cutting Board Set', 'price' => 34.99, 'stock' => 110],
                ['name' => 'Air Purifier HEPA H13', 'price' => 149.00, 'stock' => 28],
            ],
            'Books & Stationery' => [
                ['name' => 'Leuchtturm1917 Notebook A5', 'price' => 22.99, 'stock' => 180],
                ['name' => 'Pilot G2 Pens (12-pack)', 'price' => 14.99, 'stock' => 300],
            ],
            'Sports & Outdoors' => [
                ['name' => 'Yoga Mat Premium 6mm', 'price' => 49.99, 'stock' => 75],
                ['name' => 'Hydro Flask 32oz Water Bottle', 'price' => 44.95, 'stock' => 140],
            ],
        ];

        $categories = Category::pluck('id', 'name');

        foreach ($productsByCategory as $categoryName => $products) {
            foreach ($products as $product) {
                Product::create([
                    ...$product,
                    'category_id' => $categories[$categoryName] ?? null,
                ]);
            }
        }
    }
}
