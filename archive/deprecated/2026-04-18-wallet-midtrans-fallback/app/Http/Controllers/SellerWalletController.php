<?php

namespace App\Http\Controllers;

use App\Models\SellerWalletLedger;
use App\Models\SellerWithdrawal;
use App\Models\Transaksi;
use Illuminate\Http\Request;

class SellerWalletController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user();

        abort_unless($seller && $seller->isPenjual(), 403);

        $escrowHeldAmount = (float) Transaksi::query()
            ->whereHas('ikan', fn ($query) => $query->where('user_id', (int) $seller->id))
            ->where('status', 'lunas')
            ->where('escrow_status', 'ditahan')
            ->sum('escrow_amount');

        $ledgers = SellerWalletLedger::query()
            ->where('user_id', (int) $seller->id)
            ->latest('id')
            ->paginate(15, ['*'], 'ledgerPage')
            ->withQueryString();

        $withdrawals = SellerWithdrawal::query()
            ->where('user_id', (int) $seller->id)
            ->latest('id')
            ->paginate(10, ['*'], 'withdrawalPage')
            ->withQueryString();

        return view('penjual.saldo.index', [
            'seller' => $seller,
            'escrowHeldAmount' => $escrowHeldAmount,
            'ledgers' => $ledgers,
            'withdrawals' => $withdrawals,
            'highlightWithdrawalId' => (int) $request->query('withdrawal', 0),
        ]);
    }
}
