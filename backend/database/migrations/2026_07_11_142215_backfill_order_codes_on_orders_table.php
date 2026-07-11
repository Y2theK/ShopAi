<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Order::query()
            ->whereNull('order_code')
            ->each(function (Order $order) {
                $order->forceFill(['order_code' => Order::generateOrderCode()])->saveQuietly();
            });
    }

    public function down(): void
    {
        // Backfill only; nothing to reverse.
    }
};
