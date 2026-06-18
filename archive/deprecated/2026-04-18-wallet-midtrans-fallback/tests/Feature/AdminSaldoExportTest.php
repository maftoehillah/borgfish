<?php

namespace Tests\Feature;

use App\Models\SaldoTopup;
use App\Models\SellerWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSaldoExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_saldo_topups_csv(): void
    {
        $admin = $this->makeAdmin();
        $buyer = $this->makeBuyer();

        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 125_000,
            'status' => 'success',
            'payment_method' => 'qris',
            'midtrans_order_id' => 'BORGFISH-TOPUP-CSV-001',
            'requested_at' => now()->subMinutes(20),
            'paid_at' => now()->subMinutes(10),
        ]);

        $buyer->saldoLedgers()->create([
            'entry_type' => 'topup',
            'reference_type' => 'saldo_topups',
            'reference_id' => $topup->id,
            'available_delta' => 125_000,
            'held_delta' => 0,
            'balance_after' => 125_000,
            'held_after' => 0,
            'note' => 'Top up saldo berhasil dikonfirmasi via Midtrans.',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.exports.saldo-topups', [
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');

        $content = $response->streamedContent();

        $this->assertStringContainsString('topup_id,requested_at,paid_at,buyer_id,buyer_name,buyer_email,amount,status,payment_method,midtrans_order_id,ledger_posted,ledger_entry_count', $content);
        $this->assertStringContainsString((string) $topup->id, $content);
        $this->assertStringContainsString('BORGFISH-TOPUP-CSV-001', $content);
        $this->assertStringContainsString('yes,1', $content);
    }

    public function test_admin_can_download_saldo_ledgers_csv(): void
    {
        $admin = $this->makeAdmin();
        $buyer = $this->makeBuyer();

        $buyer->saldoLedgers()->create([
            'entry_type' => 'topup',
            'reference_type' => 'saldo_topups',
            'reference_id' => 44,
            'available_delta' => 90_000,
            'held_delta' => 0,
            'balance_after' => 90_000,
            'held_after' => 0,
            'note' => 'Top up saldo direkonsiliasi manual oleh admin.',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.exports.saldo-ledgers', [
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
            'entry_scope' => 'topup_only',
        ]));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('ledger_id,created_at,user_id,user_name,user_email,entry_type,reference_type,reference_id,available_delta,held_delta,balance_after,held_after,note', $content);
        $this->assertStringContainsString('topup', $content);
        $this->assertStringContainsString('saldo_topups', $content);
        $this->assertStringContainsString('Top up saldo direkonsiliasi manual oleh admin.', $content);
    }

    public function test_admin_can_download_seller_withdrawals_csv(): void
    {
        $admin = $this->makeAdmin();
        $seller = $this->makeSeller();

        $withdrawal = SellerWithdrawal::create([
            'user_id' => $seller->id,
            'amount' => 215_000,
            'status' => 'paid',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => $seller->name,
            'requested_at' => now()->subMinutes(40),
            'approved_at' => now()->subMinutes(20),
            'paid_at' => now()->subMinutes(10),
            'transfer_reference' => 'TRF-CSV-001',
            'review_note' => 'Payout sudah diproses.',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.exports.seller-withdrawals', [
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
            'status' => 'paid',
        ]));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('withdrawal_id,requested_at,approved_at,paid_at,seller_id,seller_name,seller_email,amount,status,bank_name,account_number,account_holder_name,transfer_reference,review_note', $content);
        $this->assertStringContainsString((string) $withdrawal->id, $content);
        $this->assertStringContainsString('TRF-CSV-001', $content);
        $this->assertStringContainsString('paid', $content);
    }

    public function test_admin_can_download_seller_wallet_ledgers_csv(): void
    {
        $admin = $this->makeAdmin();
        $seller = $this->makeSeller();

        $seller->sellerWalletLedgers()->create([
            'entry_type' => 'escrow_release_credit',
            'reference_type' => 'transaksis',
            'reference_id' => 88,
            'available_delta' => 320_000,
            'pending_delta' => 0,
            'balance_after' => 320_000,
            'pending_after' => 0,
            'note' => 'Escrow lot Kakap masuk ke saldo penjual.',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.exports.seller-ledgers', [
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
            'entry_scope' => 'escrow_only',
        ]));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('ledger_id,created_at,seller_id,seller_name,seller_email,entry_type,reference_type,reference_id,available_delta,pending_delta,balance_after,pending_after,note', $content);
        $this->assertStringContainsString('escrow_release_credit', $content);
        $this->assertStringContainsString('Escrow lot Kakap masuk ke saldo penjual.', $content);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => 'penjual',
            'is_admin' => true,
        ]);
    }

    private function makeBuyer(): User
    {
        return User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);
    }

    private function makeSeller(): User
    {
        return User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
    }
}
