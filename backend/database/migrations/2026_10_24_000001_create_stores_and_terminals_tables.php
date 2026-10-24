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
        // Multi-Store Support
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('state_code', 10)->default('27'); // e.g., 27 for Maharashtra
            $table->string('pincode', 20)->nullable();
            $table->string('gstin', 20)->nullable()->index();
            $table->string('currency_symbol', 10)->default('₹');
            $table->boolean('tax_included')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Terminals / POS Counters
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('terminal_code', 50); // e.g. POS-01
            $table->string('device_name', 100)->nullable();
            $table->string('mac_or_ip', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['store_id', 'terminal_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('stores');
    }
};
