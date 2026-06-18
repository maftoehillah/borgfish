<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\OptimizesStoredImages;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Services\AuditService;
use App\Services\WhatsappOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    use OptimizesStoredImages;

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isAdminUser()) {
            return redirect('/admin');
        }

        // Bypass untuk verifikasi Tripay
        if (config('app.bypass_onboarding', false)) {
            return redirect()->route('ikans.index');
        }

        return view('auth.onboarding', [
            'user' => $user->loadMissing('sellerProfile'),
        ]);
    }

    public function store(Request $request, WhatsappOtpService $otp): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Bypass untuk verifikasi Tripay
        if (config('app.bypass_onboarding', false)) {
            return redirect()->route('ikans.index');
        }

        $rules = [
            'whatsapp_number' => ['required', 'string', 'max:32'],
        ];

        if ($user->isPenjual()) {
            $existingProfile = $user->sellerProfile;

            $rules = array_merge($rules, [
                'store_name' => ['required', 'string', 'max:150'],
                'full_address' => ['required', 'string', 'max:2000'],
                'store_latitude' => ['required', 'numeric', 'between:-90,90'],
                'store_longitude' => ['required', 'numeric', 'between:-180,180'],
                'store_gps_accuracy' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'store_photo' => [
                    filled($existingProfile?->store_photo_path) ? 'nullable' : 'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],
                'bank_name' => ['required', 'string', 'max:100'],
                'bank_account_number' => ['required', 'string', 'max:50'],
                'bank_account_name' => ['required', 'string', 'max:100'],
            ]);
        }

        $validated = $request->validate($rules);

        $normalizedWhatsapp = $otp->normalizePhoneNumber($validated['whatsapp_number']);
        if (! $normalizedWhatsapp) {
            return back()
                ->withErrors(['whatsapp_number' => 'Nomor WhatsApp tidak valid.'])
                ->withInput();
        }

        $phoneAlreadyUsed = \App\Models\User::query()
            ->where('whatsapp_number', $normalizedWhatsapp)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($phoneAlreadyUsed) {
            return back()
                ->withErrors(['whatsapp_number' => 'Nomor WhatsApp ini sudah terhubung dengan akun lain.'])
                ->withInput();
        }

        $user->whatsapp_number = $normalizedWhatsapp;
        $user->onboarding_completed_at = now();
        $user->save();

        if ($user->isPenjual()) {
            $existingProfile = $user->sellerProfile;
            $photoPath = $existingProfile?->store_photo_path;

            if ($request->hasFile('store_photo')) {
                $photoPath = $request->file('store_photo')->store('seller-profiles', 'public');
                $this->optimizeStoredImage($photoPath);

                if ($existingProfile?->store_photo_path && $existingProfile->store_photo_path !== $photoPath) {
                    Storage::disk('public')->delete($existingProfile->store_photo_path);
                }
            }

            $latitude = (float) $validated['store_latitude'];
            $longitude = (float) $validated['store_longitude'];

            SellerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'store_name' => $validated['store_name'],
                    'full_address' => $validated['full_address'],
                    'store_location' => sprintf('GPS: %.7f,%.7f', $latitude, $longitude),
                    'supporting_information' => null,
                    'store_latitude' => $latitude,
                    'store_longitude' => $longitude,
                    'store_gps_accuracy' => $validated['store_gps_accuracy'] ?? null,
                    'store_gps_captured_at' => now(),
                    'store_photo_path' => $photoPath,
                    'bank_name' => $validated['bank_name'],
                    'bank_account_number' => $validated['bank_account_number'],
                    'bank_account_name' => $validated['bank_account_name'],
                ]
            );
        }

        AuditService::log('user', (int) $user->id, 'auth.onboarding_completed', 'users', (int) $user->id, [
            'role' => $user->role,
        ]);

        $challenge = $otp->issue($user, 'phone_verification');
        session(['auth.otp_purpose' => 'phone_verification']);

        return redirect()->route('auth.otp.challenge', ['token' => $challenge->session_token])
            ->with('sukses', 'Data akun berhasil disimpan. Lanjutkan verifikasi nomor WhatsApp.');
    }
}
