<?php

namespace App\Ai\Tools;

use App\Ai\ChartContext;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LowStockProductsTool implements Tool
{
    public function __construct(private ChartContext $context) {}

    public function description(): Stringable|string
    {
        return 'List products that are low on stock (at or below a threshold, default 5), lowest stock first. Useful for restock alerts.';
    }

    public function handle(Request $request): Stringable|string
    {
        $threshold = $request->integer('threshold', 5);

        $products = Product::query()
            ->lowStock($threshold)
            ->get(['id', 'name', 'stock']);

        if ($products->isEmpty()) {
            return "No products at or below a stock level of {$threshold}.";
        }

        $this->context->addChart([
            'type' => 'bar',
            'title' => "Low Stock Products (threshold: {$threshold})",
            'labels' => $products->pluck('name')->all(),
            'datasets' => [
                ['label' => 'Units in Stock', 'data' => $products->pluck('stock')->all()],
            ],
        ]);

        $lines = [];

        foreach ($products as $product) {
            $lines[] = "{$product->name}: {$product->stock} left".($product->stock === 0 ? ' (OUT OF STOCK)' : '');
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'threshold' => $schema->integer(),
        ];
    }
}
