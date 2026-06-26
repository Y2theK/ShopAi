<?php

namespace App\Ai\Tools;

use App\Ai\AgentContext;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetProductDetailsTool implements Tool
{
    public function __construct(private AgentContext $context) {}

    public function description(): Stringable|string
    {
        return 'Get full details of a specific product by its ID.';
    }

    public function handle(Request $request): Stringable|string
    {
        $product = Product::find($request->integer('product_id'));

        if (! $product) {
            return 'Product not found.';
        }

        $this->context->addProduct([
            'id' => $product->id,
            'name' => $product->name,
            'price' => (string) $product->price,
            'stock' => $product->stock,
        ]);

        return "ID: {$product->id}, Name: {$product->name}, Price: \${$product->price}, Stock: {$product->stock}";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required(),
        ];
    }
}
