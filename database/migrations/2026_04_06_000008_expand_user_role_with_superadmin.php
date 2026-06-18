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
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('penjual','pembeli','superadmin') NOT NULL DEFAULT 'pembeli'");
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

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE `users` SET `role` = 'penjual' WHERE `role` = 'superadmin'");
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('penjual','pembeli') NOT NULL DEFAULT 'pembeli'");
        }
    }
};
