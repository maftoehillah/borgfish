<?php

namespace App\Http\Controllers;

use App\Models\SaldoTopup;
use App\Services\NotificationOutboxService;
use App\Services\SaldoTopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaldoTopupController extends Controller
{
    public function create()
    {
        $user = Auth::user();

        return view('saldo.topup', [
            'user' => $user,
            'recentTopups' => $user->saldoTopups()->limit(5)->get(),
            'recentLedgers' => $user->saldoLedgers()->limit(5)->get(),
            'minimumTopupAmount' => 10_000,
        ]);
    }

    public function store(Request $request, SaldoTopupService $service)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
        ]);

        $topup = $service->createTopup(Auth::id(), $request->amount);

        return redirect()
            ->route('saldo.topup.pay', $topup->id)
            ->with('sukses', 'Permintaan top up dibuat. Lanjutkan pembayaran untuk menambah saldo.');
    }

    public function pay(SaldoTopup $topup, SaldoTopupService $service)
    {
        if ($topup->user_id !== Auth::id()) {
            abort(403);
        }

        $topup->load('user');
        $paymentSession = null;

        if ($topup->isPending()) {
            try {
                $paymentSession = $service->getPaymentSession($topup);
            } catch (\RuntimeException $e) {
                return redirect()->route('saldo.topup')
                    ->with('error', $e->getMessage());
            } catch (\Throwable $e) {
                report($e);

                return redirect()->route('saldo.topup')
                    ->with('error', 'Terjadi kendala saat menyiapkan pembayaran top up. Silakan coba lagi.');
            }
        }

        return view('saldo.topup_pay', [
            'topup' => $topup->fresh()->load('user'),
            'paymentSession' => $paymentSession,
        ]);
    }

    public function webhook(
        Request $request,
        SaldoTopupService $service,
        NotificationOutboxService $notificationOutboxService
    )
    {
        try {
            $service->handleWebhook($request->all());
            $notificationOutboxService->processPending(50);

            return response()->json(['status' => 'ok']);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Webhook top up gagal diproses.'], 500);
        }
    }
}
