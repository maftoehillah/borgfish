<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('wallet_id');
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->enum('status', ['PENDING','APPROVED','REJECTED','PAYOUT_INITIATED','PAID','FAILED','CANCELLED'])->default('PENDING');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('payout_provider')->nullable();
            $table->string('payout_external_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
