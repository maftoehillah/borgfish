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
            Schema::table('ikans', function (Blueprint $table) {
                if (! Schema::hasColumn('ikans', 'deskripsi')) {
                    $table->text('deskripsi')->nullable()->after('kondisi');
                }

                if (! Schema::hasColumn('ikans', 'estimasi_jumlah_ekor')) {
                    $table->unsignedInteger('estimasi_jumlah_ekor')->nullable()->after('berat');
                }

                if (! Schema::hasColumn('ikans', 'jenis_kemasan')) {
                    $table->enum('jenis_kemasan', ['keranjang', 'styrofoam', 'curah', 'vakum', 'lainnya'])->nullable()->after('estimasi_jumlah_ekor');
                }

                if (! Schema::hasColumn('ikans', 'foto')) {
                    $table->string('foto')->nullable()->after('status');
                }

                if (! Schema::hasColumn('ikans', 'asal_pelabuhan')) {
                    $table->string('asal_pelabuhan')->nullable()->after('deskripsi');
                }

                if (! Schema::hasColumn('ikans', 'tanggal_tangkap')) {
                    $table->date('tanggal_tangkap')->nullable()->after('asal_pelabuhan');
                }

                if (! Schema::hasColumn('ikans', 'metode_tangkap')) {
                    $table->string('metode_tangkap')->nullable()->after('tanggal_tangkap');
                }

                if (! Schema::hasColumn('ikans', 'grade_mutu')) {
                    $table->string('grade_mutu', 10)->nullable()->after('metode_tangkap');
                }

                if (! Schema::hasColumn('ikans', 'suhu_penyimpanan')) {
                    $table->decimal('suhu_penyimpanan', 5, 2)->nullable()->after('grade_mutu');
                }

                if (! Schema::hasColumn('ikans', 'surveyor')) {
                    $table->string('surveyor')->nullable()->after('suhu_penyimpanan');
                }

                if (! Schema::hasColumn('ikans', 'catatan_survey')) {
                    $table->text('catatan_survey')->nullable()->after('surveyor');
                }

                if (! Schema::hasColumn('ikans', 'verifikasi_pelabuhan_at')) {
                    $table->dateTime('verifikasi_pelabuhan_at')->nullable()->after('catatan_survey');
                }

                if (! Schema::hasColumn('ikans', 'buy_now_enabled')) {
                    $table->boolean('buy_now_enabled')->default(false)->after('minimal_increment');
                }

                if (! Schema::hasColumn('ikans', 'buy_now_price')) {
                    $table->decimal('buy_now_price', 15, 2)->nullable()->after('buy_now_enabled');
                }

                if (! Schema::hasColumn('ikans', 'anti_sniping_enabled')) {
                    $table->boolean('anti_sniping_enabled')->default(true)->after('buy_now_price');
                }

                if (! Schema::hasColumn('ikans', 'anti_sniping_window_seconds')) {
                    $table->unsignedInteger('anti_sniping_window_seconds')->default(120)->after('anti_sniping_enabled');
                }

                if (! Schema::hasColumn('ikans', 'anti_sniping_extend_seconds')) {
                    $table->unsignedInteger('anti_sniping_extend_seconds')->default(120)->after('anti_sniping_window_seconds');
                }

                if (! Schema::hasColumn('ikans', 'anti_sniping_max_extensions')) {
                    $table->unsignedTinyInteger('anti_sniping_max_extensions')->default(3)->after('anti_sniping_extend_seconds');
                }

                if (! Schema::hasColumn('ikans', 'anti_sniping_extensions_used')) {
                    $table->unsignedTinyInteger('anti_sniping_extensions_used')->default(0)->after('anti_sniping_max_extensions');
                }

                if (! Schema::hasColumn('ikans', 'last_bidder_id')) {
                    $table->unsignedBigInteger('last_bidder_id')->nullable()->after('status');
                }

                if (! Schema::hasColumn('ikans', 'last_bid_at')) {
                    $table->dateTime('last_bid_at')->nullable()->after('last_bidder_id');
                }

                if (! Schema::hasColumn('ikans', 'state_version')) {
                    $table->unsignedBigInteger('state_version')->default(0)->after('last_bid_at');
                }

                if (! Schema::hasColumn('ikans', 'video')) {
                    $table->string('video')->nullable()->after('foto');
                }

                if (! Schema::hasColumn('ikans', 'foto_latitude')) {
                    $table->decimal('foto_latitude', 10, 7)->nullable()->after('video');
                }

                if (! Schema::hasColumn('ikans', 'foto_longitude')) {
                    $table->decimal('foto_longitude', 10, 7)->nullable()->after('foto_latitude');
                }

                if (! Schema::hasColumn('ikans', 'foto_diambil_pada')) {
                    $table->dateTime('foto_diambil_pada')->nullable()->after('foto_longitude');
                }
            });

            DB::statement('UPDATE ikans SET state_version = 0 WHERE state_version IS NULL');
        }

        if (Schema::hasTable('bids')) {
            $hasJumlahBid = Schema::hasColumn('bids', 'jumlah_bid');
            $hasNilaiBid = Schema::hasColumn('bids', 'nilai_bid');

            if (! $hasJumlahBid) {
                Schema::table('bids', function (Blueprint $table) {
                    $table->decimal('jumlah_bid', 15, 2)->default(0)->after('user_id');
                });
            }

            if ($hasNilaiBid) {
                DB::statement('UPDATE bids SET jumlah_bid = nilai_bid WHERE (jumlah_bid IS NULL OR jumlah_bid = 0)');
            }

            Schema::table('bids', function (Blueprint $table) {
                if (! Schema::hasColumn('bids', 'bidder_ip')) {
                    $table->string('bidder_ip', 45)->nullable()->after('jumlah_bid');
                }

                if (! Schema::hasColumn('bids', 'bidder_user_agent')) {
                    $table->string('bidder_user_agent', 255)->nullable()->after('bidder_ip');
                }

                if (! Schema::hasColumn('bids', 'is_suspicious')) {
                    $table->boolean('is_suspicious')->default(false)->after('bidder_user_agent');
                }

                if (! Schema::hasColumn('bids', 'suspicion_reason')) {
                    $table->string('suspicion_reason')->nullable()->after('is_suspicious');
                }
            });
        }

        if (Schema::hasTable('transaksis')) {
            Schema::table('transaksis', function (Blueprint $table) {
                if (! Schema::hasColumn('transaksis', 'metode_pembayaran')) {
                    $table->string('metode_pembayaran')->nullable()->after('status');
                }

                if (! Schema::hasColumn('transaksis', 'snap_token')) {
                    $table->string('snap_token')->nullable()->after('metode_pembayaran');
                }

                if (! Schema::hasColumn('transaksis', 'midtrans_order_id')) {
                    $table->string('midtrans_order_id')->nullable()->unique()->after('snap_token');
                }

                if (! Schema::hasColumn('transaksis', 'bayar_sebelum')) {
                    $table->dateTime('bayar_sebelum')->nullable()->after('midtrans_order_id');
                }

                if (! Schema::hasColumn('transaksis', 'dibayar_pada')) {
                    $table->dateTime('dibayar_pada')->nullable()->after('bayar_sebelum');
                }

                if (! Schema::hasColumn('transaksis', 'escrow_status')) {
                    $table->enum('escrow_status', ['belum', 'ditahan', 'dilepas', 'hangus'])->default('belum')->after('dibayar_pada');
                }

                if (! Schema::hasColumn('transaksis', 'escrow_amount')) {
                    $table->decimal('escrow_amount', 15, 2)->default(0)->after('escrow_status');
                }

                if (! Schema::hasColumn('transaksis', 'escrow_locked_at')) {
                    $table->dateTime('escrow_locked_at')->nullable()->after('escrow_amount');
                }

                if (! Schema::hasColumn('transaksis', 'escrow_released_at')) {
                    $table->dateTime('escrow_released_at')->nullable()->after('escrow_locked_at');
                }

                if (! Schema::hasColumn('transaksis', 'escrow_forfeited_at')) {
                    $table->dateTime('escrow_forfeited_at')->nullable()->after('escrow_released_at');
                }

                if (! Schema::hasColumn('transaksis', 'delivery_status')) {
                    $table->enum('delivery_status', ['menunggu_pengiriman', 'diproses', 'dikirim', 'diterima'])->default('menunggu_pengiriman')->after('escrow_forfeited_at');
                }

                if (! Schema::hasColumn('transaksis', 'delivery_method')) {
                    $table->enum('delivery_method', ['pickup', 'kurir_darat', 'kargo_laut', 'kargo_udara'])->nullable()->after('delivery_status');
                }

                if (! Schema::hasColumn('transaksis', 'delivery_cost')) {
                    $table->decimal('delivery_cost', 15, 2)->default(0)->after('delivery_method');
                }

                if (! Schema::hasColumn('transaksis', 'courier_name')) {
                    $table->string('courier_name')->nullable()->after('delivery_cost');
                }

                if (! Schema::hasColumn('transaksis', 'tracking_number')) {
                    $table->string('tracking_number')->nullable()->after('courier_name');
                }

                if (! Schema::hasColumn('transaksis', 'packed_at')) {
                    $table->dateTime('packed_at')->nullable()->after('tracking_number');
                }

                if (! Schema::hasColumn('transaksis', 'packing_proof')) {
                    $table->string('packing_proof')->nullable()->after('packed_at');
                }

                if (! Schema::hasColumn('transaksis', 'shipped_at')) {
                    $table->dateTime('shipped_at')->nullable()->after('packing_proof');
                }

                if (! Schema::hasColumn('transaksis', 'estimated_arrival_at')) {
                    $table->dateTime('estimated_arrival_at')->nullable()->after('shipped_at');
                }

                if (! Schema::hasColumn('transaksis', 'delivered_at')) {
                    $table->dateTime('delivered_at')->nullable()->after('estimated_arrival_at');
                }

                if (! Schema::hasColumn('transaksis', 'released_by_buyer_at')) {
                    $table->dateTime('released_by_buyer_at')->nullable()->after('delivered_at');
                }
            });

            DB::statement("UPDATE transaksis SET escrow_amount = harga_final WHERE escrow_amount IS NULL OR escrow_amount = 0");
            DB::statement("UPDATE transaksis SET escrow_status = 'ditahan', escrow_locked_at = COALESCE(escrow_locked_at, dibayar_pada, updated_at) WHERE status = 'lunas' AND (escrow_status IS NULL OR escrow_status = 'belum')");
            DB::statement("UPDATE transaksis SET delivery_status = 'diproses' WHERE status = 'lunas' AND (delivery_status IS NULL OR delivery_status = 'menunggu_pengiriman')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ikans')) {
            Schema::table('ikans', function (Blueprint $table) {
                if (Schema::hasColumn('ikans', 'deskripsi')) {
                    $table->dropColumn('deskripsi');
                }

                if (Schema::hasColumn('ikans', 'foto')) {
                    $table->dropColumn('foto');
                }
            });
        }

        if (Schema::hasTable('bids')) {
            Schema::table('bids', function (Blueprint $table) {
                if (Schema::hasColumn('bids', 'jumlah_bid')) {
                    $table->dropColumn('jumlah_bid');
                }
            });
        }

        if (Schema::hasTable('transaksis')) {
            Schema::table('transaksis', function (Blueprint $table) {
                if (Schema::hasColumn('transaksis', 'metode_pembayaran')) {
                    $table->dropColumn('metode_pembayaran');
                }

                if (Schema::hasColumn('transaksis', 'snap_token')) {
                    $table->dropColumn('snap_token');
                }

                if (Schema::hasColumn('transaksis', 'midtrans_order_id')) {
                    $table->dropUnique('transaksis_midtrans_order_id_unique');
                    $table->dropColumn('midtrans_order_id');
                }

                if (Schema::hasColumn('transaksis', 'bayar_sebelum')) {
                    $table->dropColumn('bayar_sebelum');
                }

                if (Schema::hasColumn('transaksis', 'dibayar_pada')) {
                    $table->dropColumn('dibayar_pada');
                }
            });
        }
    }
};
