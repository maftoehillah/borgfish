<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'seller_saldo')) {
                $table->decimal('seller_saldo', 15, 2)->default(0)->after('saldo_tertahan');
            }

            if (! Schema::hasColumn('users', 'seller_saldo_pending_withdrawal')) {
                $table->decimal('seller_saldo_pending_withdrawal', 15, 2)->default(0)->after('seller_saldo');
            }
        });

        if (! Schema::hasTable('seller_wallet_ledgers')) {
            Schema::create('seller_wallet_ledgers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('entry_type', 64);
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->decimal('available_delta', 15, 2)->default(0);
                $table->decimal('pending_delta', 15, 2)->default(0);
                $table->decimal('balance_after', 15, 2)->default(0);
                $table->decimal('pending_after', 15, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
                $table->index(['entry_type']);
                $table->unique(['user_id', 'entry_type', 'reference_type', 'reference_id'], 'seller_wallet_ledgers_unique_reference');
            });
        }

        if (! Schema::hasTable('seller_withdrawals')) {
            Schema::create('seller_withdrawals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
                $table->string('bank_name', 64);
                $table->string('account_number', 64);
                $table->string('account_holder_name', 120);
                $table->text('seller_note')->nullable();
                $table->text('review_note')->nullable();
                $table->string('transfer_reference', 120)->nullable();
                $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('paid_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['status', 'requested_at']);
                $table->index(['reviewed_by_id']);
                $table->index(['paid_by_id']);
            });
        }

        $releasedTransactions = DB::table('transaksis')
            ->join('ikans', 'ikans.id', '=', 'transaksis.ikan_id')
            ->leftJoin('seller_wallet_ledgers', function ($join): void {
                $join->on('seller_wallet_ledgers.user_id', '=', 'ikans.user_id')
                    ->where('seller_wallet_ledgers.entry_type', '=', 'escrow_release_credit')
                    ->where('seller_wallet_ledgers.reference_type', '=', 'transaksis')
                    ->whereColumn('seller_wallet_ledgers.reference_id', 'transaksis.id');
            })
            ->where('transaksis.escrow_status', 'dilepas')
            ->whereNotNull('ikans.user_id')
            ->whereNull('seller_wallet_ledgers.id')
            ->orderBy('transaksis.id')
            ->get([
                'transaksis.id as transaksi_id',
                'transaksis.escrow_amount',
                'transaksis.harga_final',
                'transaksis.escrow_released_at',
                'transaksis.created_at as transaksi_created_at',
                'ikans.user_id as seller_id',
                'ikans.nama_ikan',
            ]);

        foreach ($releasedTransactions as $row) {
            $amount = round((float) ($row->escrow_amount ?? $row->harga_final ?? 0), 2);
            $sellerId = (int) ($row->seller_id ?? 0);

            if ($sellerId <= 0 || $amount <= 0) {
                continue;
            }

            $timestamp = $row->escrow_released_at
                ?? $row->transaksi_created_at
                ?? now();

            DB::table('seller_wallet_ledgers')->insert([
                'user_id' => $sellerId,
                'entry_type' => 'escrow_release_credit',
                'reference_type' => 'transaksis',
                'reference_id' => (int) $row->transaksi_id,
                'available_delta' => $amount,
                'pending_delta' => 0,
                'balance_after' => 0,
                'pending_after' => 0,
                'note' => 'Backfill saldo seller dari escrow yang sudah dilepas untuk lot ' . (string) ($row->nama_ikan ?? ('#' . $row->transaksi_id)),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('users')
                ->where('id', $sellerId)
                ->update([
                    'seller_saldo' => DB::raw('seller_saldo + ' . $amount),
                ]);
        }

        $runningBalances = [];

        $ledgerRows = DB::table('seller_wallet_ledgers')
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'user_id',
                'available_delta',
                'pending_delta',
            ]);

        foreach ($ledgerRows as $ledgerRow) {
            $userId = (int) $ledgerRow->user_id;

            if (! array_key_exists($userId, $runningBalances)) {
                $runningBalances[$userId] = [
                    'balance_after' => 0.0,
                    'pending_after' => 0.0,
                ];
            }

            $runningBalances[$userId]['balance_after'] = round(
                $runningBalances[$userId]['balance_after'] + (float) $ledgerRow->available_delta,
                2
            );
            $runningBalances[$userId]['pending_after'] = round(
                $runningBalances[$userId]['pending_after'] + (float) $ledgerRow->pending_delta,
                2
            );

            DB::table('seller_wallet_ledgers')
                ->where('id', (int) $ledgerRow->id)
                ->update([
                    'balance_after' => $runningBalances[$userId]['balance_after'],
                    'pending_after' => $runningBalances[$userId]['pending_after'],
                ]);
        }

        DB::table('users')->update([
            'seller_saldo_pending_withdrawal' => DB::raw('COALESCE(seller_saldo_pending_withdrawal, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_withdrawals');
        Schema::dropIfExists('seller_wallet_ledgers');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'seller_saldo_pending_withdrawal')) {
                $table->dropColumn('seller_saldo_pending_withdrawal');
            }

            if (Schema::hasColumn('users', 'seller_saldo')) {
                $table->dropColumn('seller_saldo');
            }
        });
    }
};
