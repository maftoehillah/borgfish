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
        if (Schema::hasColumn('ikans', 'payment_deadline_hours')) {
            Schema::table('ikans', function (Blueprint $table): void {
                $table->dropColumn('payment_deadline_hours');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('ikans', 'payment_deadline_hours')) {
            Schema::table('ikans', function (Blueprint $table): void {
                $table->unsignedSmallInteger('payment_deadline_hours')
                    ->default(24)
                    ->after('payment_deadline_minutes');
            });
        }
    }
};
