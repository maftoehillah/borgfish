<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReturnsNoStoreJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminViewModeController extends Controller
{
    use ReturnsNoStoreJson;

    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403);

        $currentMode = (string) $request->session()->get('superadmin_view_mode', 'PEMBELI');
        $nextMode = $currentMode === 'PENJUAL' ? 'PEMBELI' : 'PENJUAL';

        $request->session()->put('superadmin_view_mode', $nextMode);

        $redirect = $nextMode === 'PENJUAL'
            ? route('penjual.dashboard')
            : route('ikans.index');

        if ($request->expectsJson() || $request->ajax()) {
            return $this->noStoreJson([
                'mode' => $nextMode,
                'redirect' => $redirect,
            ]);
        }

        $label = $nextMode === 'PENJUAL' ? 'Penjual' : 'Pembeli';

        return redirect($redirect)
            ->with('sukses', 'Mode tampilan admin diganti ke mode ' . $label . '.');
    }
}
