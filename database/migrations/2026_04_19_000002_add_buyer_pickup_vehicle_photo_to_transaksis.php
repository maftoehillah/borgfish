<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaksis') || Schema::hasColumn('transaksis', 'buyer_pickup_vehicle_photo')) {
            return;
        }

        Schema::table('transaksis', function (Blueprint $table): void {
            $table->string('buyer_pickup_vehicle_photo')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transaksis') || ! Schema::hasColumn('transaksis', 'buyer_pickup_vehicle_photo')) {
            return;
        }

        Schema::table('transaksis', function (Blueprint $table): void {
            $table->dropColumn('buyer_pickup_vehicle_photo');
        });
    }
};
