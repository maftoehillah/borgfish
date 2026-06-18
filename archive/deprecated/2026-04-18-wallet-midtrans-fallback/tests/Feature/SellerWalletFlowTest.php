<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\SellerWithdrawal;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\SellerWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerWalletFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_released_escrow_credits_seller_wallet_and_records_ledger(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');
        $lot = $this->makeLot($seller, 'Lot Escrow Release');

        $transaksi = $this->makeTransaction($lot, $buyer, [
            'harga_final' => 180_000,
            'escrow_amount' => 180_000,
            'escrow_status' => 'ditahan',
        ]);

        $transaksi->releaseEscrow();
        $transaksi->save();

        $seller->refresh();

        $this->assertSame(180000.0, $seller->sellerSaldoTersedia());
        $this->assertSame(0.0, $seller->sellerSaldoPendingWithdrawal());

        $this->assertDatabaseHas('seller_wallet_ledgers', [
            'user_id' => $seller->id,
            'entry_type' => 'escrow_release_credit',
            'reference_type' => 'transaksis',
            'reference_id' => $transaksi->id,
        ]);
    }

    public function test_seller_can_request_withdraw_from_wallet_page(): void
    {
        $seller = $this->makeUser('penjual', [
            'seller_saldo' => 300_000,
        ]);

        $response = $this->actingAs($seller)->post(route('penjual.saldo.withdrawals.store'), [
            'amount' => 200_000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => $seller->name,
            'seller_note' => 'Tarik dana hasil lelang minggu ini.',
        ]);

        $response->assertRedirect(route('penjual.saldo.index') . '#riwayat-payout');
        $response->assertSessionHas('sukses');

        $seller->refresh();

        $this->assertSame(100000.0, $seller->sellerSaldoTersedia());
        $this->assertSame(200000.0, $seller->sellerSaldoPendingWithdrawal());

        $withdrawal = SellerWithdrawal::query()->where('user_id', $seller->id)->first();

        $this->assertNotNull($withdrawal);
        $this->assertSame('pending', $withdrawal->status);

        $this->assertDatabaseHas('seller_wallet_ledgers', [
            'user_id' => $seller->id,
            'entry_type' => 'withdraw_request_locked',
            'reference_type' => 'seller_withdrawals',
            'reference_id' => $withdrawal->id,
        ]);
    }

    public function test_admin_can_approve_and_mark_seller_withdrawal_paid(): void
    {
        $admin = $this->makeUser('penjual', ['is_admin' => true]);
        $seller = $this->makeUser('penjual', [
            'seller_saldo' => 250_000,
        ]);

        $service = app(SellerWalletService::class);
        $withdrawal = $service->createWithdrawalRequest(
            (int) $seller->id,
            250_000,
            'BCA',
            '1234567890',
            $seller->name,
            'Request payout penuh.'
        );

        $service->approveWithdrawal($withdrawal, (int) $admin->id, 'Approved untuk transfer sore ini.');
        $service->markWithdrawalPaid($withdrawal->fresh(), (int) $admin->id, 'TRF-SELLER-001', 'Sudah ditransfer.');

        $seller->refresh();
        $withdrawal->refresh();

        $this->assertSame('paid', $withdrawal->status);
        $this->assertSame(0.0, $seller->sellerSaldoTersedia());
        $this->assertSame(0.0, $seller->sellerSaldoPendingWithdrawal());
        $this->assertSame('TRF-SELLER-001', $withdrawal->transfer_reference);

        $this->assertDatabaseHas('seller_wallet_ledgers', [
            'user_id' => $seller->id,
            'entry_type' => 'withdraw_paid',
            'reference_type' => 'seller_withdrawals',
            'reference_id' => $withdrawal->id,
        ]);
    }

    public function test_admin_reject_returns_funds_to_seller_available_balance(): void
    {
        $admin = $this->makeUser('penjual', ['is_admin' => true]);
        $seller = $this->makeUser('penjual', [
            'seller_saldo' => 175_000,
        ]);

        $service = app(SellerWalletService::class);
        $withdrawal = $service->createWithdrawalRequest(
            (int) $seller->id,
            100_000,
            'BRI',
            '9876543210',
            $seller->name,
            null
        );

        $service->rejectWithdrawal($withdrawal, (int) $admin->id, 'Nama rekening belum sesuai.');

        $seller->refresh();
        $withdrawal->refresh();

        $this->assertSame('rejected', $withdrawal->status);
        $this->assertSame(175000.0, $seller->sellerSaldoTersedia());
        $this->assertSame(0.0, $seller->sellerSaldoPendingWithdrawal());

        $this->assertDatabaseHas('seller_wallet_ledgers', [
            'user_id' => $seller->id,
            'entry_type' => 'withdraw_rejected',
            'reference_type' => 'seller_withdrawals',
            'reference_id' => $withdrawal->id,
        ]);
    }

    public function test_seller_wallet_page_displays_mutation_and_payout_history(): void
    {
        $seller = $this->makeUser('penjual', [
            'seller_saldo' => 90_000,
            'seller_saldo_pending_withdrawal' => 60_000,
        ]);

        $seller->sellerWalletLedgers()->create([
            'entry_type' => 'escrow_release_credit',
            'reference_type' => 'transaksis',
            'reference_id' => 44,
            'available_delta' => 150_000,
            'pending_delta' => 0,
            'balance_after' => 150_000,
            'pending_after' => 0,
            'note' => 'Escrow lot Tuna masuk ke saldo penjual.',
        ]);

        $seller->sellerWithdrawals()->create([
            'amount' => 60_000,
            'status' => 'approved',
            'bank_name' => 'Mandiri',
            'account_number' => '123123123',
            'account_holder_name' => $seller->name,
            'requested_at' => now()->subHour(),
            'approved_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($seller)->get(route('penjual.saldo.index'));

        $response->assertOk();
        $response->assertSeeText('Dana penjual & pencairan');
        $response->assertSee('Mutasi dana seller');
        $response->assertSee('Riwayat payout');
        $response->assertSee('Escrow Masuk Saldo');
        $response->assertSee('APPROVED');
    }

    private function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_admin' => false,
        ], $overrides));
    }

    private function makeLot(User $seller, string $name): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => $name,
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk pengujian wallet seller',
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
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addMinutes(30),
            'status' => 'terbayar',
            'auction_state' => 'DIBAYAR',
            'state_version' => 1,
        ]);
    }

    private function makeTransaction(Ikan $ikan, User $buyer, array $overrides = []): Transaksi
    {
        return Transaksi::create(array_merge([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 150_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(24),
            'dibayar_pada' => now()->subHours(2),
            'escrow_status' => 'ditahan',
            'escrow_amount' => 150_000,
            'escrow_locked_at' => now()->subHours(2),
            'delivery_status' => 'diproses',
            'delivery_cost' => 0,
            'fulfillment_state' => 'DIBAYAR',
        ], $overrides));
    }
}
