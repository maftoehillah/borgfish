<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seller_profiles')) {
            return;
        }

        Schema::table('seller_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('seller_profiles', 'store_latitude')) {
                $table->decimal('store_latitude', 10, 7)->nullable()->after('full_address');
            }

            if (! Schema::hasColumn('seller_profiles', 'store_longitude')) {
                $table->decimal('store_longitude', 10, 7)->nullable()->after('store_latitude');
            }

            if (! Schema::hasColumn('seller_profiles', 'store_gps_accuracy')) {
                $table->decimal('store_gps_accuracy', 8, 2)->nullable()->after('store_longitude');
            }

            if (! Schema::hasColumn('seller_profiles', 'store_gps_captured_at')) {
                $table->timestamp('store_gps_captured_at')->nullable()->after('store_gps_accuracy');
            }

            if (! Schema::hasColumn('seller_profiles', 'store_photo_path')) {
                $table->string('store_photo_path')->nullable()->after('store_gps_captured_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('seller_profiles')) {
            return;
        }

        Schema::table('seller_profiles', function (Blueprint $table): void {
            foreach ([
                'store_photo_path',
                'store_gps_captured_at',
                'store_gps_accuracy',
                'store_longitude',
                'store_latitude',
            ] as $column) {
                if (Schema::hasColumn('seller_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
