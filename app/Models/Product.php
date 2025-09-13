<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'sku',
        'price',
        'price_sell',
        'stock',
        'unit',
        'category',
        'description',
        'image_url',
        'is_active',
        'firebase_id'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_sell' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'unit' => 'pcs',
        'stock' => 0,
        'is_active' => true,
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('stock', '<=', $threshold);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    // Accessors
    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    public function getFormattedPriceSellAttribute()
    {
        return 'Rp ' . number_format($this->price_sell, 0, ',', '.');
    }

    public function getIsLowStockAttribute()
    {
        return $this->stock <= 10;
    }

    public function getIsOutOfStockAttribute()
    {
        return $this->stock <= 0;
    }

    // Methods
    public function decreaseStock($quantity)
    {
        $this->stock = max(0, $this->stock - $quantity);
        $this->save();
        return $this;
    }

    public function increaseStock($quantity)
    {
        $this->stock += $quantity;
        $this->save();
        return $this;
    }

    public function updateStock($quantity)
    {
        $this->stock = max(0, $quantity);
        $this->save();
        return $this;
    }
}
