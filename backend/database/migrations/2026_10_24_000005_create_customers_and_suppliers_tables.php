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
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            
            $table->string('name')->index();
            $table->string('phone', 25)->nullable()->index();
            $table->string('email', 100)->nullable();
            $table->string('gstin', 20)->nullable()->index(); // 15-character Indian GSTIN
            $table->string('state_code', 10)->default('27')->index();
            
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            
            $table->decimal('loyalty_points', 12, 2)->default(0.00);
            $table->decimal('credit_balance', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true)->index();
            
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('company_name')->index();
            $table->string('contact_person')->nullable();
            $table->string('phone', 25)->nullable()->index();
            $table->string('email', 100)->nullable();
            $table->string('gstin', 20)->nullable()->index();
            $table->string('state_code', 10)->default('27')->index();
            
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
