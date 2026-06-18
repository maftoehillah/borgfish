<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'saldo')) {
                $table->decimal('saldo', 15, 2)->default(0)->after('reputation_score');
            }

            if (! Schema::hasColumn('users', 'saldo_tertahan')) {
                $table->decimal('saldo_tertahan', 15, 2)->default(0)->after('saldo');
            }
        });

        if (! Schema::hasTable('auction_bid_holds')) {
            Schema::create('auction_bid_holds', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transaksi_id')->nullable()->constrained('transaksis')->nullOnDelete();
                $table->decimal('amount', 15, 2)->default(0);
                $table->enum('status', ['active', 'released', 'captured'])->default('active');
                $table->string('reason', 64)->default('leading_bid');
                $table->timestamp('held_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->string('release_reason', 64)->nullable();
                $table->timestamps();

                $table->index(['ikan_id', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('saldo_ledgers')) {
            Schema::create('saldo_ledgers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('entry_type', 64);
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('available_delta', 15, 2)->default(0);
                $table->decimal('held_delta', 15, 2)->default(0);
                $table->decimal('balance_after', 15, 2)->default(0);
                $table->decimal('held_after', 15, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
                $table->index(['entry_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_ledgers');
        Schema::dropIfExists('auction_bid_holds');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'saldo_tertahan')) {
                $table->dropColumn('saldo_tertahan');
            }

            if (Schema::hasColumn('users', 'saldo')) {
                $table->dropColumn('saldo');
            }
        });
    }
};
