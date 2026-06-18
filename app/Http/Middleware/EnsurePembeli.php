<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePembeli
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            abort(403, 'Halaman ini hanya untuk pembeli.');
        }

        $user = auth()->user();

        if (! $user->canActAsPembeli()) {
            if ($user->isSuperAdmin()) {
                return redirect()->route('ikans.index')
                    ->with('error', 'Switch ke mode pembeli terlebih dahulu untuk memakai fitur bidding.');
            }

            abort(403, 'Halaman ini hanya untuk pembeli.');
        }

        if (! $user->isActive()) {
            return redirect()->route('profile.edit')->with('error', 'Akun pembeli Anda belum aktif untuk mengakses fitur ini.');
        }

        return $next($request);
    }
}
