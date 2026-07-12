<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'order_code',
        'total_price',
        'status',
        'phone',
        'secondary_phone',
        'address',
        'city',
        'state',
        'country',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_code ??= static::generateOrderCode();
            $order->status ??= static::STATUS_PENDING;
        });
    }

    public static function generateOrderCode(): string
    {
        do {
            $code = 'ORD-'.strtoupper(Str::random(8));
        } while (static::where('order_code', $code)->exists());

        return $code;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
