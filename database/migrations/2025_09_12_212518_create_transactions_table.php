<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('store_id');
            $table->string('transaction_number')->unique();
            $table->string('customer_name')->default('Walk-in Customer');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->json('items'); // Store transaction items as JSON
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'receivables']);
            $table->enum('payment_status', ['pending', 'paid', 'cancelled'])->default('paid');
            $table->text('notes')->nullable();
            $table->string('cashier_id');
            $table->string('cashier_name');
            $table->string('firebase_id')->nullable()->unique(); // For Firebase sync
            $table->timestamps();
            
            // Indexes
            $table->index(['store_id', 'created_at']);
            $table->index('transaction_number');
            $table->index('payment_method');
            $table->index('payment_status');
            $table->index('customer_id');
            
            // Foreign key
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
