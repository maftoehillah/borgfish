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
            if (! Schema::hasColumn('seller_profiles', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('store_photo_path');
            }

            if (! Schema::hasColumn('seller_profiles', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            }

            if (! Schema::hasColumn('seller_profiles', 'bank_account_name')) {
                $table->string('bank_account_name', 100)->nullable()->after('bank_account_number');
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
                'bank_account_name',
                'bank_account_number',
                'bank_name',
            ] as $column) {
                if (Schema::hasColumn('seller_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
