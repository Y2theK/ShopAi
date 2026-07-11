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
    private const MAX_RESULTS = 5;

    public function __construct(private AgentContext $context) {}

    public function description(): Stringable|string
    {
        return 'List products. Optionally filter by category name (e.g. Electronics, Clothing) or to show only products currently in stock. Returns at most 5 results, best sellers first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $inStockOnly = $request->boolean('in_stock_only');
        $category = $request['category'] ?? null;

        $matches = Product::with('category')
            ->inCategory($category)
            ->when($inStockOnly, fn ($q) => $q->where('stock', '>', 0));

        $totalMatches = (clone $matches)->count();

        $products = $matches->bestSelling()->limit(self::MAX_RESULTS)->get();

        if ($products->isEmpty()) {
            return 'No products available.';
        }

        $lines = [];

        foreach ($products as $product) {
            $this->context->addProduct([
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'price' => (string) $product->price,
                'stock' => $product->stock,
            ]);

            $categoryName = $product->category?->name ?? 'Uncategorized';

            $lines[] = "ID: {$product->id}, Name: {$product->name}, Category: {$categoryName}, Price: \${$product->price}, Stock: {$product->stock}";
        }

        if ($totalMatches > self::MAX_RESULTS) {
            $remaining = $totalMatches - self::MAX_RESULTS;
            $lines[] = 'Showing the top '.self::MAX_RESULTS." best-selling products. {$remaining} more available — suggest the user narrow down by category, keyword, or price to see others.";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string(),
            'in_stock_only' => $schema->boolean(),
        ];
    }
}
