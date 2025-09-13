<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'transaction_number',
        'customer_name',
        'customer_id',
        'items',
        'subtotal',
        'discount',
        'tax',
        'total_amount',
        'payment_method',
        'payment_status',
        'notes',
        'cashier_id',
        'cashier_name',
        'firebase_id'
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'customer_name' => 'Walk-in Customer',
        'subtotal' => 0,
        'discount' => 0,
        'tax' => 0,
        'payment_status' => 'paid',
    ];

    // Scopes
    public function scopeByStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Accessors
    public function getFormattedTotalAttribute()
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getItemsCountAttribute()
    {
        return count($this->items ?? []);
    }

    public function getTotalItemsQuantityAttribute()
    {
        return collect($this->items)->sum('quantity');
    }

    public function getPaymentMethodLabelAttribute()
    {
        $labels = [
            'cash' => 'Tunai',
            'transfer' => 'Transfer Bank',
            'qris' => 'QRIS',
            'receivables' => 'Piutang'
        ];
        
        return $labels[$this->payment_method] ?? $this->payment_method;
    }

    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            'paid' => 'Lunas',
            'pending' => 'Pending',
            'cancelled' => 'Dibatalkan'
        ];
        
        return $labels[$this->payment_status] ?? $this->payment_status;
    }

    // Static methods
    public static function generateTransactionNumber()
    {
        $prefix = 'TRX';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        return $prefix . $date . $random;
    }

    // Methods
    public function updateCustomerStats()
    {
        if ($this->customer_id && $this->payment_status === 'paid') {
            $this->customer->updateTransactionStats();
        }
        
        return $this;
    }
}
