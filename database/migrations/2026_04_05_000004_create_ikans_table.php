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
        if (! Schema::hasTable('ikans')) {
            Schema::create('ikans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('nama_ikan', 191);
                $table->decimal('berat', 8, 2);
                $table->unsignedInteger('estimasi_jumlah_ekor')->nullable();
                $table->enum('jenis_kemasan', ['keranjang', 'styrofoam', 'curah', 'vakum', 'lainnya'])->nullable();
                $table->enum('kondisi', ['segar', 'beku', 'kering'])->default('segar');
                $table->text('deskripsi')->nullable();
                $table->string('asal_pelabuhan')->nullable();
                $table->date('tanggal_tangkap')->nullable();
                $table->string('metode_tangkap')->nullable();
                $table->string('grade_mutu', 10)->nullable();
                $table->decimal('suhu_penyimpanan', 5, 2)->nullable();
                $table->string('surveyor')->nullable();
                $table->text('catatan_survey')->nullable();
                $table->dateTime('verifikasi_pelabuhan_at')->nullable();
                $table->decimal('harga_awal', 15, 2);
                $table->decimal('harga_tertinggi', 15, 2);
                $table->decimal('minimal_increment', 15, 2);
                $table->boolean('buy_now_enabled')->default(false);
                $table->decimal('buy_now_price', 15, 2)->nullable();
                $table->boolean('anti_sniping_enabled')->default(true);
                $table->unsignedInteger('anti_sniping_window_seconds')->default(120);
                $table->unsignedInteger('anti_sniping_extend_seconds')->default(120);
                $table->unsignedTinyInteger('anti_sniping_max_extensions')->default(3);
                $table->unsignedTinyInteger('anti_sniping_extensions_used')->default(0);
                $table->dateTime('waktu_mulai');
                $table->dateTime('waktu_selesai');
                $table->enum('status', ['menunggu', 'aktif', 'selesai', 'terbayar'])->default('menunggu');
                $table->unsignedBigInteger('last_bidder_id')->nullable();
                $table->dateTime('last_bid_at')->nullable();
                $table->unsignedBigInteger('state_version')->default(0);
                $table->string('foto')->nullable();
                $table->string('video')->nullable();
                $table->decimal('foto_latitude', 10, 7)->nullable();
                $table->decimal('foto_longitude', 10, 7)->nullable();
                $table->dateTime('foto_diambil_pada')->nullable();
                $table->timestamps();

                $table->index(['status', 'waktu_mulai', 'waktu_selesai']);
                $table->index(['buy_now_enabled', 'buy_now_price']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ikans');
    }
};
