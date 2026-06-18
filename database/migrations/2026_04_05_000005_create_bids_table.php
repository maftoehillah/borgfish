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
        if (! Schema::hasTable('bids')) {
            Schema::create('bids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ikan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('jumlah_bid', 15, 2);
                $table->string('bidder_ip', 45)->nullable();
                $table->string('bidder_user_agent', 255)->nullable();
                $table->boolean('is_suspicious')->default(false);
                $table->string('suspicion_reason')->nullable();
                $table->timestamps();

                $table->index(['ikan_id', 'jumlah_bid']);
                $table->index(['ikan_id', 'created_at']);
                $table->index(['is_suspicious']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
