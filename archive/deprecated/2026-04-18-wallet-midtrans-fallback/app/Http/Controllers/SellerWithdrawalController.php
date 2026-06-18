<?php

namespace App\Http\Controllers;

use App\Services\NotificationOutboxService;
use App\Services\SellerWalletService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SellerWithdrawalController extends Controller
{
    public function __construct(
        private readonly SellerWalletService $sellerWallet,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $seller = $request->user();
        abort_unless($seller && $seller->isPenjual(), 403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'bank_name' => 'required|string|max:64',
            'account_number' => ['required', 'string', 'max:64', 'regex:/^[0-9]{6,30}$/'],
            'account_holder_name' => 'required|string|max:120',
            'seller_note' => 'nullable|string|max:500',
        ], [
            'account_number.regex' => 'Nomor rekening harus berisi 6-30 digit angka.',
        ]);

        try {
            $this->sellerWallet->createWithdrawalRequest(
                (int) $seller->id,
                (float) $validated['amount'],
                (string) $validated['bank_name'],
                (string) $validated['account_number'],
                (string) $validated['account_holder_name'],
                isset($validated['seller_note']) ? (string) $validated['seller_note'] : null,
            );

            app(NotificationOutboxService::class)->processPending(100);
        } catch (DomainException $e) {
            return redirect()
                ->route('penjual.saldo.index')
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('penjual.saldo.index')
                ->withInput()
                ->with('error', 'Permintaan pencairan gagal diproses. Silakan coba lagi.');
        }

        return redirect()->to(route('penjual.saldo.index') . '#riwayat-payout')
            ->with('sukses', 'Permintaan pencairan seller berhasil dibuat dan sedang menunggu review admin.');
    }
}
