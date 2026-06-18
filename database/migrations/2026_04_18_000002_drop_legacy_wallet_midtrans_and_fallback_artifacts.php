<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndexIfExists('transaksis', 'transaksis_midtrans_order_id_unique', 'unique');
        $this->dropIndexIfExists('transaksis', 'trx_status_escrow_delivery_paid_idx');
        $this->dropIndexIfExists('transaksis', 'trx_status_escrow_paid_delivery_ikan_idx');
        $this->dropIndexIfExists('transaksis', 'transaksis_escrow_status_index');
        $this->dropIndexIfExists('transaksis', 'transaksis_delivery_status_index');
        $this->dropIndexIfExists('transaksis', 'transaksis_fulfillment_ship_deadline_idx');
        $this->dropColumnsIfExist('transaksis', [
            'snap_token',
            'midtrans_order_id',
            'ship_deadline_at',
            'escrow_status',
            'escrow_amount',
            'escrow_locked_at',
            'escrow_released_at',
            'escrow_forfeited_at',
            'delivery_status',
            'delivery_method',
            'delivery_cost',
            'courier_name',
            'tracking_number',
            'shipped_at',
            'estimated_arrival_at',
            'delivered_at',
            'released_by_buyer_at',
        ]);

        $this->dropIndexIfExists('users', 'users_is_blacklisted_index');
        $this->dropIndexIfExists('users', 'users_auction_cooldown_until_index');
        $this->dropColumnsIfExist('users', [
            'saldo',
            'saldo_tertahan',
            'seller_saldo',
            'seller_saldo_pending_withdrawal',
            'is_blacklisted',
            'auction_cooldown_until',
            'reputation_score',
        ]);

        $this->dropColumnsIfExist('ikans', [
            'fallback_count',
            'payment_deadline_fallback_one_minutes',
            'payment_deadline_fallback_two_minutes',
            'payment_window_limit_minutes',
        ]);

        Schema::dropIfExists('seller_withdrawals');
        Schema::dropIfExists('seller_wallet_ledgers');
        Schema::dropIfExists('saldo_ledgers');
        Schema::dropIfExists('saldo_topups');
        Schema::dropIfExists('auction_bid_holds');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('escrows');
        Schema::dropIfExists('bidder_penalties');
        Schema::dropIfExists('auction_fallback_histories');
    }

    public function down(): void
    {
        // Legacy wallet, Midtrans, and fallback-winner artifacts are intentionally not restored.
    }

    private function dropColumnsIfExist(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existingColumns): void {
            foreach ($existingColumns as $column) {
                $table->dropColumn($column);
            }
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName, string $type = 'index'): void
    {
        if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName, $type): void {
                if ($type === 'unique') {
                    $table->dropUnique($indexName);
                    return;
                }

                $table->dropIndex($indexName);
            });
        } catch (\Throwable) {
            // Some database engines drop indexes automatically when a column is dropped.
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => isset($row->name) && $row->name === $index),
            'mysql', 'mariadb' => collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty(),
            'pgsql' => collect(DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?', [$table, $index]))->isNotEmpty(),
            default => false,
        };
    }
};
