<?php

namespace App\Ai\Tools;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProductLookupTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Look up products by name or keyword and get their price, current stock, total units sold, and total revenue.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query');

        $products = Product::search($query)
            ->withSum('orderItems as units_sold', 'quantity')
            ->addSelect(['revenue' => OrderItem::selectRaw('COALESCE(SUM(quantity * price), 0)')
                ->whereColumn('product_id', (new Product)->getTable().'.id')])
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            return "No products found matching \"{$query}\".";
        }

        $lines = [];

        foreach ($products as $product) {
            $unitsSold = (int) ($product->units_sold ?? 0);
            $revenue = number_format((float) $product->revenue, 2);

            $lines[] = "{$product->name}: price \${$product->price}, {$product->stock} in stock, "
                ."{$unitsSold} units sold, \${$revenue} revenue";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }
}
