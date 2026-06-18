<?php

namespace App\Http\Controllers;

use App\Models\SaldoLedger;
use Illuminate\Support\Facades\Auth;

class SaldoLedgerController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $ledgers = SaldoLedger::where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('saldo.ledger', [
            'user' => $user,
            'ledgers' => $ledgers,
            'recentTopups' => $user->saldoTopups()->limit(5)->get(),
        ]);
    }
}
