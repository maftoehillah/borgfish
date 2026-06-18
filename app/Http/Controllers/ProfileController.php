<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OptimizesStoredImages;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Services\AuditService;
use App\Services\WhatsappOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use OptimizesStoredImages;

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->safe()->only('name'));

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
    public function updateSellerProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isPenjual()) {
            abort(403);
        }

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:150'],
            'full_address' => ['required', 'string', 'max:2000'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_name' => ['required', 'string', 'max:100'],
            'store_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $existingProfile = $user->sellerProfile;
        $photoPath = $existingProfile?->store_photo_path;

        if ($request->hasFile('store_photo')) {
            $photoPath = $request->file('store_photo')->store('seller-profiles', 'public');
            $this->optimizeStoredImage($photoPath);
            if ($existingProfile?->store_photo_path && $existingProfile->store_photo_path !== $photoPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($existingProfile->store_photo_path);
            }
        }

        \App\Models\SellerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'store_name' => $validated['store_name'],
                'full_address' => $validated['full_address'],
                'bank_name' => $validated['bank_name'],
                'bank_account_number' => $validated['bank_account_number'],
                'bank_account_name' => $validated['bank_account_name'],
                'store_photo_path' => $photoPath,
            ]
        );

        return Redirect::route('profile.edit')->with('status', 'seller-profile-updated');
    }
    /**
     * Send a WhatsApp OTP before disabling the user's account.
     */
    public function requestDeletionOtp(Request $request, WhatsappOtpService $otp): RedirectResponse
    {
        $user = $request->user();

        if ($message = $this->accountDeletionBlockMessage($user)) {
            return back()->withErrors(['delete_account' => $message], 'userDeletion');
        }

        try {
            $challenge = $otp->issue($user, 'account_deletion');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['delete_account' => $e->getMessage()], 'userDeletion');
        }

        session(['account_deletion_otp_token' => $challenge->session_token]);

        return Redirect::route('profile.edit')
            ->with('sukses', 'OTP hapus akun telah dikirim ke WhatsApp Anda.');
    }

    /**
     * Disable the user's account after WhatsApp OTP verification.
     */
    public function destroy(Request $request, WhatsappOtpService $otp): RedirectResponse
    {
        $validated = $request->validateWithBag('userDeletion', [
            'session_token' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
            'confirmation' => ['accepted'],
        ]);

        $user = $request->user();

        if ($message = $this->accountDeletionBlockMessage($user)) {
            return back()->withErrors(['delete_account' => $message], 'userDeletion');
        }

        if (! hash_equals((string) session('account_deletion_otp_token'), (string) $validated['session_token'])) {
            return back()->withErrors(['otp' => 'Sesi OTP hapus akun tidak valid. Kirim ulang OTP terlebih dahulu.'], 'userDeletion');
        }

        try {
            $otp->verify($user, $validated['session_token'], $validated['otp'], 'account_deletion');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['otp' => $e->getMessage()], 'userDeletion');
        }

        $user->forceFill([
            'user_status' => 'deleted',
            'suspended_until' => null,
            'status_reason' => 'Akun dinonaktifkan oleh pengguna pada ' . now()->format('Y-m-d H:i:s'),
            'last_otp_verified_at' => now(),
        ])->save();

        AuditService::log('user', (int) $user->id, 'account.deleted_by_user', 'users', (int) $user->id, [
            'verified_with' => 'whatsapp_otp',
            'phone_number' => $user->whatsapp_number,
        ]);

        session()->forget('account_deletion_otp_token');
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('ikans.index')->with('status', 'Akun Anda berhasil dinonaktifkan.');
    }

    private function accountDeletionBlockMessage($user): ?string
    {
        if (! $user) {
            return 'Sesi akun tidak ditemukan. Silakan masuk ulang.';
        }

        if ($user->isAdminUser()) {
            return 'Akun admin tidak dapat dihapus dari halaman profil marketplace.';
        }

        if ($user->isBanned()) {
            return 'Akun yang diblokir permanen tidak dapat menghapus akun sendiri. Hubungi admin untuk peninjauan.';
        }

        if ($user->isDeleted()) {
            return 'Akun ini sudah dinonaktifkan.';
        }

        if (! $user->hasVerifiedWhatsapp()) {
            return 'Nomor WhatsApp harus terverifikasi sebelum akun dapat dihapus.';
        }

        if ($this->hasActiveSellerLot((int) $user->id)) {
            return 'Akun belum dapat dihapus karena masih ada lot lelang aktif atau menunggu pembayaran.';
        }

        if ($this->hasActiveBid((int) $user->id)) {
            return 'Akun belum dapat dihapus karena masih ada bid pada lelang yang sedang berjalan.';
        }

        if ($this->hasActiveBuyerTransaction((int) $user->id)) {
            return 'Akun belum dapat dihapus karena masih ada transaksi pembelian yang belum selesai.';
        }

        if ($this->hasActiveSellerTransaction((int) $user->id)) {
            return 'Akun belum dapat dihapus karena masih ada transaksi penjualan yang belum selesai.';
        }

        return null;
    }

    private function hasActiveSellerLot(int $userId): bool
    {
        return Ikan::query()
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->whereIn('status', ['menunggu', 'aktif'])
                    ->orWhereIn('auction_state', ['AKTIF', 'MENUNGGU_PEMBAYARAN']);
            })
            ->exists();
    }

    private function hasActiveBid(int $userId): bool
    {
        return Bid::query()
            ->where('user_id', $userId)
            ->whereHas('ikan', function ($query): void {
                $query->whereIn('status', ['menunggu', 'aktif'])
                    ->orWhereIn('auction_state', ['AKTIF']);
            })
            ->exists();
    }

    private function hasActiveBuyerTransaction(int $userId): bool
    {
        return Transaksi::query()
            ->where('pemenang_id', $userId)
            ->where($this->activeTransactionConstraint())
            ->exists();
    }

    private function hasActiveSellerTransaction(int $userId): bool
    {
        return Transaksi::query()
            ->whereHas('ikan', fn ($query) => $query->where('user_id', $userId))
            ->where($this->activeTransactionConstraint())
            ->exists();
    }

    private function activeTransactionConstraint(): \Closure
    {
        return function ($query): void {
            $query->whereIn('status', ['menunggu_bayar', 'proses'])
                ->orWhere('payment_status', 'pending')
                ->orWhere(function ($query): void {
                    $query->where('payment_status', 'paid')
                        ->where(function ($query): void {
                            $query->whereNull('pickup_status')
                                ->orWhere('pickup_status', '!=', 'completed');
                        })
                        ->where(function ($query): void {
                            $query->whereNull('fulfillment_state')
                                ->orWhereNotIn('fulfillment_state', ['SELESAI', 'GAGAL']);
                        });
                });
        };
    }
}
