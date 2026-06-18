<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\SellerSettlementBatch;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\SellerSettlementBatchPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerSettlementBatchPayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_payout_marks_selected_settlements_as_paid_with_shared_batch_data(): void
    {
        $admin = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => true,
            'email' => User::SUPERADMIN_EMAIL,
        ]);
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $settlements = collect([
            $this->makeSettlement($seller, $buyer, 'ready_to_pay'),
            $this->makeSettlement($seller, $buyer, 'ready_to_pay'),
        ]);

        $batch = app(SellerSettlementBatchPayoutService::class)->markAsPaid(
            settlementIds: $settlements->pluck('id')->all(),
            actorId: (int) $admin->id,
            transferReference: 'BATCH-TRF-001',
            transferProofPath: 'seller-settlements/batch-proof.jpg',
            adminNote: 'Payout batch Jumat sore.',
        );

        $this->assertInstanceOf(SellerSettlementBatch::class, $batch);
        $this->assertSame(2, (int) $batch->settlement_count);
        $this->assertSame('160000.00', number_format((float) $batch->total_amount / 2, 2, '.', ''));

        foreach ($settlements as $settlement) {
            $this->assertDatabaseHas('seller_settlements', [
                'id' => $settlement->id,
                'batch_id' => $batch->id,
                'status' => 'paid',
                'transfer_reference' => 'BATCH-TRF-001',
                'transfer_proof_path' => 'seller-settlements/batch-proof.jpg',
                'updated_by_id' => $admin->id,
            ]);
        }
    }

    public function test_batch_payout_skips_cancelled_or_already_paid_records(): void
    {
        $admin = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => true,
            'email' => User::SUPERADMIN_EMAIL,
        ]);
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $eligible = $this->makeSettlement($seller, $buyer, 'ready_to_pay');
        $ineligiblePending = $this->makeSettlement($seller, $buyer, 'pending');
        $ineligibleHeld = $this->makeSettlement($seller, $buyer, 'held');
        $ineligiblePaid = $this->makeSettlement($seller, $buyer, 'paid');
        $ineligibleCancelled = $this->makeSettlement($seller, $buyer, 'cancelled');

        $batch = app(SellerSettlementBatchPayoutService::class)->markAsPaid(
            settlementIds: [$eligible->id, $ineligiblePending->id, $ineligibleHeld->id, $ineligiblePaid->id, $ineligibleCancelled->id],
            actorId: (int) $admin->id,
            transferReference: 'BATCH-TRF-002',
        );

        $this->assertInstanceOf(SellerSettlementBatch::class, $batch);
        $this->assertSame(1, (int) $batch->settlement_count);

        $this->assertDatabaseHas('seller_settlements', [
            'id' => $eligible->id,
            'batch_id' => $batch->id,
            'status' => 'paid',
            'transfer_reference' => 'BATCH-TRF-002',
        ]);

        $this->assertDatabaseHas('seller_settlements', [
            'id' => $ineligibleCancelled->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('seller_settlements', [
            'id' => $ineligibleHeld->id,
            'status' => 'held',
        ]);
    }

    public function test_batch_payout_requires_transfer_reference_or_proof(): void
    {
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $settlement = $this->makeSettlement($seller, $buyer, 'ready_to_pay');

        $this->expectException(\InvalidArgumentException::class);

        app(SellerSettlementBatchPayoutService::class)->markAsPaid(
            settlementIds: [$settlement->id],
            transferReference: null,
            transferProofPath: null,
        );
    }

    private function makeSettlement(User $seller, User $buyer, string $status): SellerSettlement
    {
        $ikan = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Cakalang Uji Batch Payout',
            'berat' => 15,
            'estimasi_jumlah_ekor' => 25,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot test batch payout settlement',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(20),
            'waktu_selesai' => now()->subMinutes(5),
            'status' => 'terbayar',
            'state_version' => 1,
        ]);

        $transaksi = Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 160_000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'fulfillment_state' => 'SELESAI',
            'state_version' => 1,
            'pickup_status' => 'completed',
            'dibayar_pada' => now()->subHours(4),
            'paid_at' => now()->subHours(4),
            'completed_by_buyer_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);

        return SellerSettlement::create([
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'amount' => 160000,
            'status' => $status,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => $seller->name,
        ]);
    }
}
