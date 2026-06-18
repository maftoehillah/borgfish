<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seller_settlements')) {
            return;
        }

        Schema::create('seller_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaksi_id')->unique()->constrained('transaksis')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'ready_to_pay', 'held', 'paid', 'cancelled'])->default('pending');
            $table->string('bank_name', 100);
            $table->string('bank_account_number', 50);
            $table->string('bank_account_name', 100);
            $table->text('admin_note')->nullable();
            $table->text('hold_reason')->nullable();
            $table->string('transfer_reference', 120)->nullable();
            $table->string('transfer_proof_path')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_to_pay_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_settlements');
    }
};
