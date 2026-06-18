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
        if (! Schema::hasColumn('ikans', 'payment_deadline_minutes')) {
            Schema::table('ikans', function (Blueprint $table): void {
                $table->unsignedSmallInteger('payment_deadline_minutes')
                    ->default(1440)
                    ->after('buy_now_price');
            });
        }

        if (Schema::hasColumn('ikans', 'payment_deadline_hours') && Schema::hasColumn('ikans', 'payment_deadline_minutes')) {
            DB::table('ikans')
                ->select(['id', 'payment_deadline_hours'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $legacyHours = (int) ($row->payment_deadline_hours ?? 24);
                        if ($legacyHours <= 0) {
                            $legacyHours = 24;
                        }

                        $deadlineMinutes = max(1, min(4320, $legacyHours * 60));

                        DB::table('ikans')
                            ->where('id', (int) $row->id)
                            ->update([
                                'payment_deadline_minutes' => $deadlineMinutes,
                            ]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ikans', 'payment_deadline_minutes')) {
            Schema::table('ikans', function (Blueprint $table): void {
                $table->dropColumn('payment_deadline_minutes');
            });
        }
    }
};
