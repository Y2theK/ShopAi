<?php

namespace App\Ai\Tools;

use App\Ai\ChartContext;
use App\Ai\Concerns\CachesToolResults;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class BestSellingProductsTool implements Tool
{
    use CachesToolResults;

    public function __construct(private ChartContext $context) {}

    public function description(): Stringable|string
    {
        return 'Get the best-selling products ranked by units sold, with revenue per product. Optionally limit the number of products and restrict to the last N days.';
    }

    public function handle(Request $request): Stringable|string
    {
        $limit = $request->integer('limit', 5);
        $days = $request->integer('days');

        return $this->cached(
            "admin-tools:best-selling-products:{$limit}:{$days}",
            fn () => $this->compute($limit, $days),
            300,
            $this->context,
        );
    }

    private function compute(int $limit, int $days): string
    {
        $orderItems = (new OrderItem)->getTable();
        $products = (new Product)->getTable();

        $rows = OrderItem::query()
            ->join($products, "{$products}.id", '=', "{$orderItems}.product_id")
            ->when($days > 0, fn ($q) => $q->where("{$orderItems}.created_at", '>=', now()->subDays($days)))
            ->selectRaw("{$products}.name as name")
            ->selectRaw("SUM({$orderItems}.quantity) as units_sold")
            ->selectRaw("SUM({$orderItems}.quantity * {$orderItems}.price) as revenue")
            ->groupBy("{$products}.id", "{$products}.name")
            ->orderByDesc('units_sold')
            ->limit(max(1, $limit))
            ->get();

        if ($rows->isEmpty()) {
            return 'No sales recorded for the requested period.';
        }

        $this->context->addChart([
            'type' => 'bar',
            'title' => $days > 0 ? "Best Selling Products (last {$days} days)" : 'Best Selling Products',
            'labels' => $rows->pluck('name')->all(),
            'datasets' => [
                ['label' => 'Units Sold', 'data' => $rows->pluck('units_sold')->map(fn ($v) => (int) $v)->all()],
            ],
        ]);

        $lines = [];

        foreach ($rows as $row) {
            $revenue = number_format((float) $row->revenue, 2);
            $lines[] = "{$row->name}: {$row->units_sold} units sold, \${$revenue} revenue";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer(),
            'days' => $schema->integer(),
        ];
    }
}
