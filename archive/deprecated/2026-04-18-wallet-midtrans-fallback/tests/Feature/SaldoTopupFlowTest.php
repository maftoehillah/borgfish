<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\SaldoTopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaldoTopupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('midtrans.server_key', 'SB-Mid-server-valid-12345');
        config()->set('midtrans.client_key', 'SB-Mid-client-valid-12345');
        config()->set('midtrans.is_production', false);
    }

    public function test_buyer_can_create_topup_request_and_redirect_to_payment_page(): void
    {
        $buyer = $this->makeBuyer();

        $response = $this->actingAs($buyer)->post(route('saldo.topup.store'), [
            'amount' => 100_000,
        ]);

        $topup = SaldoTopup::query()->first();

        $response->assertRedirect(route('saldo.topup.pay', $topup));
        $this->assertNotNull($topup);
        $this->assertSame((int) $buyer->id, (int) $topup->user_id);
        $this->assertSame('pending', $topup->status);
        $this->assertSame(100000.0, (float) $topup->amount);
    }

    public function test_owner_can_open_topup_payment_page_with_existing_snap_session(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 25_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 100_000,
            'status' => 'pending',
            'midtrans_order_id' => 'BORGFISH-TOPUP-PAGE-001',
            'snap_token' => 'SNAP-TOKEN-001',
            'requested_at' => now(),
            'expired_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($buyer)->get(route('saldo.topup.pay', $topup));

        $response->assertOk();
        $response->assertSee('Pembayaran top up saldo', false);
        $response->assertSee('Pilih metode dan bayar sekarang', false);
        $response->assertSee('BORGFISH-TOPUP-PAGE-001', false);
    }

    public function test_non_owner_cannot_access_topup_payment_page(): void
    {
        $owner = $this->makeBuyer();
        $otherBuyer = $this->makeBuyer();
        $topup = SaldoTopup::create([
            'user_id' => $owner->id,
            'amount' => 75_000,
            'status' => 'pending',
            'midtrans_order_id' => 'BORGFISH-TOPUP-FORBIDDEN-001',
            'snap_token' => 'SNAP-TOKEN-FORBIDDEN',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($otherBuyer)->get(route('saldo.topup.pay', $topup));

        $response->assertForbidden();
    }

    public function test_topup_webhook_settlement_credits_balance_and_creates_ledger(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 50_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 100_000,
            'status' => 'pending',
            'midtrans_order_id' => 'BORGFISH-TOPUP-WEBHOOK-001',
            'requested_at' => now(),
        ]);

        $grossAmount = number_format((float) $topup->amount, 2, '.', '');
        $payload = $this->webhookPayload(
            $topup->midtrans_order_id,
            'settlement',
            '200',
            $grossAmount,
            'bank_transfer'
        );

        $response = $this->postJson(route('saldo.topup.webhook'), $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame(150000.0, $buyer->fresh()->saldoTersedia());
        $this->assertSame(0.0, $buyer->fresh()->saldoDitahan());

        $this->assertDatabaseHas('saldo_topups', [
            'id' => $topup->id,
            'status' => 'success',
            'payment_method' => 'bank_transfer',
        ]);

        $this->assertDatabaseHas('saldo_ledgers', [
            'user_id' => $buyer->id,
            'entry_type' => 'topup',
            'reference_type' => 'saldo_topups',
            'reference_id' => $topup->id,
            'available_delta' => 100000,
            'held_delta' => 0,
            'balance_after' => 150000,
            'held_after' => 0,
        ]);
    }

    public function test_topup_success_webhook_creates_buyer_notification_and_redirects_to_topup_page(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 35_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 70_000,
            'status' => 'pending',
            'midtrans_order_id' => 'BORGFISH-TOPUP-WEBHOOK-NOTIF-001',
            'requested_at' => now(),
        ]);

        $grossAmount = number_format((float) $topup->amount, 2, '.', '');
        $payload = $this->webhookPayload(
            $topup->midtrans_order_id,
            'settlement',
            '200',
            $grossAmount,
            'qris'
        );

        $this->postJson(route('saldo.topup.webhook'), $payload)->assertOk();

        $notification = InAppNotification::query()
            ->where('user_id', $buyer->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('saldo', $notification->category);
        $this->assertSame('Top up berhasil', $notification->title);
        $this->assertSame('saldo_topup_success', data_get($notification->payload, 'event'));
        $this->assertSame($topup->id, data_get($notification->payload, 'topup_id'));

        $openResponse = $this->actingAs($buyer)
            ->get(route('notifications.open', $notification));

        $openResponse->assertRedirect(route('saldo.topup.pay', $topup));
    }

    public function test_duplicate_settlement_webhook_does_not_double_credit_balance(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 10_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 60_000,
            'status' => 'pending',
            'midtrans_order_id' => 'BORGFISH-TOPUP-WEBHOOK-002',
            'requested_at' => now(),
        ]);

        $grossAmount = number_format((float) $topup->amount, 2, '.', '');
        $payload = $this->webhookPayload(
            $topup->midtrans_order_id,
            'settlement',
            '200',
            $grossAmount,
            'qris'
        );

        $this->postJson(route('saldo.topup.webhook'), $payload)->assertOk();
        $this->postJson(route('saldo.topup.webhook'), $payload)->assertOk();

        $this->assertSame(70000.0, $buyer->fresh()->saldoTersedia());
        $this->assertDatabaseCount('saldo_ledgers', 1);
        $this->assertDatabaseHas('saldo_ledgers', [
            'user_id' => $buyer->id,
            'reference_id' => $topup->id,
            'available_delta' => 60000,
        ]);
    }

    public function test_invalid_topup_webhook_signature_is_rejected(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 20_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 80_000,
            'status' => 'pending',
            'midtrans_order_id' => 'BORGFISH-TOPUP-WEBHOOK-003',
            'requested_at' => now(),
        ]);

        $grossAmount = number_format((float) $topup->amount, 2, '.', '');
        $payload = $this->webhookPayload(
            $topup->midtrans_order_id,
            'settlement',
            '200',
            $grossAmount,
            'bank_transfer'
        );
        $payload['signature_key'] = 'invalid-signature';

        $response = $this->postJson(route('saldo.topup.webhook'), $payload);

        $response->assertStatus(422);
        $this->assertSame(20000.0, $buyer->fresh()->saldoTersedia());
        $this->assertDatabaseHas('saldo_topups', [
            'id' => $topup->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('saldo_ledgers', 0);
    }

    public function test_topup_expired_webhook_creates_buyer_notification(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 20_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 80_000,
            'status' => 'pending',
            'midtrans_order_id' => 'BORGFISH-TOPUP-WEBHOOK-EXPIRED-001',
            'requested_at' => now(),
        ]);

        $grossAmount = number_format((float) $topup->amount, 2, '.', '');
        $payload = $this->webhookPayload(
            $topup->midtrans_order_id,
            'expire',
            '407',
            $grossAmount,
            'bank_transfer'
        );

        $this->postJson(route('saldo.topup.webhook'), $payload)->assertOk();

        $this->assertDatabaseHas('saldo_topups', [
            'id' => $topup->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $buyer->id,
            'category' => 'saldo',
            'title' => 'Top up kadaluarsa',
        ]);
    }

    public function test_admin_manual_reconciliation_can_credit_success_topup_without_existing_ledger(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 15_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 85_000,
            'status' => 'success',
            'payment_method' => 'manual_admin',
            'midtrans_order_id' => 'BORGFISH-TOPUP-MANUAL-001',
            'requested_at' => now()->subMinutes(15),
            'paid_at' => now()->subMinutes(10),
        ]);

        app(\App\Services\SaldoTopupService::class)->markSucceededByAdmin(
            $topup,
            'manual_admin',
            'Top up saldo direkonsiliasi manual oleh admin.'
        );
        app(\App\Services\NotificationOutboxService::class)->processPending(50);

        $this->assertSame(100000.0, $buyer->fresh()->saldoTersedia());

        $this->assertDatabaseHas('saldo_ledgers', [
            'user_id' => $buyer->id,
            'reference_type' => 'saldo_topups',
            'reference_id' => $topup->id,
            'available_delta' => 85000,
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $buyer->id,
            'category' => 'saldo',
            'title' => 'Top up berhasil',
        ]);
    }

    public function test_ledger_page_shows_topup_mutation_for_buyer(): void
    {
        $buyer = $this->makeBuyer(['saldo' => 150_000]);
        $topup = SaldoTopup::create([
            'user_id' => $buyer->id,
            'amount' => 90_000,
            'status' => 'success',
            'midtrans_order_id' => 'BORGFISH-TOPUP-LEDGER-001',
            'payment_method' => 'bank_transfer',
            'requested_at' => now()->subMinutes(10),
            'paid_at' => now()->subMinutes(5),
        ]);

        $buyer->saldoLedgers()->create([
            'entry_type' => 'topup',
            'reference_type' => 'saldo_topups',
            'reference_id' => $topup->id,
            'available_delta' => 90_000,
            'held_delta' => 0,
            'balance_after' => 150_000,
            'held_after' => 0,
            'note' => 'Top up saldo berhasil dikonfirmasi via Midtrans.',
        ]);

        $response = $this->actingAs($buyer)->get(route('saldo.ledger'));

        $response->assertOk();
        $response->assertSee('Riwayat mutasi saldo', false);
        $response->assertSee('Top Up Saldo', false);
        $response->assertSee('Top up saldo berhasil dikonfirmasi via Midtrans.', false);
        $response->assertSee('Rp 90.000', false);
    }

    private function webhookPayload(
        string $orderId,
        string $transactionStatus,
        string $statusCode,
        string $grossAmount,
        ?string $paymentType = null
    ): array {
        $payload = [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $this->sign($orderId, $statusCode, $grossAmount),
        ];

        if ($paymentType !== null) {
            $payload['payment_type'] = $paymentType;
        }

        return $payload;
    }

    private function sign(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.(string) config('midtrans.server_key'));
    }

    private function makeBuyer(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'pembeli',
            'is_admin' => false,
        ], $overrides));
    }
}
