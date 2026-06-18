<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePenjual
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            abort(403, 'Halaman ini hanya untuk penjual.');
        }

        $user = auth()->user();

        if (! $user->canActAsPenjual()) {
            if ($user->isSuperAdmin()) {
                return redirect()->route('ikans.index')
                    ->with('error', 'Switch ke mode penjual terlebih dahulu untuk mengelola lot lelang.');
            }

            abort(403, 'Halaman ini hanya untuk penjual.');
        }

        if (! $user->isActive()) {
            return redirect()->route('profile.edit')->with('error', 'Akun penjual Anda belum aktif untuk mengakses fitur ini.');
        }

        return $next($request);
    }
}
