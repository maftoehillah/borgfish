<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\SellerSettlement;
use App\Models\SystemSetting;
use App\Models\Transaksi;
use App\Models\TransactionDispute;
use App\Models\User;
use App\Services\TransaksiFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerSettlementAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_is_auto_created_when_buyer_completes_transaction(): void
    {
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'fulfillment_state' => 'DIKIRIM',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subHour(),
        ]);

        app(TransaksiFulfillmentService::class)->markCompletedByBuyer($transaksi, (int) $buyer->id);

        $this->assertDatabaseHas('seller_settlements', [
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'amount' => '160000.00',
            'created_by_id' => $buyer->id,
        ]);
    }

    public function test_settlement_is_not_duplicated_when_completion_runs_again(): void
    {
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'fulfillment_state' => 'DIKIRIM',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subHour(),
        ]);

        $service = app(TransaksiFulfillmentService::class);
        $service->markCompletedByBuyer($transaksi, (int) $buyer->id);
        $service->markCompletedByBuyer($transaksi->fresh(), (int) $buyer->id);

        $this->assertSame(
            1,
            SellerSettlement::query()
                ->where('transaksi_id', $transaksi->id)
                ->count()
        );
    }

    public function test_settlement_is_auto_created_when_admin_resolves_dispute_to_completed(): void
    {
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $admin = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => true,
            'email' => User::SUPERADMIN_EMAIL,
        ]);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'proses',
            'fulfillment_state' => 'DISENGKETAKAN',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subDay(),
        ]);

        TransactionDispute::create([
            'transaksi_id' => $transaksi->id,
            'ikan_id' => $ikan->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'open',
            'complaint_reason' => 'barang_tidak_sesuai',
            'complaint_detail' => 'Perlu review admin.',
            'opened_by_type' => 'buyer',
            'opened_by_id' => $buyer->id,
            'opened_at' => now()->subHours(2),
        ]);

        app(TransaksiFulfillmentService::class)->resolveOpenDisputeByAdmin(
            $transaksi,
            (int) $admin->id,
            'completed',
            'Sengketa selesai dan transaksi dianggap selesai.'
        );

        $this->assertDatabaseHas('seller_settlements', [
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'created_by_id' => $admin->id,
        ]);
    }

    public function test_settlement_is_not_auto_created_when_setting_is_disabled(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'settlement_auto_create_enabled'],
            ['group' => 'settlement', 'value' => '0', 'type' => 'boolean']
        );

        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'fulfillment_state' => 'DIKIRIM',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subHour(),
        ]);

        app(TransaksiFulfillmentService::class)->markCompletedByBuyer($transaksi, (int) $buyer->id);

        $this->assertDatabaseMissing('seller_settlements', [
            'transaksi_id' => $transaksi->id,
        ]);
    }

    public function test_settlement_can_start_ready_to_pay_when_review_and_delay_are_disabled(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'settlement_requires_admin_review'],
            ['group' => 'settlement', 'value' => '0', 'type' => 'boolean']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'settlement_payout_delay_days'],
            ['group' => 'settlement', 'value' => '0', 'type' => 'integer']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'settlement_hold_on_dispute'],
            ['group' => 'settlement', 'value' => '0', 'type' => 'boolean']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'settlement_hold_on_violation'],
            ['group' => 'settlement', 'value' => '0', 'type' => 'boolean']
        );

        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'fulfillment_state' => 'DIKIRIM',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subHour(),
        ]);

        app(TransaksiFulfillmentService::class)->markCompletedByBuyer($transaksi, (int) $buyer->id);

        $this->assertDatabaseHas('seller_settlements', [
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'status' => 'ready_to_pay',
            'created_by_id' => $buyer->id,
        ]);
    }

    public function test_settlement_with_delay_is_promoted_when_delay_has_passed(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'settlement_requires_admin_review'],
            ['group' => 'settlement', 'value' => '0', 'type' => 'boolean']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'settlement_payout_delay_days'],
            ['group' => 'settlement', 'value' => '2', 'type' => 'integer']
        );

        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $ikan = $this->makeLot($seller);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'fulfillment_state' => 'DIKIRIM',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subHour(),
        ]);

        app(TransaksiFulfillmentService::class)->markCompletedByBuyer($transaksi, (int) $buyer->id);

        $settlement = SellerSettlement::query()->where('transaksi_id', $transaksi->id)->firstOrFail();
        $this->assertSame('pending', (string) $settlement->status);
        $this->assertTrue($settlement->ready_to_pay_at->isFuture());

        $this->travel(3)->days();

        $promoted = app(\App\Services\SellerSettlementService::class)->promoteReadySettlements();

        $this->assertSame(1, $promoted);
        $this->assertDatabaseHas('seller_settlements', [
            'id' => $settlement->id,
            'status' => 'ready_to_pay',
        ]);
    }

    private function makeLot(User $seller): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Cakalang Uji Settlement',
            'berat' => 15,
            'estimasi_jumlah_ekor' => 25,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot test settlement automation',
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
    }

    private function makeTransaksi(Ikan $ikan, User $buyer, array $overrides = []): Transaksi
    {
        return Transaksi::create(array_merge([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 160_000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'fulfillment_state' => 'DIBAYAR',
            'state_version' => 1,
            'pickup_status' => 'awaiting_pickup',
            'dibayar_pada' => now()->subHours(4),
            'paid_at' => now()->subHours(4),
            'seller_ack_deadline_at' => now()->addHour(),
            'seller_process_deadline_at' => now()->addHours(20),
        ], $overrides));
    }
}
