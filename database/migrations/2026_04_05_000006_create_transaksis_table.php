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
        if (! Schema::hasTable('transaksis')) {
            Schema::create('transaksis', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pemenang_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('harga_final', 15, 2);
                $table->enum('status', [
                    'menunggu_bayar',
                    'proses',
                    'lunas',
                    'gagal',
                    'kadaluarsa',
                ])->default('menunggu_bayar');
                $table->string('metode_pembayaran')->nullable();
                $table->string('snap_token')->nullable();
                $table->string('midtrans_order_id')->nullable()->unique();
                $table->dateTime('bayar_sebelum')->nullable();
                $table->dateTime('dibayar_pada')->nullable();
                $table->enum('escrow_status', ['belum', 'ditahan', 'dilepas', 'hangus'])->default('belum');
                $table->decimal('escrow_amount', 15, 2)->default(0);
                $table->dateTime('escrow_locked_at')->nullable();
                $table->dateTime('escrow_released_at')->nullable();
                $table->dateTime('escrow_forfeited_at')->nullable();
                $table->enum('delivery_status', ['menunggu_pengiriman', 'diproses', 'dikirim', 'diterima'])->default('menunggu_pengiriman');
                $table->enum('delivery_method', ['pickup', 'kurir_darat', 'kargo_laut', 'kargo_udara'])->nullable();
                $table->decimal('delivery_cost', 15, 2)->default(0);
                $table->string('courier_name')->nullable();
                $table->string('tracking_number')->nullable();
                $table->dateTime('packed_at')->nullable();
                $table->string('packing_proof')->nullable();
                $table->dateTime('shipped_at')->nullable();
                $table->dateTime('estimated_arrival_at')->nullable();
                $table->dateTime('delivered_at')->nullable();
                $table->dateTime('released_by_buyer_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'bayar_sebelum']);
                $table->index(['escrow_status']);
                $table->index(['delivery_status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};
