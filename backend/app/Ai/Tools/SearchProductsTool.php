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
    public function __construct(private AgentContext $context) {}

    public function description(): Stringable|string
    {
        return 'Search for products by name or keyword. Optionally filter by max price.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query');
        $maxPrice = $request['max_price'] ?? null;

        $products = Product::search($query)
            ->when($maxPrice, fn ($q) => $q->where('price', '<=', $maxPrice))
            ->get();

        if ($products->isEmpty()) {
            return 'No products found matching your search.';
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
            'query' => $schema->string()->required(),
            'max_price' => $schema->number(),
        ];
    }
}
