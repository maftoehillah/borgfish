<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        $scoreColumns = [
            'skor_ai',
            'skor_survey',
            'skor_final',
            'skor_kecerahan_media',
            'skor_ketajaman_media',
            'skor_media',
        ];

        $dropColumns = array_values(array_filter(
            $scoreColumns,
            fn (string $column): bool => Schema::hasColumn('ikans', $column)
        ));

        if ($dropColumns === []) {
            return;
        }

        Schema::table('ikans', function (Blueprint $table) use ($dropColumns): void {
            $table->dropColumn($dropColumns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ikans')) {
            return;
        }

        Schema::table('ikans', function (Blueprint $table): void {
            if (! Schema::hasColumn('ikans', 'skor_ai')) {
                $table->decimal('skor_ai', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('ikans', 'skor_survey')) {
                $table->decimal('skor_survey', 5, 2)->nullable();
            }

            if (! Schema::hasColumn('ikans', 'skor_final')) {
                $table->decimal('skor_final', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('ikans', 'skor_kecerahan_media')) {
                $table->decimal('skor_kecerahan_media', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('ikans', 'skor_ketajaman_media')) {
                $table->decimal('skor_ketajaman_media', 5, 2)->default(0);
            }

            if (! Schema::hasColumn('ikans', 'skor_media')) {
                $table->decimal('skor_media', 5, 2)->default(0);
            }
        });
    }
};
