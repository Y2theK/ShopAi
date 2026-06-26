<?php

namespace App\Ai\Tools;

use App\Ai\AgentContext;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListProductsTool implements Tool
{
    public function __construct(private AgentContext $context) {}

    public function description(): Stringable|string
    {
        return 'List all products. Optionally filter to show only products currently in stock.';
    }

    public function handle(Request $request): Stringable|string
    {
        $inStockOnly = $request->boolean('in_stock_only');

        $products = Product::when($inStockOnly, fn ($q) => $q->where('stock', '>', 0))->get();

        if ($products->isEmpty()) {
            return 'No products available.';
        }

        $lines = [];

        foreach ($products as $product) {
            $this->context->addProduct([
                'id' => $product->id,
                'name' => $product->name,
                'price' => (string) $product->price,
                'stock' => $product->stock,
            ]);

            $lines[] = "ID: {$product->id}, Name: {$product->name}, Price: \${$product->price}, Stock: {$product->stock}";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'in_stock_only' => $schema->boolean(),
        ];
    }
}
