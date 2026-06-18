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
        if (! Schema::hasTable('transaction_disputes')) {
            Schema::create('transaction_disputes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('transaksi_id')->constrained('transaksis')->cascadeOnDelete();
                $table->foreignId('ikan_id')->constrained('ikans')->cascadeOnDelete();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['open', 'resolved_completed', 'resolved_failed', 'rejected'])->default('open');
                $table->string('complaint_reason', 64);
                $table->string('complaint_detail', 500)->nullable();
                $table->string('opened_by_type', 32)->default('buyer');
                $table->unsignedBigInteger('opened_by_id')->nullable();
                $table->dateTime('opened_at')->nullable();
                $table->unsignedBigInteger('resolved_by_id')->nullable();
                $table->string('resolution_note', 500)->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'opened_at']);
                $table->index(['buyer_id', 'created_at']);
                $table->index(['seller_id', 'created_at']);
                $table->index(['transaksi_id', 'status']);
            });
        }

        if (! Schema::hasTable('notification_outbox')) {
            Schema::create('notification_outbox', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('recipient_role', 32)->nullable();
                $table->string('category', 64);
                $table->string('title', 191);
                $table->string('message', 500);
                $table->json('payload')->nullable();
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->string('last_error', 500)->nullable();
                $table->dateTime('available_at')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->string('idempotency_key', 100)->unique();
                $table->timestamps();

                $table->index(['status', 'available_at']);
                $table->index(['recipient_user_id', 'status']);
            });
        }

        if (! Schema::hasTable('in_app_notifications')) {
            Schema::create('in_app_notifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('category', 64);
                $table->string('title', 191);
                $table->string('message', 500);
                $table->json('payload')->nullable();
                $table->dateTime('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['user_id', 'read_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('transaction_disputes');
    }
};
