<?php

namespace App\Ai\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class InventorySummaryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get an inventory overview: total number of products, total units in stock, total inventory value, and how many products are out of stock.';
    }

    public function handle(Request $request): Stringable|string
    {
        $summary = Product::query()
            ->selectRaw('COUNT(*) as products_count')
            ->selectRaw('COALESCE(SUM(stock), 0) as units_in_stock')
            ->selectRaw('COALESCE(SUM(stock * price), 0) as inventory_value')
            ->selectRaw('SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count')
            ->first();

        if ((int) $summary->products_count === 0) {
            return 'There are no products in the catalog yet.';
        }

        $value = number_format((float) $summary->inventory_value, 2);

        return "Inventory summary: {$summary->products_count} products in the catalog, "
            ."{$summary->units_in_stock} total units in stock, "
            ."total inventory value \${$value}, "
            ."{$summary->out_of_stock_count} products out of stock.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
