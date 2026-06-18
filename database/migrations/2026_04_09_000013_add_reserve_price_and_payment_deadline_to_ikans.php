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

        Schema::table('ikans', function (Blueprint $table) {
            if (! Schema::hasColumn('ikans', 'reserve_price')) {
                $table->decimal('reserve_price', 15, 2)->nullable()->after('harga_tertinggi');
            }

            if (! Schema::hasColumn('ikans', 'payment_deadline_hours')) {
                $table->unsignedSmallInteger('payment_deadline_hours')->default(24)->after('buy_now_price');
            }
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

        Schema::table('ikans', function (Blueprint $table) {
            if (Schema::hasColumn('ikans', 'payment_deadline_hours')) {
                $table->dropColumn('payment_deadline_hours');
            }

            if (Schema::hasColumn('ikans', 'reserve_price')) {
                $table->dropColumn('reserve_price');
            }
        });
    }
};
