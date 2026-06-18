<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // ensure payout_external_id uniqueness to avoid mapping ambiguity
            $table->unique('payout_external_id', 'withdrawals_payout_external_id_unique');
            $table->index('status', 'withdrawals_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropUnique('withdrawals_payout_external_id_unique');
            $table->dropIndex('withdrawals_status_index');
        });
    }
};
