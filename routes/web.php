<?php

use App\Http\Controllers\AdminViewModeController;
use App\Http\Controllers\IkanController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\PembeliController;
use App\Http\Controllers\PenawaranController;
use App\Http\Controllers\PenjualController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => redirect()->route('ikans.index'));

$servePublicStorageFile = function (string $path) {
    $relativePath = ltrim($path, '/');

    if ($relativePath === '' || str_contains($relativePath, '..')) {
        abort(404);
    }

    $disk = Storage::disk('public');
    if (! $disk->exists($relativePath)) {
        abort(404);
    }

    return response()->file($disk->path($relativePath), [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
};

Route::get('/media/{path}', $servePublicStorageFile)
    ->where('path', '.*')
    ->name('media.fallback');

Route::get('/storage/{path}', $servePublicStorageFile)
    ->where('path', '.*')
    ->name('storage.fallback');

Route::get('/tentang-kami', [StaticPageController::class, 'about'])->name('pages.about');
Route::get('/kontak', [StaticPageController::class, 'contact'])->name('pages.contact');
Route::get('/kebijakan-privasi', [StaticPageController::class, 'privacy'])->name('pages.privacy');
Route::get('/syarat-ketentuan', [StaticPageController::class, 'terms'])->name('pages.terms');
Route::get('/kebijakan-pembayaran', [StaticPageController::class, 'paymentPolicy'])->name('pages.payment_policy');

Route::get('/ikans', [IkanController::class, 'index'])
    ->middleware('marketplace.ready')
    ->name('ikans.index');
Route::get('/ikans/{ikan}', [IkanController::class, 'show'])
    ->middleware('marketplace.ready')
    ->name('ikans.show');
Route::get('/toko/{seller}', [PenjualController::class, 'publicDashboard'])
    ->middleware('marketplace.ready')
    ->name('seller.public');
Route::get('/ikans/{ikan}/state', [IkanController::class, 'state'])
    ->middleware('throttle:lot-state')
    ->name('ikans.state');

require __DIR__.'/auth.php';

Route::get('/dashboard', function () {
    if (auth()->user()->isAdminUser()) {
        return redirect('/admin');
    }

    if (auth()->user()->isPenjual()) {
        return redirect()->route('penjual.dashboard');
    }

    return redirect()->route('ikans.index');
})
    ->middleware(['auth', 'user.active', 'onboarding.complete', 'otp.verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/seller', [ProfileController::class, 'updateSellerProfile'])->name('profile.seller.update');
    Route::post('/profile/delete-otp', [ProfileController::class, 'requestDeletionOtp'])
        ->middleware('throttle:otp-resend')
        ->name('profile.delete_otp');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'user.active', 'onboarding.complete', 'otp.verified'])->group(function () {
    Route::post('/admin/toggle-view-mode', [AdminViewModeController::class, 'toggle'])
        ->name('admin.toggle_view_mode');

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');
});

Route::middleware(['auth', 'user.active', 'onboarding.complete', 'otp.verified', 'penjual'])
    ->prefix('penjual')
    ->name('penjual.')
    ->group(function () {
        Route::get('/dashboard', [PenjualController::class, 'dashboard'])->name('dashboard');
        Route::get('/ikans', [PenjualController::class, 'index'])->name('ikans.index');
        Route::get('/ikans/create', [IkanController::class, 'create'])->name('ikans.create');
        Route::get('/ikans/{ikan}/edit', [IkanController::class, 'edit'])->name('ikans.edit');
        Route::post('/ikans', [IkanController::class, 'store'])->name('ikans.store');
        Route::patch('/ikans/{ikan}', [IkanController::class, 'update'])->name('ikans.update');
        Route::delete('/ikans/{ikan}', [IkanController::class, 'destroy'])->name('ikans.destroy');
        Route::get('/ikans/{ikan}', [PenjualController::class, 'show'])->name('ikans.show');
        Route::post('/ikans/{ikan}/packing', [PenjualController::class, 'markPacked'])->name('ikans.packing');
        Route::post('/ikans/{ikan}/pickup-arrived', [PenjualController::class, 'markPickupArrived'])->name('ikans.pickup_arrived');
    });

Route::middleware(['auth', 'user.active', 'onboarding.complete', 'otp.verified', 'pembeli'])
    ->prefix('pembeli')
    ->name('pembeli.')
    ->group(function () {
        Route::get('/aktivitas', [PembeliController::class, 'aktivitas'])->name('aktivitas');
        Route::get('/riwayat', [PembeliController::class, 'riwayatPembelian'])->name('riwayat');
        Route::get('/aktivitas/{ikan}/penilaian', [PembeliController::class, 'penilaian'])->name('aktivitas.penilaian');
        Route::get('/aktivitas/{ikan}', [PembeliController::class, 'aktivitasDetail'])->name('aktivitas.detail');
        Route::post('/ikans/{ikan}/pickup', [PembeliController::class, 'submitPickup'])->name('ikans.pickup');
        Route::post('/ikans/{ikan}/diterima', [PembeliController::class, 'konfirmasiDiterima'])->name('ikans.diterima');
        Route::post('/ikans/{ikan}/komplain', [PembeliController::class, 'ajukanKomplain'])->name('ikans.komplain');
    });

Route::middleware(['auth', 'user.active', 'onboarding.complete', 'otp.verified', 'pembeli', 'throttle:bid-actions'])
    ->post('/bid/{ikan}', [PenawaranController::class, 'store'])
    ->name('bid.store');

Route::middleware(['auth', 'user.active', 'onboarding.complete', 'otp.verified', 'pembeli', 'throttle:bid-actions'])
    ->post('/ikans/{ikan}/buy-now', [PenawaranController::class, 'buyNow'])
    ->name('ikans.buy_now');

Route::middleware(['auth', 'user.active', 'onboarding.complete', 'otp.verified', 'pembeli'])
    ->prefix('pembayaran')
    ->name('pembayaran.')
    ->group(function () {
        Route::get('/{transaksi}', [PembayaranController::class, 'show'])->name('show');
        Route::post('/{transaksi}/attempt', [PembayaranController::class, 'createAttempt'])
            ->middleware('throttle:payment-token')
            ->name('attempt');
        Route::post('/{transaksi}/refresh-status', [PembayaranController::class, 'refreshStatus'])
            ->middleware('throttle:payment-token')
            ->name('refresh');
        Route::get('/{transaksi}/selesai', [PembayaranController::class, 'selesai'])->name('selesai');
    });
