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
        $hasInitial = Schema::hasColumn('ikans', 'payment_deadline_initial_minutes');
        $hasFallbackOne = Schema::hasColumn('ikans', 'payment_deadline_fallback_one_minutes');
        $hasFallbackTwo = Schema::hasColumn('ikans', 'payment_deadline_fallback_two_minutes');
        $hasWindowLimit = Schema::hasColumn('ikans', 'payment_window_limit_minutes');

        if (! $hasInitial || ! $hasFallbackOne || ! $hasFallbackTwo || ! $hasWindowLimit) {
            Schema::table('ikans', function (Blueprint $table) use ($hasInitial, $hasFallbackOne, $hasFallbackTwo, $hasWindowLimit): void {
                if (! $hasInitial) {
                    $table->unsignedSmallInteger('payment_deadline_initial_minutes')
                        ->nullable()
                        ->after('payment_deadline_minutes');
                }

                if (! $hasFallbackOne) {
                    $table->unsignedSmallInteger('payment_deadline_fallback_one_minutes')
                        ->nullable()
                        ->after('payment_deadline_initial_minutes');
                }

                if (! $hasFallbackTwo) {
                    $table->unsignedSmallInteger('payment_deadline_fallback_two_minutes')
                        ->nullable()
                        ->after('payment_deadline_fallback_one_minutes');
                }

                if (! $hasWindowLimit) {
                    $table->unsignedSmallInteger('payment_window_limit_minutes')
                        ->nullable()
                        ->after('payment_deadline_fallback_two_minutes');
                }
            });
        }

        if (
            Schema::hasColumn('ikans', 'payment_deadline_minutes')
            && Schema::hasColumn('ikans', 'payment_deadline_initial_minutes')
            && Schema::hasColumn('ikans', 'payment_deadline_fallback_one_minutes')
            && Schema::hasColumn('ikans', 'payment_deadline_fallback_two_minutes')
            && Schema::hasColumn('ikans', 'payment_window_limit_minutes')
        ) {
            DB::table('ikans')
                ->select([
                    'id',
                    'payment_deadline_minutes',
                    'payment_deadline_initial_minutes',
                    'payment_deadline_fallback_one_minutes',
                    'payment_deadline_fallback_two_minutes',
                    'payment_window_limit_minutes',
                ])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $base = max(1, min(4320, (int) ($row->payment_deadline_minutes ?? 1440)));

                        $initial = $row->payment_deadline_initial_minutes !== null
                            ? max(1, min(4320, (int) $row->payment_deadline_initial_minutes))
                            : min($base, 120);

                        $fallbackOne = $row->payment_deadline_fallback_one_minutes !== null
                            ? max(1, min($initial, (int) $row->payment_deadline_fallback_one_minutes))
                            : min($base, 60);

                        $fallbackTwo = $row->payment_deadline_fallback_two_minutes !== null
                            ? max(1, min($fallbackOne, (int) $row->payment_deadline_fallback_two_minutes))
                            : min($base, 30);

                        $windowLimit = $row->payment_window_limit_minutes !== null
                            ? max($initial, min(4320, (int) $row->payment_window_limit_minutes))
                            : 360;

                        DB::table('ikans')
                            ->where('id', $row->id)
                            ->update([
                                'payment_deadline_initial_minutes' => $initial,
                                'payment_deadline_fallback_one_minutes' => $fallbackOne,
                                'payment_deadline_fallback_two_minutes' => $fallbackTwo,
                                'payment_window_limit_minutes' => $windowLimit,
                                'payment_deadline_minutes' => $initial,
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
        Schema::table('ikans', function (Blueprint $table): void {
            foreach ([
                'payment_window_limit_minutes',
                'payment_deadline_fallback_two_minutes',
                'payment_deadline_fallback_one_minutes',
                'payment_deadline_initial_minutes',
            ] as $column) {
                if (Schema::hasColumn('ikans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
