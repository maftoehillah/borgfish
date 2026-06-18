<?php

namespace App\Http\Controllers;

use App\Models\SaldoLedger;
use App\Models\SaldoTopup;
use App\Models\SellerWalletLedger;
use App\Models\SellerWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSaldoExportController extends Controller
{
    public function topupsCsv(Request $request): StreamedResponse
    {
        $this->ensureAdminAccess();

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $status = trim((string) $request->query('status', ''));
        $needsReconciliation = filter_var($request->query('needs_reconciliation', false), FILTER_VALIDATE_BOOL);

        $query = SaldoTopup::query()
            ->with(['user'])
            ->withCount('ledgerEntries')
            ->whereBetween('requested_at', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay(),
            ])
            ->orderBy('requested_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($needsReconciliation) {
            $query->where('status', 'success')
                ->whereDoesntHave('ledgerEntries');
        }

        $rows = $query->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fprintf($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'topup_id',
                'requested_at',
                'paid_at',
                'buyer_id',
                'buyer_name',
                'buyer_email',
                'amount',
                'status',
                'payment_method',
                'midtrans_order_id',
                'ledger_posted',
                'ledger_entry_count',
            ]);

            foreach ($rows as $topup) {
                fputcsv($handle, [
                    (int) $topup->id,
                    optional($topup->requested_at)->format('Y-m-d H:i:s'),
                    optional($topup->paid_at)->format('Y-m-d H:i:s'),
                    (int) $topup->user_id,
                    (string) ($topup->user?->name ?? ''),
                    (string) ($topup->user?->email ?? ''),
                    number_format((float) $topup->amount, 2, '.', ''),
                    (string) $topup->status,
                    (string) ($topup->payment_method ?? ''),
                    (string) ($topup->midtrans_order_id ?? ''),
                    (int) ($topup->ledger_entries_count ?? 0) > 0 ? 'yes' : 'no',
                    (int) ($topup->ledger_entries_count ?? 0),
                ]);
            }

            fclose($handle);
        }, $this->buildFilename('saldo-topups', $fromDate->format('Ymd'), $toDate->format('Ymd')));
    }

    public function ledgersCsv(Request $request): StreamedResponse
    {
        $this->ensureAdminAccess();

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $entryScope = trim((string) $request->query('entry_scope', 'topup_only'));

        $query = SaldoLedger::query()
            ->with('user')
            ->whereBetween('created_at', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay(),
            ])
            ->orderBy('created_at');

        if ($entryScope === 'topup_only') {
            $query->where('entry_type', 'topup');
        }

        $rows = $query->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fprintf($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ledger_id',
                'created_at',
                'user_id',
                'user_name',
                'user_email',
                'entry_type',
                'reference_type',
                'reference_id',
                'available_delta',
                'held_delta',
                'balance_after',
                'held_after',
                'note',
            ]);

            foreach ($rows as $ledger) {
                fputcsv($handle, [
                    (int) $ledger->id,
                    optional($ledger->created_at)->format('Y-m-d H:i:s'),
                    (int) $ledger->user_id,
                    (string) ($ledger->user?->name ?? ''),
                    (string) ($ledger->user?->email ?? ''),
                    (string) $ledger->entry_type,
                    (string) ($ledger->reference_type ?? ''),
                    $ledger->reference_id !== null ? (int) $ledger->reference_id : '',
                    number_format((float) $ledger->available_delta, 2, '.', ''),
                    number_format((float) $ledger->held_delta, 2, '.', ''),
                    number_format((float) $ledger->balance_after, 2, '.', ''),
                    number_format((float) $ledger->held_after, 2, '.', ''),
                    (string) ($ledger->note ?? ''),
                ]);
            }

            fclose($handle);
        }, $this->buildFilename('saldo-ledgers', $fromDate->format('Ymd'), $toDate->format('Ymd')));
    }

    public function sellerWithdrawalsCsv(Request $request): StreamedResponse
    {
        $this->ensureAdminAccess();

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $status = trim((string) $request->query('status', ''));

        $query = SellerWithdrawal::query()
            ->with('user')
            ->whereBetween('requested_at', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay(),
            ])
            ->orderBy('requested_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fprintf($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'withdrawal_id',
                'requested_at',
                'approved_at',
                'paid_at',
                'seller_id',
                'seller_name',
                'seller_email',
                'amount',
                'status',
                'bank_name',
                'account_number',
                'account_holder_name',
                'transfer_reference',
                'review_note',
            ]);

            foreach ($rows as $withdrawal) {
                fputcsv($handle, [
                    (int) $withdrawal->id,
                    optional($withdrawal->requested_at)->format('Y-m-d H:i:s'),
                    optional($withdrawal->approved_at)->format('Y-m-d H:i:s'),
                    optional($withdrawal->paid_at)->format('Y-m-d H:i:s'),
                    (int) $withdrawal->user_id,
                    (string) ($withdrawal->user?->name ?? ''),
                    (string) ($withdrawal->user?->email ?? ''),
                    number_format((float) $withdrawal->amount, 2, '.', ''),
                    (string) $withdrawal->status,
                    (string) $withdrawal->bank_name,
                    (string) $withdrawal->account_number,
                    (string) $withdrawal->account_holder_name,
                    (string) ($withdrawal->transfer_reference ?? ''),
                    (string) ($withdrawal->review_note ?? ''),
                ]);
            }

            fclose($handle);
        }, $this->buildFilename('seller-withdrawals', $fromDate->format('Ymd'), $toDate->format('Ymd')));
    }

    public function sellerLedgersCsv(Request $request): StreamedResponse
    {
        $this->ensureAdminAccess();

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $entryScope = trim((string) $request->query('entry_scope', 'all'));

        $query = SellerWalletLedger::query()
            ->with('user')
            ->whereBetween('created_at', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay(),
            ])
            ->orderBy('created_at');

        if ($entryScope === 'escrow_only') {
            $query->where('entry_type', 'escrow_release_credit');
        } elseif ($entryScope === 'withdraw_only') {
            $query->whereIn('entry_type', [
                'withdraw_request_locked',
                'withdraw_paid',
                'withdraw_rejected',
            ]);
        }

        $rows = $query->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fprintf($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ledger_id',
                'created_at',
                'seller_id',
                'seller_name',
                'seller_email',
                'entry_type',
                'reference_type',
                'reference_id',
                'available_delta',
                'pending_delta',
                'balance_after',
                'pending_after',
                'note',
            ]);

            foreach ($rows as $ledger) {
                fputcsv($handle, [
                    (int) $ledger->id,
                    optional($ledger->created_at)->format('Y-m-d H:i:s'),
                    (int) $ledger->user_id,
                    (string) ($ledger->user?->name ?? ''),
                    (string) ($ledger->user?->email ?? ''),
                    (string) $ledger->entry_type,
                    (string) ($ledger->reference_type ?? ''),
                    $ledger->reference_id !== null ? (int) $ledger->reference_id : '',
                    number_format((float) $ledger->available_delta, 2, '.', ''),
                    number_format((float) $ledger->pending_delta, 2, '.', ''),
                    number_format((float) $ledger->balance_after, 2, '.', ''),
                    number_format((float) $ledger->pending_after, 2, '.', ''),
                    (string) ($ledger->note ?? ''),
                ]);
            }

            fclose($handle);
        }, $this->buildFilename('seller-wallet-ledgers', $fromDate->format('Ymd'), $toDate->format('Ymd')));
    }

    private function ensureAdminAccess(): void
    {
        $admin = auth('admin')->user();

        if (! $admin || ! $admin->isPanelAdmin()) {
            abort(403);
        }
    }

    private function resolveDateRange(Request $request): array
    {
        $fromInput = $request->query('from_date');
        $toInput = $request->query('to_date');

        $fromDate = $fromInput ? Carbon::parse((string) $fromInput) : today();
        $toDate = $toInput ? Carbon::parse((string) $toInput) : $fromDate->copy();

        if ($toDate->lt($fromDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function buildFilename(string $prefix, string $fromDate, string $toDate): string
    {
        return $prefix . '-' . $fromDate . '-to-' . $toDate . '.csv';
    }
}
