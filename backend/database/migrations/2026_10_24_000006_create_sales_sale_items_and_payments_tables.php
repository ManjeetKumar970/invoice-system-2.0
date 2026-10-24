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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // Counter-generated UUID for distributed sync
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained('terminals')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            
            $table->string('invoice_number', 60);
            $table->enum('invoice_type', ['tax_invoice', 'bill_of_supply', 'quote', 'work_order', 'return'])->default('tax_invoice')->index();
            $table->enum('sale_status', ['completed', 'suspended', 'cancelled'])->default('completed')->index();
            
            $table->boolean('is_interstate')->default(false)->index();
            $table->string('customer_gstin', 20)->nullable();
            
            // Financials with scale of 4 for absolute math precision
            $table->decimal('subtotal', 15, 4)->default(0.0000);
            $table->decimal('discount_total', 15, 4)->default(0.0000);
            $table->decimal('cgst_total', 15, 4)->default(0.0000);
            $table->decimal('sgst_total', 15, 4)->default(0.0000);
            $table->decimal('igst_total', 15, 4)->default(0.0000);
            $table->decimal('cess_total', 15, 4)->default(0.0000);
            $table->decimal('tax_total', 15, 4)->default(0.0000);
            $table->decimal('round_off', 8, 4)->default(0.0000);
            $table->decimal('grand_total', 15, 4)->default(0.0000)->index();
            
            $table->decimal('amount_paid', 15, 4)->default(0.0000);
            $table->decimal('change_due', 15, 4)->default(0.0000);
            $table->enum('payment_status', ['paid', 'partial', 'unpaid'])->default('paid')->index();
            
            $table->date('sale_date')->index();
            $table->timestamp('sale_time')->index();
            
            // Offline Two-Way Sync tracking
            $table->enum('sync_status', ['pending', 'synced', 'conflict'])->default('synced')->index();
            $table->timestamp('synced_at')->nullable()->index();
            
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->unique(['store_id', 'invoice_number']);
            $table->index(['store_id', 'sale_date', 'payment_status']);
            $table->index(['sync_status', 'created_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            
            $table->unsignedSmallInteger('line_number');
            $table->string('item_name');
            $table->string('barcode', 100);
            $table->string('hsn_code', 50)->default('')->index(); // Critical for GSTR-1 HSN summary
            
            $table->decimal('quantity', 15, 3)->default(1.000);
            $table->decimal('cost_price', 15, 4)->default(0.0000);
            $table->decimal('unit_price', 15, 4)->default(0.0000);
            
            $table->decimal('discount_percent', 6, 3)->default(0.000);
            $table->decimal('discount_amount', 15, 4)->default(0.0000);
            $table->decimal('taxable_amount', 15, 4)->default(0.0000);
            
            $table->decimal('tax_rate', 6, 3)->default(0.000);
            $table->decimal('cgst_rate', 6, 3)->default(0.000);
            $table->decimal('cgst_amount', 15, 4)->default(0.0000);
            $table->decimal('sgst_rate', 6, 3)->default(0.000);
            $table->decimal('sgst_amount', 15, 4)->default(0.0000);
            $table->decimal('igst_rate', 6, 3)->default(0.000);
            $table->decimal('igst_amount', 15, 4)->default(0.0000);
            $table->decimal('cess_rate', 6, 3)->default(0.000);
            $table->decimal('cess_amount', 15, 4)->default(0.0000);
            
            $table->decimal('total_tax', 15, 4)->default(0.0000);
            $table->decimal('line_total', 15, 4)->default(0.0000);
            $table->timestamps();

            $table->unique(['sale_id', 'line_number']);
            $table->index(['item_id', 'created_at']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'giftcard', 'loyalty_points', 'credit'])->index();
            $table->decimal('amount', 15, 4);
            $table->string('reference_code', 100)->nullable();
            $table->string('card_last_four', 10)->nullable();
            $table->timestamps();

            $table->index(['payment_method', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
