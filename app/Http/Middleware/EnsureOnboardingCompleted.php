<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next)
    {
        // Bypass untuk verifikasi Tripay
        if (config('app.bypass_onboarding', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasCompletedRequiredAccountData()) {
            return redirect()->route('auth.onboarding.show')
                ->with('error', 'Lengkapi data akun terlebih dahulu sebelum masuk ke dashboard.');
        }

        return $next($request);
    }
}