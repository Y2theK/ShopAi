<?php

namespace App\Ai\Tools;

use App\Ai\AgentContext;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchProductsTool implements Tool
{
    private const MAX_RESULTS = 5;

    public function __construct(private AgentContext $context) {}

    public function description(): Stringable|string
    {
        return 'Search for products by name or keyword. Optionally filter by category name (e.g. Electronics, Clothing) and/or max price. Returns at most 5 results, best sellers first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query');
        $category = $request['category'] ?? null;
        $maxPrice = $request['max_price'] ?? null;

        $matches = Product::with('category')
            ->search($query)
            ->inCategory($category)
            ->when($maxPrice, fn ($q) => $q->where('price', '<=', $maxPrice));

        $totalMatches = (clone $matches)->count();

        $products = $matches->bestSelling()->limit(self::MAX_RESULTS)->get();

        if ($products->isEmpty()) {
            return 'No products found matching your search.';
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
            $lines[] = 'Showing the top '.self::MAX_RESULTS." best-selling matches. {$remaining} more match — suggest the user narrow down by keyword or price to see others.";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'category' => $schema->string(),
            'max_price' => $schema->number(),
        ];
    }
}
