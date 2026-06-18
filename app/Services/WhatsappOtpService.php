<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsappOtpChallenge;
use App\Services\Whatsapp\WhatsappMessageProvider;
use App\Services\Whatsapp\WhatsappSendResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsappOtpService
{
    public function __construct(
        private readonly WhatsappMessageProvider $provider,
        private readonly SystemSettingService $settings,
    ) {}

    public function issue(User $user, string $purpose = 'login'): WhatsappOtpChallenge
    {
        $phone = $this->normalizePhoneNumber((string) $user->whatsapp_number);

        if ($phone === null) {
            throw new \RuntimeException('Nomor WhatsApp belum tersedia untuk verifikasi OTP.');
        }

        $this->ensureRateLimitAllows($user, $phone, $purpose);

        WhatsappOtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        return $this->createAndSendChallenge($user, $phone, $purpose, 0, false);
    }

    public function resend(User $user, string $purpose = 'login'): WhatsappOtpChallenge
    {
        $phone = $this->normalizePhoneNumber((string) $user->whatsapp_number);

        if ($phone === null) {
            throw new \RuntimeException('Nomor WhatsApp belum tersedia untuk verifikasi OTP.');
        }

        $latest = WhatsappOtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('phone_number', $phone)
            ->latest('id')
            ->first();

        if (! $latest || (string) $latest->status !== 'pending') {
            return $this->issue($user, $purpose);
        }

        $cooldownSeconds = $this->settings->otpResendCooldownSeconds();
        $nextAllowedAt = $latest->sent_at?->copy()->addSeconds($cooldownSeconds);
        if ($nextAllowedAt && now()->lt($nextAllowedAt)) {
            $seconds = (int) max(1, now()->diffInSeconds($nextAllowedAt));

            $this->logOtpRequest($user, $phone, $purpose, 'resend_cooldown_blocked', [
                'challenge_id' => $latest->id,
                'cooldown_seconds' => $cooldownSeconds,
                'retry_after_seconds' => $seconds,
            ]);

            throw new \RuntimeException("Tunggu {$seconds} detik sebelum meminta OTP baru.");
        }

        $maxResend = $this->settings->otpMaxResend();
        $nextResendCount = ((int) $latest->resend_count) + 1;
        if ($nextResendCount > $maxResend) {
            $this->logOtpRequest($user, $phone, $purpose, 'max_resend_blocked', [
                'challenge_id' => $latest->id,
                'max_resend' => $maxResend,
            ]);

            throw new \RuntimeException('Batas kirim ulang OTP tercapai. Coba lagi nanti.');
        }

        $this->ensureRateLimitAllows($user, $phone, $purpose);

        $latest->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return $this->createAndSendChallenge($user, $phone, $purpose, $nextResendCount, true);
    }

    private function createAndSendChallenge(User $user, string $phone, string $purpose, int $resendCount, bool $isResend): WhatsappOtpChallenge
    {
        $plainOtp = (string) random_int(100000, 999999);

        $challenge = WhatsappOtpChallenge::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($plainOtp),
            'session_token' => Str::random(48),
            'status' => 'pending',
            'attempts' => 0,
            'resend_count' => $resendCount,
            'expires_at' => now()->addMinutes($this->settings->otpTtlMinutes()),
            'sent_at' => now(),
        ]);

        $message = $this->messageForPurpose($plainOtp, $purpose);

        $this->logOtpRequest($user, $phone, $purpose, $isResend ? 'resend_requested' : 'requested', [
            'challenge_id' => $challenge->id,
            'resend_count' => $resendCount,
            'provider' => $this->provider->name(),
        ]);

        try {
            $result = $this->provider->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            $challenge->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();

            $this->logOtpRequest($user, $phone, $purpose, 'send_failed', [
                'challenge_id' => $challenge->id,
                'provider' => $this->provider->name(),
                'error' => $e->getMessage(),
            ]);

            Log::warning('whatsapp.otp_send_failed', [
                'provider' => $this->provider->name(),
                'to' => $phone,
                'purpose' => $purpose,
                'challenge_id' => $challenge->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('OTP gagal dikirim ke WhatsApp. Periksa konfigurasi provider WhatsApp.', previous: $e);
        }

        $this->logOtpSent($user, $phone, $purpose, $challenge, $result);

        if ((bool) config('whatsapp.show_dev_otp', false)) {
            session()->flash('dev_whatsapp_otp', $plainOtp);
        }

        return $challenge;
    }

    public function verify(User $user, string $sessionToken, string $otp, string $purpose = 'login'): WhatsappOtpChallenge
    {
        $challenge = WhatsappOtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('session_token', $sessionToken)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $challenge) {
            throw new \RuntimeException('OTP tidak ditemukan atau sudah tidak berlaku.');
        }

        if ($challenge->expires_at && now()->gt($challenge->expires_at)) {
            $challenge->status = 'expired';
            $challenge->save();

            throw new \RuntimeException('OTP sudah kedaluwarsa. Silakan minta kode baru.');
        }

        $maxAttempts = $this->settings->otpMaxAttempts();
        $challenge->attempts = ((int) $challenge->attempts) + 1;

        if ((int) $challenge->attempts > $maxAttempts) {
            $challenge->status = 'cancelled';
            $challenge->cancelled_at = now();
            $challenge->save();

            throw new \RuntimeException('Percobaan OTP melebihi batas yang diizinkan.');
        }

        if (! Hash::check($otp, $challenge->otp_hash)) {
            if ((int) $challenge->attempts >= $maxAttempts) {
                $challenge->status = 'cancelled';
                $challenge->cancelled_at = now();
            }

            $challenge->save();

            AuditService::log('system', null, 'auth.otp_verify_failed', 'whatsapp_otp_challenges', (int) $challenge->id, [
                'user_id' => $user->id,
                'phone_number' => $challenge->phone_number,
                'purpose' => $purpose,
                'attempts' => $challenge->attempts,
                'max_attempts' => $maxAttempts,
                'status' => $challenge->status,
            ]);

            if ((string) $challenge->status === 'cancelled') {
                throw new \RuntimeException('Percobaan OTP melebihi batas yang diizinkan.');
            }

            throw new \RuntimeException('Kode OTP tidak sesuai.');
        }

        $challenge->status = 'verified';
        $challenge->verified_at = now();
        $challenge->save();

        $user->forceFill([
            'whatsapp_number' => $challenge->phone_number,
            'whatsapp_verified_at' => $user->whatsapp_verified_at ?? now(),
            'last_otp_verified_at' => now(),
        ])->save();

        AuditService::log('system', null, 'auth.otp_verified', 'users', (int) $user->id, [
            'purpose' => $purpose,
            'challenge_id' => $challenge->id,
        ]);

        return $challenge;
    }

    public function normalizePhoneNumber(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (! str_starts_with($digits, '62')) {
            return '62' . $digits;
        }

        return $digits;
    }

    private function ensureRateLimitAllows(User $user, string $phone, string $purpose): void
    {
        $windowStartedAt = now()->subHour();

        $recentUserQuery = WhatsappOtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $windowStartedAt);

        $recentUserCount = (clone $recentUserQuery)->count();
        $userLimit = $this->settings->otpRateLimitPerHour();
        if ($recentUserCount >= $userLimit) {
            $retryAfterSeconds = $this->otpWindowRetryAfterSeconds($recentUserQuery);
            $retryAfterText = $this->formatRetryAfter($retryAfterSeconds);

            $this->logOtpRequest($user, $phone, $purpose, 'rate_limited_user', [
                'limit' => $userLimit,
                'window_minutes' => 60,
                'retry_after_seconds' => $retryAfterSeconds,
                'retry_after_human' => $retryAfterText,
            ]);

            throw new \RuntimeException("Batas kirim OTP akun ini tercapai. Coba lagi dalam {$retryAfterText}.");
        }

        $recentPhoneQuery = WhatsappOtpChallenge::query()
            ->where('phone_number', $phone)
            ->where('created_at', '>=', $windowStartedAt);

        $recentPhoneCount = (clone $recentPhoneQuery)->count();
        $phoneLimit = $this->settings->otpRateLimitPerNumberPerHour();
        if ($recentPhoneCount >= $phoneLimit) {
            $retryAfterSeconds = $this->otpWindowRetryAfterSeconds($recentPhoneQuery);
            $retryAfterText = $this->formatRetryAfter($retryAfterSeconds);

            $this->logOtpRequest($user, $phone, $purpose, 'rate_limited_phone', [
                'limit' => $phoneLimit,
                'window_minutes' => 60,
                'retry_after_seconds' => $retryAfterSeconds,
                'retry_after_human' => $retryAfterText,
            ]);

            throw new \RuntimeException("Batas kirim OTP untuk nomor WhatsApp ini tercapai. Coba lagi dalam {$retryAfterText}.");
        }
    }

    private function otpWindowRetryAfterSeconds(Builder $query): int
    {
        $oldestCreatedAt = (clone $query)
            ->oldest('created_at')
            ->value('created_at');

        if (! $oldestCreatedAt) {
            return 3600;
        }

        $oldestCreatedAt = \Illuminate\Support\Carbon::parse($oldestCreatedAt);

        $availableAt = $oldestCreatedAt->copy()->addHour();

        return max(1, $availableAt->getTimestamp() - now()->getTimestamp());
    }

    private function formatRetryAfter(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        if ($minutes <= 1) {
            return 'kurang dari 1 menit';
        }

        if ($minutes >= 60) {
            return 'sekitar 1 jam';
        }

        return "{$minutes} menit";
    }

    private function messageForPurpose(string $plainOtp, string $purpose): string
    {
        $ttlMinutes = $this->settings->otpTtlMinutes();

        return match ($purpose) {
            'account_deletion' => "Kode OTP hapus akun Borgfish Anda: {$plainOtp}. Berlaku selama {$ttlMinutes} menit. Abaikan jika bukan Anda.",
            default => "Kode OTP Borgfish Anda: {$plainOtp}. Berlaku selama {$ttlMinutes} menit.",
        };
    }

    private function logOtpRequest(User $user, string $phone, string $purpose, string $event, array $payload = []): void
    {
        AuditService::log('system', null, 'auth.otp_' . $event, 'users', (int) $user->id, array_merge([
            'phone_number' => $phone,
            'purpose' => $purpose,
            'provider' => $this->provider->name(),
        ], $payload));
    }

    private function logOtpSent(User $user, string $phone, string $purpose, WhatsappOtpChallenge $challenge, WhatsappSendResult $result): void
    {
        AuditService::log('system', null, 'auth.otp_sent', 'whatsapp_otp_challenges', (int) $challenge->id, [
            'user_id' => $user->id,
            'phone_number' => $phone,
            'purpose' => $purpose,
            'provider' => $result->provider,
            'provider_reference' => $result->providerReference,
        ]);

        Log::info('whatsapp.otp_sent', [
            'provider' => $result->provider,
            'to' => $phone,
            'provider_reference' => $result->providerReference,
        ]);
    }
}
