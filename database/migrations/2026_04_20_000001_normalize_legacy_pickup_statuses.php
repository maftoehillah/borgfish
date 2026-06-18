<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaksis') || ! Schema::hasColumn('transaksis', 'pickup_status')) {
            return;
        }

        DB::table('transaksis')
            ->whereIn('pickup_status', ['pickup_submitted', 'pickup_on_the_way'])
            ->update(['pickup_status' => 'awaiting_pickup']);
    }

    public function down(): void
    {
        // Not safely reversible: awaiting_pickup is also the current valid status.
    }
};
