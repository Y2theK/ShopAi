<?php

namespace App\Ai;

class AgentContext
{
    /** @var array<int, array<string, mixed>> */
    private array $products = [];

    private bool $orderPlaced = false;

    public function markOrderPlaced(): void
    {
        $this->orderPlaced = true;
    }

    public function orderWasPlaced(): bool
    {
        return $this->orderPlaced;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function addProduct(array $product): void
    {
        $this->products[$product['id']] = $product;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(): array
    {
        return array_values($this->products);
    }
}
