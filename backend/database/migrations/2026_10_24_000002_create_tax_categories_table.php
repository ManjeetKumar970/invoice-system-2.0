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
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100); // e.g., GST 18%, GST 5%, GST Exempt
            $table->decimal('rate', 6, 3)->default(0.000); // e.g. 18.000%
            $table->decimal('cgst_rate', 6, 3)->default(0.000); // 9.000%
            $table->decimal('sgst_rate', 6, 3)->default(0.000); // 9.000%
            $table->decimal('igst_rate', 6, 3)->default(0.000); // 18.000%
            $table->decimal('cess_rate', 6, 3)->default(0.000);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_categories');
    }
};
