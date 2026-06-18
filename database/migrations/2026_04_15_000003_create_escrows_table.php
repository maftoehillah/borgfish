<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('transaction_id')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->enum('status', ['HELD', 'RELEASED', 'FORFEITED'])->default('HELD');
            $table->timestamp('held_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('released_by')->nullable();
            $table->string('external_payment_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transaksis')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrows');
    }
};
