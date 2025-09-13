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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('store_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_receivables', 12, 2)->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('firebase_id')->nullable()->unique(); // For Firebase sync
            $table->timestamps();
            
            // Indexes
            $table->index(['store_id', 'is_active']);
            $table->index('phone');
            $table->index('email');
            $table->index('total_receivables');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
