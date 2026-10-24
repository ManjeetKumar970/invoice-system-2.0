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
        Schema::create('cashups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained('terminals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->timestamp('opened_at')->index();
            $table->timestamp('closed_at')->nullable()->index();
            
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->decimal('cash_sales', 15, 2)->default(0.00);
            $table->decimal('card_sales', 15, 2)->default(0.00);
            $table->decimal('upi_sales', 15, 2)->default(0.00);
            $table->decimal('cash_expenses', 15, 2)->default(0.00);
            
            $table->decimal('expected_cash', 15, 2)->default(0.00);
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable(); // Over / Short amount
            
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'terminal_id', 'status']);
        });

        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('terminal_id')->constrained('terminals')->cascadeOnDelete();
            $table->enum('direction', ['push', 'pull'])->index();
            $table->unsignedInteger('records_count')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->text('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['terminal_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_batches');
        Schema::dropIfExists('cashups');
    }
};
