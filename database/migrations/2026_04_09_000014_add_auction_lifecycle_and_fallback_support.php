<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ikans')) {
            $hasAuctionState = Schema::hasColumn('ikans', 'auction_state');
            $hasFallbackCount = Schema::hasColumn('ikans', 'fallback_count');
            $hasCurrentWinnerRank = Schema::hasColumn('ikans', 'current_winner_rank');
            $hasHardStopReason = Schema::hasColumn('ikans', 'hard_stop_reason');
            $hasRankingFrozenAt = Schema::hasColumn('ikans', 'ranking_frozen_at');

            Schema::table('ikans', function (Blueprint $table) use ($hasAuctionState, $hasFallbackCount, $hasCurrentWinnerRank, $hasHardStopReason, $hasRankingFrozenAt): void {
                if (! $hasAuctionState) {
                    $table->enum('auction_state', ['AKTIF', 'SELESAI', 'MENUNGGU_PEMBAYARAN', 'DIBAYAR', 'KADALUARSA', 'GAGAL_TOTAL'])
                        ->default('AKTIF')
                        ->after('status');
                    $table->index('auction_state');
                }

                if (! $hasFallbackCount) {
                    $table->unsignedTinyInteger('fallback_count')->default(0)->after('auction_state');
                }

                if (! $hasCurrentWinnerRank) {
                    $table->unsignedSmallInteger('current_winner_rank')->nullable()->after('fallback_count');
                }

                if (! $hasHardStopReason) {
                    $table->string('hard_stop_reason', 191)->nullable()->after('current_winner_rank');
                }

                if (! $hasRankingFrozenAt) {
                    $table->dateTime('ranking_frozen_at')->nullable()->after('hard_stop_reason');
                }
            });

            if (Schema::hasColumn('ikans', 'auction_state') && Schema::hasColumn('ikans', 'status')) {
                DB::statement("UPDATE ikans
                    SET auction_state = CASE
                        WHEN status = 'terbayar' THEN 'DIBAYAR'
                        WHEN status = 'selesai' THEN 'SELESAI'
                        ELSE 'AKTIF'
                    END
                    WHERE auction_state IS NULL OR auction_state = ''");
            }
        }

        if (Schema::hasTable('users')) {
            $hasIsBlacklisted = Schema::hasColumn('users', 'is_blacklisted');
            $hasAuctionCooldownUntil = Schema::hasColumn('users', 'auction_cooldown_until');
            $hasReputationScore = Schema::hasColumn('users', 'reputation_score');

            Schema::table('users', function (Blueprint $table) use ($hasIsBlacklisted, $hasAuctionCooldownUntil, $hasReputationScore): void {
                if (! $hasIsBlacklisted) {
                    $table->boolean('is_blacklisted')->default(false)->after('is_admin');
                    $table->index('is_blacklisted');
                }

                if (! $hasAuctionCooldownUntil) {
                    $table->dateTime('auction_cooldown_until')->nullable()->after('is_blacklisted');
                    $table->index('auction_cooldown_until');
                }

                if (! $hasReputationScore) {
                    $table->integer('reputation_score')->default(100)->after('auction_cooldown_until');
                }
            });
        }

        if (! Schema::hasTable('auction_rankings')) {
            Schema::create('auction_rankings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('rank');
                $table->foreignId('bidder_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('bid_id')->nullable()->constrained('bids')->nullOnDelete();
                $table->decimal('bid_amount', 15, 2);
                $table->dateTime('bid_created_at')->nullable();
                $table->string('snapshot_hash', 64);
                $table->timestamps();

                $table->unique(['ikan_id', 'rank']);
                $table->unique(['ikan_id', 'bidder_id']);
                $table->index(['ikan_id', 'bid_amount']);
            });
        }

        if (! Schema::hasTable('payment_attempts')) {
            Schema::create('payment_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transaksi_id')->nullable()->constrained('transaksis')->nullOnDelete();
                $table->foreignId('bidder_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('rank');
                $table->decimal('amount_due', 15, 2);
                $table->enum('status', ['menunggu_pembayaran', 'dibayar', 'kadaluarsa', 'dibatalkan'])->default('menunggu_pembayaran');
                $table->dateTime('bayar_sebelum');
                $table->dateTime('assigned_at');
                $table->dateTime('paid_at')->nullable();
                $table->dateTime('expired_at')->nullable();
                $table->string('payment_provider_ref')->nullable();
                $table->string('idempotency_key', 64)->unique();
                $table->timestamps();

                $table->index(['status', 'bayar_sebelum']);
                $table->index(['ikan_id', 'rank']);
            });
        }

        if (! Schema::hasTable('auction_state_logs')) {
            Schema::create('auction_state_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->string('from_state', 32)->nullable();
                $table->string('to_state', 32);
                $table->string('event_name', 64);
                $table->string('actor_type', 32)->default('system');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['ikan_id', 'created_at']);
                $table->index(['actor_type', 'actor_id']);
            });
        }

        if (! Schema::hasTable('auction_fallback_histories')) {
            Schema::create('auction_fallback_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('from_rank')->nullable();
                $table->unsignedSmallInteger('to_rank')->nullable();
                $table->string('reason', 191);
                $table->unsignedTinyInteger('fallback_count_after')->default(0);
                $table->string('triggered_by_type', 32)->default('system');
                $table->unsignedBigInteger('triggered_by_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['ikan_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('bidder_penalties')) {
            Schema::create('bidder_penalties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->string('reason', 64);
                $table->dateTime('cooldown_until')->nullable();
                $table->smallInteger('reputation_delta')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at']);
                $table->index(['reason', 'cooldown_until']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bidder_penalties');
        Schema::dropIfExists('auction_fallback_histories');
        Schema::dropIfExists('auction_state_logs');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('auction_rankings');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'reputation_score')) {
                    $table->dropColumn('reputation_score');
                }

                if (Schema::hasColumn('users', 'auction_cooldown_until')) {
                    $table->dropColumn('auction_cooldown_until');
                }

                if (Schema::hasColumn('users', 'is_blacklisted')) {
                    $table->dropColumn('is_blacklisted');
                }
            });
        }

        if (Schema::hasTable('ikans')) {
            Schema::table('ikans', function (Blueprint $table): void {
                if (Schema::hasColumn('ikans', 'ranking_frozen_at')) {
                    $table->dropColumn('ranking_frozen_at');
                }

                if (Schema::hasColumn('ikans', 'hard_stop_reason')) {
                    $table->dropColumn('hard_stop_reason');
                }

                if (Schema::hasColumn('ikans', 'current_winner_rank')) {
                    $table->dropColumn('current_winner_rank');
                }

                if (Schema::hasColumn('ikans', 'fallback_count')) {
                    $table->dropColumn('fallback_count');
                }

                if (Schema::hasColumn('ikans', 'auction_state')) {
                    $table->dropColumn('auction_state');
                }
            });
        }
    }
};
