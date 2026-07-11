<?php

namespace App\Ai;

class ChartContext
{
    /** @var array<int, array<string, mixed>> */
    private array $charts = [];

    /**
     * @param  array{type: string, title: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float|int>}>}  $chart
     */
    public function addChart(array $chart): void
    {
        $this->charts[] = $chart;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCharts(): array
    {
        return $this->charts;
    }
}
