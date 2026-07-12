<?php

namespace App\Ai;

class AgentContext
{
    /** @var array<int, array<string, mixed>> */
    private array $products = [];

    private bool $orderPlaced = false;

    /** @var array<string, string|null>|null */
    private ?array $deliveryAddress = null;

    /**
     * Delivery details collected by the checkout form. They travel as a
     * structured side channel (request -> context -> tool), never through
     * the model, so the PII stays out of the prompt.
     *
     * @param  array<string, string|null>  $address
     */
    public function setDeliveryAddress(array $address): void
    {
        $this->deliveryAddress = $address;
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getDeliveryAddress(): ?array
    {
        return $this->deliveryAddress;
    }

    /** @var array<string, mixed>|null */
    private ?array $orderInfo = null;

    /**
     * Delivery details of a placed or tracked order, returned to the frontend
     * for deterministic rendering. Kept out of tool results so the PII never
     * reaches the model.
     *
     * @param  array<string, mixed>  $orderInfo
     */
    public function setOrderInfo(array $orderInfo): void
    {
        $this->orderInfo = $orderInfo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrderInfo(): ?array
    {
        return $this->orderInfo;
    }

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
