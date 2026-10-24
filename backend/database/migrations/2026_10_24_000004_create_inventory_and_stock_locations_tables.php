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
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Current real-time stock balance cache
        Schema::create('item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0.000);
            $table->timestamps();

            $table->unique(['item_id', 'stock_location_id']);
        });

        // Immutable Double-Entry Stock Movement Ledger
        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->enum('transaction_type', ['sale', 'return', 'receiving', 'adjustment', 'transfer'])->index();
            $table->uuid('reference_uuid')->nullable()->index(); // ID of the sale or receiving
            
            $table->decimal('quantity_delta', 15, 3); // Negative for sales, positive for receivings
            $table->decimal('balance_after', 15, 3);
            $table->string('comment')->nullable();
            
            $table->timestamp('created_at')->index();

            // High-speed ledger query indexes
            $table->index(['item_id', 'created_at']);
            $table->index(['stock_location_id', 'created_at']);
            $table->index(['transaction_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
        Schema::dropIfExists('item_stocks');
        Schema::dropIfExists('stock_locations');
    }
};
