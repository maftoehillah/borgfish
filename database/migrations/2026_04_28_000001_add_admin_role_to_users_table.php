<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'admin_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('admin_role', 30)->nullable()->after('is_admin');
                $table->index(['is_admin', 'admin_role']);
            });
        }

        DB::table('users')
            ->where('is_admin', true)
            ->whereNull('admin_role')
            ->update(['admin_role' => 'ops']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'admin_role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', ['is_admin', 'admin_role'])) {
                $table->dropIndex(['is_admin', 'admin_role']);
            }

            $table->dropColumn('admin_role');
        });
    }
};
