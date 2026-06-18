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
        if (Schema::hasTable('transaksis')) {
            $hasFulfillmentState = Schema::hasColumn('transaksis', 'fulfillment_state');
            $hasStateVersion = Schema::hasColumn('transaksis', 'state_version');
            $hasStateReasonCode = Schema::hasColumn('transaksis', 'state_reason_code');
            $hasStateReasonText = Schema::hasColumn('transaksis', 'state_reason_text');
            $hasPaidAt = Schema::hasColumn('transaksis', 'paid_at');
            $hasSellerAckAt = Schema::hasColumn('transaksis', 'seller_ack_at');
            $hasCompletedAt = Schema::hasColumn('transaksis', 'completed_at');
            $hasFailedAt = Schema::hasColumn('transaksis', 'failed_at');
            $hasDisputedAt = Schema::hasColumn('transaksis', 'disputed_at');
            $hasSellerAckDeadlineAt = Schema::hasColumn('transaksis', 'seller_ack_deadline_at');
            $hasShipDeadlineAt = Schema::hasColumn('transaksis', 'ship_deadline_at');
            $hasBuyerConfirmDeadlineAt = Schema::hasColumn('transaksis', 'buyer_confirm_deadline_at');

            Schema::table('transaksis', function (Blueprint $table) use (
                $hasFulfillmentState,
                $hasStateVersion,
                $hasStateReasonCode,
                $hasStateReasonText,
                $hasPaidAt,
                $hasSellerAckAt,
                $hasCompletedAt,
                $hasFailedAt,
                $hasDisputedAt,
                $hasSellerAckDeadlineAt,
                $hasShipDeadlineAt,
                $hasBuyerConfirmDeadlineAt,
            ): void {
                if (! $hasFulfillmentState) {
                    $table->enum('fulfillment_state', [
                        'DIBAYAR',
                        'DIPROSES_PENJUAL',
                        'DIKIRIM',
                        'SELESAI',
                        'GAGAL',
                        'DISENGKETAKAN',
                    ])->nullable()->after('status');
                    $table->index('fulfillment_state', 'transaksis_fulfillment_state_idx');
                }

                if (! $hasStateVersion) {
                    $table->unsignedInteger('state_version')->default(0)->after('fulfillment_state');
                }

                if (! $hasStateReasonCode) {
                    $table->string('state_reason_code', 64)->nullable()->after('state_version');
                }

                if (! $hasStateReasonText) {
                    $table->string('state_reason_text', 191)->nullable()->after('state_reason_code');
                }

                if (! $hasPaidAt) {
                    $table->dateTime('paid_at')->nullable()->after('dibayar_pada');
                }

                if (! $hasSellerAckAt) {
                    $table->dateTime('seller_ack_at')->nullable()->after('paid_at');
                }

                if (! $hasCompletedAt) {
                    $table->dateTime('completed_at')->nullable()->after('delivered_at');
                }

                if (! $hasFailedAt) {
                    $table->dateTime('failed_at')->nullable()->after('completed_at');
                }

                if (! $hasDisputedAt) {
                    $table->dateTime('disputed_at')->nullable()->after('failed_at');
                }

                if (! $hasSellerAckDeadlineAt) {
                    $table->dateTime('seller_ack_deadline_at')->nullable()->after('disputed_at');
                }

                if (! $hasShipDeadlineAt) {
                    $table->dateTime('ship_deadline_at')->nullable()->after('seller_ack_deadline_at');
                    $table->index(['fulfillment_state', 'ship_deadline_at'], 'transaksis_fulfillment_ship_deadline_idx');
                }

                if (! $hasBuyerConfirmDeadlineAt) {
                    $table->dateTime('buyer_confirm_deadline_at')->nullable()->after('ship_deadline_at');
                    $table->index(['fulfillment_state', 'buyer_confirm_deadline_at'], 'transaksis_fulfillment_buyer_deadline_idx');
                }
            });

            DB::table('transaksis')
                ->whereNull('fulfillment_state')
                ->update([
                    'fulfillment_state' => DB::raw("CASE
                        WHEN status IN ('gagal', 'kadaluarsa') THEN 'GAGAL'
                        WHEN status = 'proses' THEN 'DISENGKETAKAN'
                        WHEN status = 'lunas' AND delivery_status = 'diterima' THEN 'SELESAI'
                        WHEN status = 'lunas' AND delivery_status = 'dikirim' THEN 'DIKIRIM'
                        WHEN status = 'lunas' AND delivery_status IN ('diproses', 'menunggu_pengiriman') THEN 'DIPROSES_PENJUAL'
                        ELSE fulfillment_state
                    END"),
                ]);

            DB::table('transaksis')
                ->whereNull('paid_at')
                ->whereNotNull('dibayar_pada')
                ->whereIn('fulfillment_state', ['DIBAYAR', 'DIPROSES_PENJUAL', 'DIKIRIM', 'SELESAI'])
                ->update([
                    'paid_at' => DB::raw('dibayar_pada'),
                ]);
        }

        if (! Schema::hasTable('transaction_state_logs')) {
            Schema::create('transaction_state_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('transaksi_id')->constrained('transaksis')->cascadeOnDelete();
                $table->string('from_state', 32)->nullable();
                $table->string('to_state', 32);
                $table->string('event_name', 64);
                $table->string('actor_type', 32)->default('system');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('reason_code', 64)->nullable();
                $table->string('reason_text', 191)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['transaksi_id', 'created_at'], 'transaction_state_logs_transaksi_created_idx');
                $table->index(['actor_type', 'actor_id'], 'transaction_state_logs_actor_idx');
                $table->index(['to_state', 'created_at'], 'transaction_state_logs_to_state_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_state_logs');

        if (Schema::hasTable('transaksis')) {
            Schema::table('transaksis', function (Blueprint $table): void {
                $columns = [
                    'buyer_confirm_deadline_at',
                    'ship_deadline_at',
                    'seller_ack_deadline_at',
                    'disputed_at',
                    'failed_at',
                    'completed_at',
                    'seller_ack_at',
                    'paid_at',
                    'state_reason_text',
                    'state_reason_code',
                    'state_version',
                    'fulfillment_state',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('transaksis', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
