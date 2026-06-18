<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Transaksi;
use App\Services\PembayaranService;

class SimulateMidtransWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'midtrans:webhook:simulate
        {transaksi : ID of the Transaksi record}
        {--status=settlement : Midtrans transaction_status (capture, settlement, pending, deny, cancel, expire)}
        {--order-id= : Optional order_id to use (will be set on transaksi if empty)}
        {--amount= : Optional gross_amount override}
        {--payment-type=bank_transfer : payment_type value}
        {--status-code=200 : status_code used in signature}
        {--target= : Target webhook URL (defaults to APP_URL/midtrans/webhook)}
        {--no-http : Do not send HTTP request; process locally}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a Midtrans webhook notification for a Transaksi (local testing)';

    public function handle(): int
    {
        $transaksiId = $this->argument('transaksi');

        $transaksi = Transaksi::find($transaksiId);
        if (! $transaksi) {
            $this->error("Transaksi with id {$transaksiId} not found.");
            return self::FAILURE;
        }

        $orderId = $this->option('order-id') ?: ($transaksi->midtrans_order_id ?: 'BORGFISH-' . $transaksi->id . '-SIM' . time());

        if (! $transaksi->midtrans_order_id || $this->option('order-id')) {
            $transaksi->midtrans_order_id = $orderId;
            $transaksi->save();
            $this->info("Set transaksi->midtrans_order_id = {$orderId}");
        }

        $grossAmount = $this->option('amount') !== null
            ? $this->option('amount')
            : (int) round($transaksi->totalTagihan());

        $status = $this->option('status') ?: 'settlement';
        $statusCode = (string) $this->option('status-code');
        $paymentType = $this->option('payment-type') ?: 'bank_transfer';

        $serverKey = trim((string) config('midtrans.server_key'));
        if ($serverKey === '') {
            $this->error('MIDTRANS server key not configured. Set MIDTRANS_SERVER_KEY in .env.');
            return self::FAILURE;
        }

        $signatureKey = strtolower(hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey));

        $payload = [
            'order_id' => $orderId,
            'transaction_status' => $status,
            'status_code' => $statusCode,
            'gross_amount' => (string) $grossAmount,
            'payment_type' => $paymentType,
            'signature_key' => $signatureKey,
        ];

        $this->info('Prepared payload: ' . json_encode($payload));

        if (! $this->option('no-http')) {
            $target = $this->option('target') ?: rtrim(config('app.url') ?: 'http://127.0.0.1:8000', '/') . '/midtrans/webhook';
            $this->info("Posting to {$target} ...");

            try {
                $res = Http::timeout(15)->post($target, $payload);

                if ($res->successful()) {
                    $this->info('Webhook posted successfully. Response: ' . $res->body());
                    return self::SUCCESS;
                }

                $this->error('HTTP POST failed: ' . $res->status() . ' ' . $res->body());
            } catch (\Throwable $e) {
                $this->error('HTTP request error: ' . $e->getMessage());
            }
        }

        $this->info('Falling back to local processing via PembayaranService::handleWebhook');

        try {
            app(PembayaranService::class)->handleWebhook($payload);
            $this->info('Processed webhook locally successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Local processing failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
