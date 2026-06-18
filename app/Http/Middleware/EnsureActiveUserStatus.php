<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isBanned()) {
            auth()->logout();

            return redirect()->route('login')->with('error', 'Akun Anda diblokir permanen. Hubungi admin untuk peninjauan.');
        }

        if ($user->isDeleted()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Akun Anda sudah dinonaktifkan.');
        }

        if ($user->isSuspended()) {
            return redirect()->route('profile.edit')->with('error', 'Akun Anda sedang disuspend sampai ' . optional($user->suspended_until)->format('d M Y H:i'));
        }

        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Status akun Anda belum aktif.');
        }

        return $next($request);
    }
}
