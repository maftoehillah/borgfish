<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_attempts') || ! Schema::hasColumn('payment_attempts', 'provider')) {
            return;
        }

        DB::table('payment_attempts')
            ->where('provider', 'threepay')
            ->update(['provider' => 'tripay']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_attempts') || ! Schema::hasColumn('payment_attempts', 'provider')) {
            return;
        }

        DB::table('payment_attempts')
            ->where('provider', 'tripay')
            ->update(['provider' => 'threepay']);
    }
};
