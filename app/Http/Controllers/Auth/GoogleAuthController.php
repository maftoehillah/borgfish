<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    private const ACCOUNT_ROLES = ['pembeli', 'penjual'];

    public function redirect(): RedirectResponse
    {
        $role = request()->query('role');
        $flow = request()->query('flow') === 'register' ? 'register' : 'login';

        session(['auth.flow' => $flow]);

        if ($flow === 'register' && in_array($role, self::ACCOUNT_ROLES, true)) {
            session(['auth.intended_role' => $role]);
        } else {
            session()->forget('auth.intended_role');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', 'Login Google gagal diproses. Silakan coba lagi.');
        }

        $email = Str::lower((string) $googleUser->getEmail());
        $googleId = (string) $googleUser->getId();
        $flow = session('auth.flow') === 'register' ? 'register' : 'login';
        $roleFromSession = session('auth.intended_role');
        $roleFromSession = $flow === 'register' && in_array($roleFromSession, self::ACCOUNT_ROLES, true)
            ? $roleFromSession
            : null;
        $adminWhitelist = collect(config('marketplace.admin_whitelist', []))
            ->push((string) config('app.superadmin_email', User::SUPERADMIN_EMAIL))
            ->map(fn (string $value) => Str::lower(trim($value)));

        $isAdminEmail = $adminWhitelist->contains($email);

        $user = User::query()
            ->where(function ($query) use ($email, $googleId): void {
                $query->where('email', $email);

                if ($googleId !== '') {
                    $query->orWhere('google_id', $googleId);
                }
            })
            ->first();

        if ($isAdminEmail && $flow === 'register') {
            session()->forget(['auth.flow', 'auth.intended_role']);

            return redirect()->route('register')
                ->with('error', 'Email Google ini terdaftar sebagai admin. Gunakan halaman Masuk untuk membuka dashboard admin, atau gunakan akun Google lain untuk mendaftar sebagai pembeli/penjual.');
        }

        if (! $user && ! $isAdminEmail && $flow !== 'register') {
            return redirect()->route('register')
                ->with('error', 'Akun Google ini belum terdaftar. Pilih role Pembeli atau Penjual terlebih dahulu dari halaman daftar.');
        }

        if (! $user && ! $isAdminEmail && $roleFromSession === null) {
            session()->forget(['auth.flow', 'auth.intended_role']);

            return redirect()->route('register')
                ->with('error', 'Pilih role Pembeli atau Penjual sebelum melanjutkan pendaftaran.');
        }

        if ($user?->isDeleted()) {
            session()->forget(['auth.flow', 'auth.intended_role']);

            return redirect()->route('login')
                ->with('error', 'Akun ini sudah dinonaktifkan. Hubungi admin jika membutuhkan pemulihan akun.');
        }

        if ($user && ! $isAdminEmail && $flow === 'register' && $roleFromSession !== null && (string) $user->role !== $roleFromSession) {
            if (! $this->canRechooseRoleBeforeActivation($user)) {
                session()->forget(['auth.flow', 'auth.intended_role']);

                return redirect()->route('login')
                    ->with('error', 'Akun Google ini sudah terdaftar sebagai ' . $user->displayRoleLabel() . '. Gunakan halaman Masuk untuk melanjutkan sesuai role yang sudah tersimpan.');
            }

            $this->applyPendingRegistrationRole($user, $roleFromSession);
        }

        $user ??= new User();
        $user->fill([
            'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Pengguna Borgfish',
            'email' => $email,
            'google_id' => $googleId,
            'auth_provider' => 'google',
            'google_avatar' => $googleUser->getAvatar(),
            'email_verified_at' => now(),
            'password' => $user->password ?: Hash::make(Str::random(40)),
        ]);

        if ($isAdminEmail) {
            $user->role = in_array((string) $user->role, self::ACCOUNT_ROLES, true)
                ? $user->role
                : ($roleFromSession ?: 'pembeli');
            $user->is_admin = true;
            $user->onboarding_completed_at = $user->onboarding_completed_at ?? now();
            $user->user_status = 'active';
        } elseif (! $user->exists) {
            $user->role = $roleFromSession;
            $user->is_admin = false;
            $user->user_status = 'active';
        } elseif ($user->is_admin) {
            $user->is_admin = false;
        }

        $user->last_login_at = now();
        $user->save();

        Auth::login($user, true);
        request()->session()->regenerate();
        session()->forget([
            'auth.flow',
            'auth.intended_role',
            'auth.otp_purpose',
            'otp_verified_user_id',
        ]);

        AuditService::log('user', (int) $user->id, 'auth.google_login', 'users', (int) $user->id, [
            'email' => $email,
            'is_admin' => $user->isAdminUser(),
        ]);

        if ($user->isAdminUser()) {
            session([
                'otp_verified_user_id' => (int) $user->id,
                'superadmin_view_mode' => session('superadmin_view_mode', 'PEMBELI'),
            ]);

            return redirect('/admin')
                ->with('status', 'Akun Google ini terdaftar sebagai admin dan diarahkan ke dashboard admin.');
        }

        // Bypass onboarding dan OTP untuk verifikasi Tripay
        if (config('app.bypass_onboarding', false)) {
            session(['otp_verified_user_id' => (int) $user->id]);
            return redirect()->route('ikans.index');
        }

        if ($user->needsOnboarding()) {
            return redirect()->route('auth.onboarding.show');
        }

        session(['otp_verified_user_id' => (int) $user->id]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function canRechooseRoleBeforeActivation(User $user): bool
    {
        if ($user->isAdminUser() || $user->hasVerifiedWhatsapp()) {
            return false;
        }

        return ! $user->ikans()->exists()
            && ! $user->bids()->exists()
            && ! $user->transaksis()->exists();
    }

    private function applyPendingRegistrationRole(User $user, string $role): void
    {
        $oldRole = (string) $user->role;

        $user->role = $role;
        $user->is_admin = false;
        $user->onboarding_completed_at = null;

        if ($role === 'pembeli' && $oldRole === 'penjual') {
            $user->sellerProfile()->delete();
        }

        AuditService::log('user', (int) $user->id, 'auth.pending_registration_role_changed', 'users', (int) $user->id, [
            'old_role' => $oldRole,
            'new_role' => $role,
        ]);
    }
}
