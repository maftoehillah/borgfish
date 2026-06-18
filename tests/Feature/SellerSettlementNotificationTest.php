<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\NotificationOutboxService;
use App\Services\SellerSettlementBatchPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerSettlementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_to_pay_notification_is_sent_to_seller(): void
    {
        [$seller, $settlement] = $this->makeSettlementFixture('pending');

        $settlement->status = 'ready_to_pay';
        $settlement->ready_to_pay_at = now();
        $settlement->save();

        app(NotificationOutboxService::class)->queueForSellerSettlementReady($settlement->fresh());
        app(NotificationOutboxService::class)->processPending(50);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $seller->id,
            'category' => 'operasional',
            'title' => 'Settlement siap diproses',
        ]);
    }

    public function test_paid_notification_is_sent_to_seller_after_batch_payout(): void
    {
        [$seller, $settlement] = $this->makeSettlementFixture('ready_to_pay');
        $admin = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => true,
            'email' => User::SUPERADMIN_EMAIL,
        ]);

        app(SellerSettlementBatchPayoutService::class)->markAsPaid(
            settlementIds: [$settlement->id],
            actorId: (int) $admin->id,
            transferReference: 'TRF-NOTIF-001',
        );

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $seller->id,
            'category' => 'operasional',
            'title' => 'Settlement sudah dibayar',
        ]);
    }

    private function makeSettlementFixture(string $status): array
    {
        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);

        $ikan = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Cakalang Uji Notifikasi Settlement',
            'berat' => 15,
            'estimasi_jumlah_ekor' => 25,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot test notifikasi settlement',
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

        $settlement = SellerSettlement::create([
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'amount' => 160000,
            'status' => $status,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => $seller->name,
            'ready_to_pay_at' => $status === 'ready_to_pay' ? now()->subMinutes(10) : null,
        ]);

        return [$seller, $settlement];
    }
}
