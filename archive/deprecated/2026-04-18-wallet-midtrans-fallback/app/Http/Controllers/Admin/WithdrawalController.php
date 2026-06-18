<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\WithdrawService;
use App\Models\Withdrawal;

class WithdrawalController extends Controller
{
    protected WithdrawService $service;

    public function __construct(WithdrawService $service)
    {
        $this->service = $service;
        $this->middleware('auth');
        // you may add admin middleware here
    }

    public function approve(Request $request, $id)
    {
        $adminId = Auth::id();
        $w = $this->service->approveWithdraw((int) $id, $adminId);
        return redirect()->back()->with('status', 'Withdrawal approved: ' . $w->id);
    }

    public function reject(Request $request, $id)
    {
        $w = Withdrawal::findOrFail($id);
        $w->status = 'REJECTED';
        $w->save();
        \App\Services\AuditService::log('admin', Auth::id(), 'withdraw.rejected', 'withdrawal', $w->id, []);
        return redirect()->back()->with('status', 'Withdrawal rejected');
    }
}
