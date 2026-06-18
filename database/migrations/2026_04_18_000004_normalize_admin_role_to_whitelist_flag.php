<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role') || ! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        DB::table('users')
            ->where('role', 'superadmin')
            ->update([
                'role' => 'pembeli',
                'is_admin' => true,
            ]);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('penjual','pembeli') NOT NULL DEFAULT 'pembeli'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('penjual','pembeli','superadmin') NOT NULL DEFAULT 'pembeli'");
        }
    }
};
