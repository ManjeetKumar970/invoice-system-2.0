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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            
            $table->string('name')->index();
            $table->string('barcode', 100)->unique()->index();
            $table->string('sku', 100)->unique()->index();
            $table->string('hsn_code', 50)->default('')->index(); // Indian GST HSN / SAC Code
            $table->string('unit', 20)->default('PCS');
            
            $table->decimal('cost_price', 15, 4)->default(0.0000);
            $table->decimal('unit_price', 15, 4)->default(0.0000)->index();
            $table->decimal('mrp', 15, 4)->default(0.0000);
            $table->decimal('reorder_level', 12, 3)->default(0.000);
            
            $table->boolean('is_serialized')->default(false);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true)->index();
            
            // Distributed sync metadata
            $table->unsignedBigInteger('sync_version')->default(1)->index();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for ultra-fast query & sync performance
            $table->index(['category_id', 'is_active']);
            $table->index(['updated_at', 'sync_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
