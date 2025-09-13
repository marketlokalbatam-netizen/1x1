<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'phone',
        'email',
        'address',
        'total_receivables',
        'total_spent',
        'total_transactions',
        'notes',
        'is_active',
        'firebase_id'
    ];

    protected $casts = [
        'total_receivables' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'total_transactions' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'total_receivables' => 0,
        'total_spent' => 0,
        'total_transactions' => 0,
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

    public function scopeWithReceivables($query)
    {
        return $query->where('total_receivables', '>', 0);
    }

    // Relationships
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Accessors
    public function getFormattedReceivablesAttribute()
    {
        return 'Rp ' . number_format($this->total_receivables, 0, ',', '.');
    }

    public function getFormattedTotalSpentAttribute()
    {
        return 'Rp ' . number_format($this->total_spent, 0, ',', '.');
    }

    public function getHasReceivablesAttribute()
    {
        return $this->total_receivables > 0;
    }

    // Methods
    public function addReceivables($amount, $notes = '')
    {
        $this->total_receivables += $amount;
        $this->save();
        return $this;
    }

    public function subtractReceivables($amount)
    {
        $this->total_receivables = max(0, $this->total_receivables - $amount);
        $this->save();
        return $this;
    }

    public function updateTransactionStats()
    {
        $stats = $this->transactions()
            ->where('payment_status', 'paid')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as total')
            ->first();
        
        $this->total_transactions = $stats->count ?? 0;
        $this->total_spent = $stats->total ?? 0;
        $this->save();
        
        return $this;
    }
}
