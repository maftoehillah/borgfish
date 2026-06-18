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
        if (Schema::hasTable('ikans')) {
            Schema::table('ikans', function (Blueprint $table): void {
                if (! Schema::hasIndex('ikans', 'ikans_user_tipe_id_idx')) {
                    $table->index(['user_id', 'tipe_lelang', 'id'], 'ikans_user_tipe_id_idx');
                }
            });
        }

        if (Schema::hasTable('transaksis')) {
            Schema::table('transaksis', function (Blueprint $table): void {
                if (! Schema::hasIndex('transaksis', 'trx_status_escrow_paid_delivery_ikan_idx')) {
                    $table->index(
                        ['status', 'escrow_status', 'dibayar_pada', 'delivery_status', 'ikan_id'],
                        'trx_status_escrow_paid_delivery_ikan_idx'
                    );
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transaksis')) {
            Schema::table('transaksis', function (Blueprint $table): void {
                if (Schema::hasIndex('transaksis', 'trx_status_escrow_paid_delivery_ikan_idx')) {
                    $table->dropIndex('trx_status_escrow_paid_delivery_ikan_idx');
                }
            });
        }

        if (Schema::hasTable('ikans')) {
            Schema::table('ikans', function (Blueprint $table): void {
                if (Schema::hasIndex('ikans', 'ikans_user_tipe_id_idx')) {
                    $table->dropIndex('ikans_user_tipe_id_idx');
                }
            });
        }
    }
};
