<?php

namespace App\Services;

use App\Models\SellerWalletLedger;
use App\Models\SellerWithdrawal;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\Withdrawal as ExternalWithdrawal;
use App\Models\Wallet as GenericWallet;
use App\Jobs\ProcessPayoutJob;
use Illuminate\Support\Str;
use DomainException;
use Illuminate\Support\Facades\DB;

class SellerWalletService
{
    public function __construct(
        private readonly NotificationOutboxService $notifications,
    ) {
    }

    public function creditReleasedEscrow(Transaksi $transaksi): void
    {
        DB::transaction(function () use ($transaksi): void {
            if (! $transaksi->exists || $transaksi->escrow_status !== 'dilepas') {
                return;
            }

            $transaksi->loadMissing('ikan');

            $sellerId = (int) ($transaksi->ikan?->user_id ?? 0);
            $amount = round((float) ($transaksi->escrow_amount ?? $transaksi->harga_final ?? 0), 2);

            if ($sellerId <= 0 || $amount <= 0) {
                return;
            }

            $seller = User::query()
                ->whereKey($sellerId)
                ->lockForUpdate()
                ->first();

            if (! $seller) {
                return;
            }

            $existing = SellerWalletLedger::query()
                ->where('user_id', $sellerId)
                ->where('entry_type', 'escrow_release_credit')
                ->where('reference_type', 'transaksis')
                ->where('reference_id', (int) $transaksi->id)
                ->first();

            if ($existing) {
                return;
            }

            $lotName = trim((string) ($transaksi->ikan?->nama_ikan ?? 'lot #' . $transaksi->ikan_id));

            $this->applyLedger(
                $seller,
                $amount,
                0,
                'escrow_release_credit',
                'transaksis',
                (int) $transaksi->id,
                'Escrow lot ' . $lotName . ' sudah lepas dan masuk ke saldo penjual.'
            );

            $this->notifications->queue(
                $sellerId,
                'saldo',
                'Dana seller masuk saldo',
                'Escrow ' . formatRupiah($amount) . ' dari lot ' . $lotName . ' sudah masuk ke saldo penjual dan siap Anda tarik.',
                [
                    'event' => 'seller_wallet_credited',
                    'transaksi_id' => (int) $transaksi->id,
                    'ikan_id' => (int) ($transaksi->ikan_id ?? 0),
                    'amount' => $amount,
                ],
                $this->buildKey('credit', (int) $transaksi->id, $sellerId)
            );
        }, 3);
    }

