<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAuthenticatedMarketplaceAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (config('app.bypass_onboarding', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isAdminUser()) {
            return $next($request);
        }

        if ($user->isBanned()) {
            Auth::logout();

            return redirect()->route('login')
                ->with('error', 'Akun Anda diblokir permanen. Hubungi admin untuk peninjauan.');
        }

        if ($user->isDeleted()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda sudah dinonaktifkan.');
        }

        if ($user->isSuspended()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Akun Anda sedang disuspend sampai ' . optional($user->suspended_until)->format('d M Y H:i'));
        }

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Status akun Anda belum aktif.');
        }

        if (! $user->hasCompletedRequiredAccountData()) {
            return redirect()->route('auth.onboarding.show')
                ->with('error', 'Lengkapi data akun terlebih dahulu sebelum masuk marketplace.');
        }

        if (! $user->hasVerifiedWhatsapp()) {
            return redirect()->route('auth.otp.challenge')
                ->with('error', 'Verifikasi OTP WhatsApp diperlukan sebelum masuk marketplace.');
        }

        return $next($request);
    }
}
