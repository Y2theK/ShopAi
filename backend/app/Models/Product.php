<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'price',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeLowStock(Builder $query, int $threshold = 5): Builder
    {
        return $query->where('stock', '<=', $threshold)->orderBy('stock');
    }

    public function scopeSearch(Builder $query, ?string $search = null): Builder
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
        });
    }

    public function scopeBestSelling(Builder $query): Builder
    {
        return $query->withSum('orderItems as total_sold', 'quantity')
            ->orderByDesc('total_sold')
            ->orderBy('id');
    }

    public function scopeInCategory(Builder $query, ?string $category = null): Builder
    {
        if (! $category) {
            return $query;
        }

        return $query->whereHas('category', function (Builder $q) use ($category) {
            $q->where('slug', $category)->orWhere('name', 'like', "%{$category}%");
        });
    }
}
