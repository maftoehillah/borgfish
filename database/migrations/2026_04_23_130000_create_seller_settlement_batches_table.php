<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seller_settlement_batches')) {
            Schema::create('seller_settlement_batches', function (Blueprint $table): void {
                $table->id();
                $table->string('batch_number', 40)->unique();
                $table->string('status', 30)->default('paid');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->unsignedInteger('settlement_count')->default(0);
                $table->string('transfer_reference', 120)->nullable();
                $table->string('transfer_proof_path')->nullable();
                $table->text('admin_note')->nullable();
                $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'processed_at']);
            });
        }

        Schema::table('seller_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('seller_settlements', 'batch_id')) {
                $table->foreignId('batch_id')
                    ->nullable()
                    ->after('seller_id')
                    ->constrained('seller_settlement_batches')
                    ->nullOnDelete();
                $table->index(['batch_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('seller_settlements', function (Blueprint $table): void {
            if (Schema::hasColumn('seller_settlements', 'batch_id')) {
                $table->dropConstrainedForeignId('batch_id');
            }
        });

        Schema::dropIfExists('seller_settlement_batches');
    }
};
