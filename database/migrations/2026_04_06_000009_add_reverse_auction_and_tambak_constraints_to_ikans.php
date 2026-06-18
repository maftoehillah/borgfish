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
        if (! Schema::hasTable('ikans')) {
            return;
        }

        Schema::table('ikans', function (Blueprint $table) {
            if (! Schema::hasColumn('ikans', 'tipe_lelang')) {
                $table->enum('tipe_lelang', ['naik', 'turun'])->default('naik')->after('harga_tertinggi');
            }
        });

        if (Schema::hasColumn('ikans', 'tipe_lelang')) {
            DB::statement("UPDATE ikans SET tipe_lelang = 'naik' WHERE tipe_lelang IS NULL OR tipe_lelang = ''");
        }

        if (Schema::hasColumn('ikans', 'kondisi')) {
            DB::statement("UPDATE ikans SET kondisi = 'beku' WHERE LOWER(kondisi) = 'frozen'");
            DB::statement("UPDATE ikans SET kondisi = 'segar' WHERE kondisi IS NULL OR kondisi NOT IN ('segar', 'beku')");
        }

        if (Schema::hasColumn('ikans', 'jenis_kemasan')) {
            DB::statement("UPDATE ikans SET jenis_kemasan = NULL WHERE jenis_kemasan IS NOT NULL AND jenis_kemasan NOT IN ('keranjang', 'besek', 'styrofoam')");
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ikans MODIFY kondisi ENUM('segar', 'beku') NOT NULL DEFAULT 'segar'");
            DB::statement("ALTER TABLE ikans MODIFY jenis_kemasan ENUM('keranjang', 'besek', 'styrofoam') NULL");
            DB::statement("ALTER TABLE ikans MODIFY tipe_lelang ENUM('naik', 'turun') NOT NULL DEFAULT 'naik'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ikans')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ikans MODIFY kondisi ENUM('segar', 'beku', 'kering') NOT NULL DEFAULT 'segar'");
            DB::statement("ALTER TABLE ikans MODIFY jenis_kemasan ENUM('keranjang', 'styrofoam', 'curah', 'vakum', 'lainnya') NULL");
        }

        Schema::table('ikans', function (Blueprint $table) {
            if (Schema::hasColumn('ikans', 'tipe_lelang')) {
                $table->dropColumn('tipe_lelang');
            }
        });
    }
};
