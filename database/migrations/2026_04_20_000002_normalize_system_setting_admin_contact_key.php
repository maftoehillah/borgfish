<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $legacy = DB::table('system_settings')->where('key', 'admin_contact')->first();
        $normalized = DB::table('system_settings')->where('key', 'site_admin_contact')->first();

        if ($legacy && ! $normalized) {
            DB::table('system_settings')
                ->where('id', $legacy->id)
                ->update([
                    'key' => 'site_admin_contact',
                    'group' => 'site',
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($legacy && $normalized) {
            DB::table('system_settings')->where('id', $legacy->id)->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $normalized = DB::table('system_settings')->where('key', 'site_admin_contact')->first();
        $legacy = DB::table('system_settings')->where('key', 'admin_contact')->first();

        if ($normalized && ! $legacy) {
            DB::table('system_settings')
                ->where('id', $normalized->id)
                ->update([
                    'key' => 'admin_contact',
                    'group' => 'site',
                    'updated_at' => now(),
                ]);
        }
    }
};
