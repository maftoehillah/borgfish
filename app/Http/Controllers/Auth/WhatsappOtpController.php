<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\WhatsappOtpChallenge;
use App\Services\WhatsappOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappOtpController extends Controller
{
    public function show(Request $request, WhatsappOtpService $otp): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isAdminUser()) {
            session(['otp_verified_user_id' => (int) $user->id]);

            return redirect('/admin');
        }

        $purpose = (string) session('auth.otp_purpose', 'login');

        if ($purpose === 'login' && $user->hasVerifiedWhatsapp()) {
            session(['otp_verified_user_id' => (int) $user->id]);

            return redirect()->intended(route('dashboard', absolute: false));
        }

        $token = (string) $request->query('token', '');

        $challenge = WhatsappOtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('status', 'pending')
            ->when($token !== '', fn ($query) => $query->where('session_token', $token))
            ->latest('id')
            ->first();

        if (! $challenge) {
            $challenge = $otp->issue($user, $purpose);
        }

        return view('auth.whatsapp-otp', [
            'user' => $user,
            'challenge' => $challenge,
            'purpose' => $purpose,
            'maskedPhone' => $this->maskPhone($challenge->phone_number),
        ]);
    }

    public function verify(Request $request, WhatsappOtpService $otp): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'session_token' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
            'purpose' => ['required', 'string', 'max:40'],
        ]);

        try {
            $otp->verify($user, $validated['session_token'], $validated['otp'], $validated['purpose']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        session([
            'otp_verified_user_id' => (int) $user->id,
        ]);
        session()->forget('auth.otp_purpose');

        if ($user->fresh()->needsOnboarding()) {
            return redirect()->route('auth.onboarding.show');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request, WhatsappOtpService $otp): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $purpose = (string) $request->input('purpose', session('auth.otp_purpose', 'login'));

        try {
            $challenge = $otp->resend($user, $purpose);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('auth.otp.challenge', ['token' => $challenge->session_token])
            ->with('sukses', 'Kode OTP baru telah dikirim ke WhatsApp Anda.');
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 5) {
            return $phone;
        }

        return substr($phone, 0, 4) . str_repeat('*', max(strlen($phone) - 7, 2)) . substr($phone, -3);
    }
}
