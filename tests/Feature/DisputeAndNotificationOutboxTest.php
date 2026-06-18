<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\TransactionDispute;
use App\Models\User;
use App\Services\TransaksiFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeAndNotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_open_dispute_and_outbox_delivers_notifications(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');
        $admin = $this->makeUser('penjual', true);

        $ikan = $this->makeLot($seller, ['status' => 'terbayar']);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'lunas',
            'fulfillment_state' => 'DIKIRIM',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->post(route('pembeli.ikans.komplain', $ikan), [
                'complaint_reason' => 'barang_tidak_sesuai',
                'complaint_detail' => 'Kondisi tidak sesuai deskripsi.',
                'return_url' => route('pembeli.aktivitas.detail', $ikan),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('sukses');

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'fulfillment_state' => 'DISENGKETAKAN',
            'status' => 'proses',
        ]);

        $this->assertDatabaseHas('transaction_disputes', [
            'transaksi_id' => $transaksi->id,
            'status' => 'open',
            'complaint_reason' => 'barang_tidak_sesuai',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $buyer->id,
            'category' => 'sengketa',
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $seller->id,
            'category' => 'sengketa',
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $admin->id,
            'category' => 'operasional',
        ]);
    }

    public function test_admin_can_resolve_dispute_to_completed(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');
        $admin = $this->makeUser('penjual', true);

        $ikan = $this->makeLot($seller, ['status' => 'terbayar']);

        $transaksi = $this->makeTransaksi($ikan, $buyer, [
            'status' => 'proses',
            'fulfillment_state' => 'DISENGKETAKAN',
            'pickup_status' => 'pickup_arrived',
            'pickup_verified_at' => now()->subDay(),
        ]);

        $dispute = TransactionDispute::create([
            'transaksi_id' => $transaksi->id,
            'ikan_id' => $ikan->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'open',
            'complaint_reason' => 'penjemput_belum_datang',
            'complaint_detail' => 'Belum ada update penjemputan.',
            'opened_by_type' => 'buyer',
            'opened_by_id' => $buyer->id,
            'opened_at' => now()->subHours(5),
        ]);

        app(TransaksiFulfillmentService::class)->resolveOpenDisputeByAdmin(
            $transaksi,
            (int) $admin->id,
            'completed',
            'Bukti penjemputan valid, transaksi diselesaikan.'
        );

        $this->assertDatabaseHas('transaksis', [
            'id' => $transaksi->id,
            'fulfillment_state' => 'SELESAI',
            'status' => 'lunas',
        ]);

        $this->assertDatabaseHas('transaction_disputes', [
            'id' => $dispute->id,
            'status' => 'resolved_completed',
            'resolved_by_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('transaction_state_logs', [
            'transaksi_id' => $transaksi->id,
            'to_state' => 'SELESAI',
            'actor_type' => 'admin',
            'reason_code' => 'DISPUTE_RESOLVED_COMPLETED',
        ]);
    }

    private function makeUser(string $role, bool $isAdmin = false): User
    {
        return User::factory()->create(array_filter([
            'email' => $isAdmin ? 'sabiqmaftu@gmail.com' : null,
            'role' => $role,
            'is_admin' => $isAdmin,
        ], fn ($value) => $value !== null));
    }

    private function makeLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Cakalang Uji Dispute',
            'berat' => 15,
            'estimasi_jumlah_ekor' => 25,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot test dispute',
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
            'status' => 'selesai',
            'state_version' => 1,
        ], $overrides));
    }

    private function makeTransaksi(Ikan $ikan, User $buyer, array $overrides = []): Transaksi
    {
        return Transaksi::create(array_merge([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 160_000,
            'status' => 'lunas',
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
