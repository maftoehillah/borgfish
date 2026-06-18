<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOtpVerifiedSession
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
                ->with('error', 'Lengkapi data akun terlebih dahulu sebelum verifikasi OTP.');
        }

        if ($user->hasVerifiedWhatsapp()) {
            return $next($request);
        }

        if ($request->session()->get('otp_verified_user_id') === (int) $user->id) {
            return $next($request);
        }

        return redirect()->route('auth.otp.challenge');
    }
}
