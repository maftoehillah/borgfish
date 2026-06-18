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
        if (Schema::hasTable('bids')) {
            Schema::table('bids', function (Blueprint $table): void {
                if (! Schema::hasIndex('bids', 'bids_user_ikan_id_idx')) {
                    $table->index(['user_id', 'ikan_id', 'id'], 'bids_user_ikan_id_idx');
                }

                if (! Schema::hasIndex('bids', 'bids_ikan_amount_id_idx')) {
                    $table->index(['ikan_id', 'jumlah_bid', 'id'], 'bids_ikan_amount_id_idx');
                }

                if (! Schema::hasIndex('bids', 'bids_ikan_user_amount_idx')) {
                    $table->index(['ikan_id', 'user_id', 'jumlah_bid'], 'bids_ikan_user_amount_idx');
                }
            });
        }

        if (Schema::hasTable('ikans')) {
            Schema::table('ikans', function (Blueprint $table): void {
                if (! Schema::hasIndex('ikans', 'ikans_status_tipe_end_idx')) {
                    $table->index(['status', 'tipe_lelang', 'waktu_selesai'], 'ikans_status_tipe_end_idx');
                }

                if (! Schema::hasIndex('ikans', 'ikans_user_tipe_status_created_idx')) {
                    $table->index(['user_id', 'tipe_lelang', 'status', 'created_at'], 'ikans_user_tipe_status_created_idx');
                }
            });
        }

        if (Schema::hasTable('transaksis')) {
            Schema::table('transaksis', function (Blueprint $table): void {
                if (! Schema::hasIndex('transaksis', 'trx_status_escrow_delivery_paid_idx')) {
                    $table->index(['status', 'escrow_status', 'delivery_status', 'dibayar_pada'], 'trx_status_escrow_delivery_paid_idx');
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
                if (Schema::hasIndex('transaksis', 'trx_status_escrow_delivery_paid_idx')) {
                    $table->dropIndex('trx_status_escrow_delivery_paid_idx');
                }
            });
        }

        if (Schema::hasTable('ikans')) {
            Schema::table('ikans', function (Blueprint $table): void {
                if (Schema::hasIndex('ikans', 'ikans_status_tipe_end_idx')) {
                    $table->dropIndex('ikans_status_tipe_end_idx');
                }

                if (Schema::hasIndex('ikans', 'ikans_user_tipe_status_created_idx')) {
                    $table->dropIndex('ikans_user_tipe_status_created_idx');
                }
            });
        }

        if (Schema::hasTable('bids')) {
            Schema::table('bids', function (Blueprint $table): void {
                if (Schema::hasIndex('bids', 'bids_user_ikan_id_idx')) {
                    $table->dropIndex('bids_user_ikan_id_idx');
                }

                if (Schema::hasIndex('bids', 'bids_ikan_amount_id_idx')) {
                    $table->dropIndex('bids_ikan_amount_id_idx');
                }

                if (Schema::hasIndex('bids', 'bids_ikan_user_amount_idx')) {
                    $table->dropIndex('bids_ikan_user_amount_idx');
                }
            });
        }
    }
};