    public function createWithdrawalRequest(
        int $sellerId,
        float $amount,
        string $bankName,
        string $accountNumber,
        string $accountHolderName,
        ?string $sellerNote = null
    ): SellerWithdrawal {
        return DB::transaction(function () use ($sellerId, $amount, $bankName, $accountNumber, $accountHolderName, $sellerNote): SellerWithdrawal {
            $seller = User::query()
                ->whereKey($sellerId)
                ->lockForUpdate()
                ->first();

            if (! $seller || ! $seller->isPenjual()) {
                throw new DomainException('Akun penjual tidak valid.');
            }

            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw new DomainException('Nominal pencairan harus lebih besar dari nol.');
            }

            if ($seller->sellerSaldoTersedia() + 0.00001 < $amount) {
                throw new DomainException('Saldo penjual tersedia tidak cukup untuk request withdraw ini.');
            }

            $withdrawal = SellerWithdrawal::create([
                'user_id' => $sellerId,
                'amount' => $amount,
                'status' => 'pending',
                'bank_name' => trim($bankName),
                'account_number' => trim($accountNumber),
                'account_holder_name' => trim($accountHolderName),
                'seller_note' => $sellerNote ? trim($sellerNote) : null,
                'requested_at' => now(),
            ]);

            $this->applyLedger(
                $seller,
                -$amount,
                $amount,
                'withdraw_request_locked',
                'seller_withdrawals',
                (int) $withdrawal->id,
                'Dana dipindahkan ke antrean pencairan seller.'
            );

            $this->notifications->queue(
                $sellerId,
                'saldo',
                'Permintaan pencairan dikirim',
                'Request withdraw sebesar ' . formatRupiah($amount) . ' sudah masuk ke antrean review admin.',
                [
                    'event' => 'seller_withdrawal_requested',
                    'withdrawal_id' => (int) $withdrawal->id,
                    'amount' => $amount,
                    'status' => 'pending',
                ],
                $this->buildKey('requested', (int) $withdrawal->id, $sellerId)
            );

            $this->queueAdminWithdrawalBroadcast(
                $withdrawal,
                'Permintaan withdraw seller baru',
                'Penjual ' . $seller->name . ' meminta pencairan ' . formatRupiah($amount) . '.'
            );

            return $withdrawal->fresh(['user']);
        }, 3);
    }

    public function approveWithdrawal(SellerWithdrawal $withdrawal, int $adminId, ?string $reviewNote = null): SellerWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $adminId, $reviewNote): SellerWithdrawal {
            $locked = SellerWithdrawal::query()
                ->with('user')
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new DomainException('Request withdraw ini tidak lagi menunggu persetujuan.');
            }

            $locked->status = 'approved';
            $locked->reviewed_by_id = $adminId;
            $locked->reviewed_at = $locked->reviewed_at ?? now();
            $locked->approved_at = now();
            $locked->review_note = $reviewNote ? trim($reviewNote) : null;
            $locked->save();

            $this->notifications->queue(
                (int) $locked->user_id,
                'saldo',
                'Withdraw disetujui admin',
                'Request pencairan ' . formatRupiah($locked->amount) . ' sudah disetujui admin dan masuk tahap transfer.',
                [
                    'event' => 'seller_withdrawal_approved',
                    'withdrawal_id' => (int) $locked->id,
                    'amount' => (float) $locked->amount,
                    'status' => 'approved',
                ],
                $this->buildKey('approved', (int) $locked->id, (int) $locked->user_id)
            );

            // If REAL mode, create a generic withdrawal record and dispatch payout job
            if (config('wallet.mode') === 'REAL') {
                $wallet = GenericWallet::firstOrCreate(['user_id' => (int) $locked->user_id], ['currency' => 'IDR']);

                $idempotency = (string) Str::uuid();

                $external = ExternalWithdrawal::create([
                    'user_id' => (int) $locked->user_id,
                    'wallet_id' => $wallet->id,
                    'amount' => (float) $locked->amount,
                    'fee' => 0,
                    'net_amount' => (float) $locked->amount,
                    'status' => 'APPROVED',
                    'requested_at' => now(),
                    'idempotency_key' => $idempotency,
                    'meta' => [
                        'seller_withdrawal_id' => (int) $locked->id,
                        'beneficiary' => [
                            'bank_name' => $locked->bank_name,
                            'account_number' => $locked->account_number,
                            'account_holder_name' => $locked->account_holder_name,
                        ],
                    ],
                ]);

                AuditService::log('system', null, 'withdraw.external_created', 'withdrawal', $external->id, ['seller_withdrawal_id' => $locked->id]);

                // dispatch payout job
                ProcessPayoutJob::dispatch($external->id);
            }

            return $locked->fresh(['user', 'reviewedBy']);
        }, 3);
    }

    public function markWithdrawalPaid(
        SellerWithdrawal $withdrawal,
        int $adminId,
        ?string $transferReference = null,
        ?string $reviewNote = null
    ): SellerWithdrawal {
        return DB::transaction(function () use ($withdrawal, $adminId, $transferReference, $reviewNote): SellerWithdrawal {
            $locked = SellerWithdrawal::query()
                ->with('user')
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isApproved()) {
                throw new DomainException('Withdraw harus disetujui admin sebelum ditandai sudah dibayar.');
            }

            $seller = User::query()
                ->whereKey((int) $locked->user_id)
                ->lockForUpdate()
                ->first();

            if (! $seller) {
                throw new DomainException('Akun penjual tidak ditemukan.');
            }

            $amount = round((float) $locked->amount, 2);
            if ($seller->sellerSaldoPendingWithdrawal() + 0.00001 < $amount) {
                throw new DomainException('Saldo seller pending withdraw tidak mencukupi untuk payout ini.');
            }

            $this->applyLedger(
                $seller,
                0,
                -$amount,
                'withdraw_paid',
                'seller_withdrawals',
                (int) $locked->id,
                'Pencairan seller ditandai selesai dibayar admin.'
            );

            $locked->status = 'paid';
            $locked->paid_by_id = $adminId;
            $locked->paid_at = now();
            $locked->review_note = $reviewNote ? trim($reviewNote) : ($locked->review_note ?: null);
            $locked->transfer_reference = $transferReference ? trim($transferReference) : null;
            $locked->save();

            $this->notifications->queue(
                (int) $locked->user_id,
                'saldo',
                'Withdraw sudah dibayar',
                'Pencairan ' . formatRupiah($amount) . ' sudah diproses admin dan ditandai selesai dibayar.',
                [
                    'event' => 'seller_withdrawal_paid',
                    'withdrawal_id' => (int) $locked->id,
                    'amount' => $amount,
                    'status' => 'paid',
                ],
                $this->buildKey('paid', (int) $locked->id, (int) $locked->user_id)
            );

            return $locked->fresh(['user', 'reviewedBy', 'paidBy']);
        }, 3);
    }

    public function rejectWithdrawal(SellerWithdrawal $withdrawal, int $adminId, ?string $reviewNote = null): SellerWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $adminId, $reviewNote): SellerWithdrawal {
            $locked = SellerWithdrawal::query()
                ->with('user')
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array((string) $locked->status, ['pending', 'approved'], true)) {
                throw new DomainException('Request withdraw ini sudah final dan tidak bisa ditolak.');
            }

            $seller = User::query()
                ->whereKey((int) $locked->user_id)
                ->lockForUpdate()
                ->first();

            if (! $seller) {
                throw new DomainException('Akun penjual tidak ditemukan.');
            }

            $amount = round((float) $locked->amount, 2);
            if ($seller->sellerSaldoPendingWithdrawal() + 0.00001 < $amount) {
                throw new DomainException('Saldo seller pending withdraw tidak mencukupi untuk membatalkan request ini.');
            }

            $this->applyLedger(
                $seller,
                $amount,
                -$amount,
                'withdraw_rejected',
                'seller_withdrawals',
                (int) $locked->id,
                'Request pencairan ditolak admin dan dana dikembalikan ke saldo penjual.'
            );

            $locked->status = 'rejected';
            $locked->reviewed_by_id = $adminId;
            $locked->reviewed_at = now();
            $locked->rejected_at = now();
            $locked->review_note = $reviewNote ? trim($reviewNote) : null;
            $locked->save();

            $this->notifications->queue(
                (int) $locked->user_id,
                'saldo',
                'Withdraw ditolak admin',
                'Request pencairan ' . formatRupiah($amount) . ' ditolak admin. Dana dikembalikan ke saldo penjual.',
                [
                    'event' => 'seller_withdrawal_rejected',
                    'withdrawal_id' => (int) $locked->id,
                    'amount' => $amount,
                    'status' => 'rejected',
                ],
                $this->buildKey('rejected', (int) $locked->id, (int) $locked->user_id)
            );

            return $locked->fresh(['user', 'reviewedBy']);
        }, 3);
    }

    /**
     * Mark a seller withdrawal as paid triggered by external payout (webhook).
     * This is used when the payment gateway notifies that the payout was successful.
     */
    public function markWithdrawalPaidByPayout(SellerWithdrawal $withdrawal, ?string $transferReference = null, ?string $reviewNote = null): SellerWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $transferReference, $reviewNote): SellerWithdrawal {
            $locked = SellerWithdrawal::query()
                ->with('user')
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isApproved()) {
                throw new DomainException('Withdraw harus disetujui sebelum ditandai sudah dibayar.');
            }

            $seller = User::query()
                ->whereKey((int) $locked->user_id)
                ->lockForUpdate()
                ->first();

            if (! $seller) {
                throw new DomainException('Akun penjual tidak ditemukan.');
            }

            $amount = round((float) $locked->amount, 2);
            if ($seller->sellerSaldoPendingWithdrawal() + 0.00001 < $amount) {
                throw new DomainException('Saldo seller pending withdraw tidak mencukupi untuk payout ini.');
            }

            $this->applyLedger(
                $seller,
                0,
                -$amount,
                'withdraw_paid',
                'seller_withdrawals',
                (int) $locked->id,
                'Pencairan seller otomatis ditandai selesai oleh sistem.'
            );

            $locked->status = 'paid';
            $locked->paid_by_id = null;
            $locked->paid_at = now();
            $locked->review_note = $reviewNote ? trim($reviewNote) : ($locked->review_note ?: null);
            $locked->transfer_reference = $transferReference ? trim($transferReference) : $locked->transfer_reference;
            $locked->save();

            $this->notifications->queue(
                (int) $locked->user_id,
                'saldo',
                'Withdraw sudah dibayar',
                'Pencairan ' . formatRupiah($amount) . ' sudah diproses dan ditandai selesai.',
                [
                    'event' => 'seller_withdrawal_paid',
                    'withdrawal_id' => (int) $locked->id,
                    'amount' => $amount,
                    'status' => 'paid',
                ],
                $this->buildKey('paid', (int) $locked->id, (int) $locked->user_id)
            );

            return $locked->fresh(['user', 'reviewedBy', 'paidBy']);
        }, 3);
    }

    /**
     * Reject a seller withdrawal due to payout failure. Called by webhook/reconciliation.
     */
    public function rejectWithdrawalByPayout(SellerWithdrawal $withdrawal, ?string $reviewNote = null): SellerWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $reviewNote): SellerWithdrawal {
            $locked = SellerWithdrawal::query()
                ->with('user')
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array((string) $locked->status, ['pending', 'approved'], true)) {
                throw new DomainException('Request withdraw ini sudah final dan tidak bisa ditolak.');
            }

            $seller = User::query()
                ->whereKey((int) $locked->user_id)
                ->lockForUpdate()
                ->first();

            if (! $seller) {
                throw new DomainException('Akun penjual tidak ditemukan.');
            }

            $amount = round((float) $locked->amount, 2);
            if ($seller->sellerSaldoPendingWithdrawal() + 0.00001 < $amount) {
                throw new DomainException('Saldo seller pending withdraw tidak mencukupi untuk membatalkan request ini.');
            }

            $this->applyLedger(
                $seller,
                $amount,
                -$amount,
                'withdraw_rejected',
                'seller_withdrawals',
                (int) $locked->id,
                'Request pencairan ditolak oleh sistem dan dana dikembalikan ke saldo penjual.'
            );

            $locked->status = 'rejected';
            $locked->reviewed_by_id = null;
            $locked->reviewed_at = now();
            $locked->rejected_at = now();
            $locked->review_note = $reviewNote ? trim($reviewNote) : null;
            $locked->save();

            $this->notifications->queue(
                (int) $locked->user_id,
                'saldo',
                'Withdraw ditolak',
                'Pencairan ' . formatRupiah($amount) . ' gagal diproses dan dikembalikan ke saldo Anda.',
                [
                    'event' => 'seller_withdrawal_rejected',
                    'withdrawal_id' => (int) $locked->id,
                    'amount' => $amount,
                    'status' => 'rejected',
                ],
                $this->buildKey('rejected', (int) $locked->id, (int) $locked->user_id)
            );

            return $locked->fresh(['user', 'reviewedBy']);
        }, 3);
    }

    private function applyLedger(
        User $seller,
        float $availableDelta,
        float $pendingDelta,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null
    ): SellerWalletLedger {
        $seller->seller_saldo = round($seller->sellerSaldoTersedia() + $availableDelta, 2);
        $seller->seller_saldo_pending_withdrawal = round($seller->sellerSaldoPendingWithdrawal() + $pendingDelta, 2);

        if ($seller->seller_saldo < -0.00001 || $seller->seller_saldo_pending_withdrawal < -0.00001) {
            throw new DomainException('Mutasi dana seller menghasilkan nilai negatif yang tidak valid.');
        }

        $seller->seller_saldo = max(0, (float) $seller->seller_saldo);
        $seller->seller_saldo_pending_withdrawal = max(0, (float) $seller->seller_saldo_pending_withdrawal);
        $seller->save();

        return SellerWalletLedger::create([
            'user_id' => (int) $seller->id,
            'entry_type' => $entryType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'available_delta' => round($availableDelta, 2),
            'pending_delta' => round($pendingDelta, 2),
            'balance_after' => round($seller->sellerSaldoTersedia(), 2),
            'pending_after' => round($seller->sellerSaldoPendingWithdrawal(), 2),
            'note' => $note,
        ]);
    }

    private function queueAdminWithdrawalBroadcast(SellerWithdrawal $withdrawal, string $title, string $message): void
    {
        User::query()
            ->where('is_admin', true)
            ->whereIn('role', ['penjual', 'pembeli', 'superadmin'])
            ->select(['id'])
            ->each(function (User $admin) use ($withdrawal, $title, $message): void {
                $this->notifications->queue(
                    (int) $admin->id,
                    'saldo',
                    $title,
                    $message,
                    [
                        'event' => 'seller_withdrawal_requested_for_admin',
                        'withdrawal_id' => (int) $withdrawal->id,
                        'seller_id' => (int) $withdrawal->user_id,
                        'amount' => (float) $withdrawal->amount,
                        'status' => (string) $withdrawal->status,
                    ],
                    $this->buildKey('admin-pending', (int) $withdrawal->id, (int) $admin->id)
                );
            });
    }

    private function buildKey(string $event, int $referenceId, int $recipientId): string
    {
        return implode(':', [
            'seller-wallet',
            $event,
            $referenceId,
            $recipientId,
        ]);
    }
}
