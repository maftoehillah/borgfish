<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function all(): array
    {
        return Cache::remember(SystemSetting::CACHE_KEY, now()->addMinutes(10), function (): array {
            try {
                return SystemSetting::query()
                    ->orderBy('group')
                    ->orderBy('key')
                    ->get()
                    ->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $this->castValue($setting->value, $setting->type)])
                    ->all();
            } catch (\Throwable) {
                return $this->defaults();
            }
        });
    }

    public function grouped(): array
    {
        return SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(fn ($rows) => $rows->keyBy('key'))
            ->all();
    }

    public function put(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        $existing = SystemSetting::query()->where('key', $key)->first();
        $normalizedValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $normalizedValue,
                'type' => $type,
            ]
        );

        $this->logAuditIfChanged($setting, $existing, $normalizedValue, $group, $type);

        Cache::forget(SystemSetting::CACHE_KEY);
    }

    public function paymentDeadlineMinutes(): int
    {
        $configuredMinutes = (int) $this->get(
            'payment_deadline_minutes',
            config('marketplace.payment_deadline_minutes', 30),
        );

        return max(1, min(240, $configuredMinutes));
    }

    public function antiSnipingEnabledByDefault(): bool
    {
        return (bool) $this->get('anti_sniping_enabled_default', true);
    }

    public function antiSnipingWindowSeconds(): int
    {
        return $this->boundedInt('anti_sniping_window_seconds', 60, 30, 600);
    }

    public function antiSnipingExtendSeconds(): int
    {
        return $this->boundedInt('anti_sniping_extend_seconds', 90, 30, 600);
    }

    public function antiSnipingMaxExtensions(): int
    {
        return $this->boundedInt('anti_sniping_max_extensions', 3, 1, 20);
    }

    public function settlementAutoCreateEnabled(): bool
    {
        return (bool) $this->get('settlement_auto_create_enabled', true);
    }

    public function settlementPayoutDelayDays(): int
    {
        return $this->boundedInt('settlement_payout_delay_days', 0, 0, 30);
    }

    public function settlementMinPayoutAmount(): int
    {
        return $this->boundedInt('settlement_min_payout_amount', 0, 0, 1000000000);
    }

    public function settlementRequiresAdminReview(): bool
    {
        return (bool) $this->get('settlement_requires_admin_review', true);
    }

    public function settlementHoldOnDispute(): bool
    {
        return (bool) $this->get('settlement_hold_on_dispute', true);
    }

    public function settlementHoldOnViolation(): bool
    {
        return (bool) $this->get('settlement_hold_on_violation', true);
    }

    public function whatsappSenderName(): string
    {
        $name = trim((string) $this->get('whatsapp_sender_name', config('whatsapp.sender_name', 'Borgfish')));

        return $name !== '' ? $name : 'Borgfish';
    }

    public function whatsappFailSilently(): bool
    {
        return (bool) $this->get('whatsapp_fail_silently', (bool) config('whatsapp.fail_silently', false));
    }

    public function whatsappAdminContactTemplate(): string
    {
        $template = trim((string) $this->get('whatsapp_admin_contact_template', $this->defaultWhatsappAdminContactTemplate()));

        return $template !== '' ? $template : $this->defaultWhatsappAdminContactTemplate();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderWhatsappAdminContactMessage(array $context = []): string
    {
        $replacements = [
            '{site_name}' => trim((string) ($this->get('site_name', 'Borgfish') ?: 'Borgfish')),
            '{user_role}' => trim((string) ($context['user_role'] ?? '')),
            '{user_name}' => trim((string) ($context['user_name'] ?? '')),
            '{user_email}' => trim((string) ($context['user_email'] ?? '')),
            '{user_phone}' => trim((string) ($context['user_phone'] ?? '')),
            '{transaction_id}' => trim((string) ($context['transaction_id'] ?? '')),
            '{current_url}' => trim((string) ($context['current_url'] ?? '')),
        ];

        $message = strtr($this->whatsappAdminContactTemplate(), $replacements);
        $lines = preg_split("/\r\n|\r|\n/", $message) ?: [];
        $cleanedLines = [];
        $previousBlank = false;

        foreach ($lines as $line) {
            $normalizedLine = rtrim((string) $line);
            $trimmedLine = trim($normalizedLine);

            if ($trimmedLine !== '' && preg_match('/[:：]\s*$/', $trimmedLine) === 1) {
                continue;
            }

            if ($trimmedLine === '') {
                if ($previousBlank) {
                    continue;
                }

                $cleanedLines[] = '';
                $previousBlank = true;

                continue;
            }

            $cleanedLines[] = $normalizedLine;
            $previousBlank = false;
        }

        $message = trim(implode("\n", $cleanedLines));

        return $message !== '' ? $message : $this->defaultWhatsappAdminContactTemplate();
    }

    public function otpTtlMinutes(): int
    {
        return $this->boundedInt('otp_ttl_minutes', (int) config('marketplace.otp.ttl_minutes', 5), 1, 60);
    }

    public function otpMaxAttempts(): int
    {
        return $this->boundedInt('otp_max_attempts', (int) config('marketplace.otp.max_attempts', 5), 1, 20);
    }

    public function otpMaxResend(): int
    {
        return $this->boundedInt('otp_max_resend', (int) config('marketplace.otp.max_resend', 3), 0, 20);
    }

    public function otpRateLimitPerHour(): int
    {
        return $this->boundedInt('otp_rate_limit_per_hour', (int) config('marketplace.otp.rate_limit_per_hour', 6), 1, 100);
    }

    public function otpRateLimitPerNumberPerHour(): int
    {
        return $this->boundedInt(
            'otp_rate_limit_per_number_per_hour',
            (int) config('marketplace.otp.rate_limit_per_number_per_hour', config('marketplace.otp.rate_limit_per_hour', 6)),
            1,
            100,
        );
    }

    public function otpResendCooldownSeconds(): int
    {
        return $this->boundedInt('otp_resend_cooldown_seconds', (int) config('marketplace.otp.resend_cooldown_seconds', 60), 1, 3600);
    }

    public function maintenanceEnabled(): bool
    {
        return (bool) $this->get('maintenance_enabled', (bool) config('maintenance-page.enabled', false));
    }

    public function maintenanceHtmlPath(): string
    {
        return trim((string) $this->get('maintenance_html_path', (string) config('maintenance-page.html_path', public_path('maintenance.html'))));
    }

    public function maintenanceStatus(): int
    {
        return $this->boundedInt('maintenance_status', (int) config('maintenance-page.status', 503), 200, 599);
    }

    /**
     * @return array<int, string>
     */
    public function maintenanceExcept(): array
    {
        $configured = $this->get('maintenance_except', config('maintenance-page.except', []));

        if (is_array($configured)) {
            return array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $configured
            )));
        }

        return array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            preg_split('/[\r\n,]+/', (string) $configured) ?: []
        )));
    }

    public function maintenanceMessage(): string
    {
        $message = trim((string) $this->get('maintenance_message', 'Situs sedang maintenance. Silakan coba lagi beberapa saat lagi.'));

        return $message !== '' ? $message : 'Situs sedang maintenance. Silakan coba lagi beberapa saat lagi.';
    }

    private function boundedInt(string $key, int $default, int $min, int $max): int
    {
        $value = (int) $this->get($key, $default);

        return max($min, min($max, $value));
    }

    private function logAuditIfChanged(SystemSetting $setting, ?SystemSetting $existing, string $newValue, string $group, string $type): void
    {
        if (! auth()->check()) {
            return;
        }

        $oldValue = $existing?->value;
        if ($oldValue === $newValue
            && (string) ($existing?->group ?? $group) === $group
            && (string) ($existing?->type ?? $type) === $type) {
            return;
        }

        AuditService::log(
            'admin',
            (int) auth()->id(),
            'system_setting.updated',
            'system_settings',
            (int) $setting->id,
            [
                'key' => $setting->key,
                'group' => $group,
                'type' => $type,
                'old' => $this->castAuditValue($oldValue, $type),
                'new' => $this->castAuditValue($newValue, $type),
            ]
        );
    }

    private function castAuditValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => $value === null ? null : (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'json' => $value ? json_decode($value, true) : [],
            default => $value,
        };
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => $value === null ? null : (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'json' => $value ? json_decode($value, true) : [],
            default => $value,
        };
    }

    private function defaults(): array
    {
        return [
            'site_name' => 'Borgfish',
            'site_address' => 'Indonesia',
            'site_email' => 'admin@borgfish.test',
            'site_admin_contact' => '6283873041404',
            'social_facebook_url' => '',
            'social_instagram_url' => '',
            'social_tiktok_url' => '',
            'whatsapp_sender_name' => 'Borgfish',
            'whatsapp_fail_silently' => (bool) config('whatsapp.fail_silently', false),
            'whatsapp_admin_contact_template' => $this->defaultWhatsappAdminContactTemplate(),
            'otp_ttl_minutes' => (int) config('marketplace.otp.ttl_minutes', 5),
            'otp_max_attempts' => (int) config('marketplace.otp.max_attempts', 5),
            'otp_max_resend' => (int) config('marketplace.otp.max_resend', 3),
            'otp_rate_limit_per_hour' => (int) config('marketplace.otp.rate_limit_per_hour', 6),
            'otp_rate_limit_per_number_per_hour' => (int) config('marketplace.otp.rate_limit_per_number_per_hour', 6),
            'otp_resend_cooldown_seconds' => (int) config('marketplace.otp.resend_cooldown_seconds', 60),
            'maintenance_enabled' => (bool) config('maintenance-page.enabled', false),
            'maintenance_html_path' => (string) config('maintenance-page.html_path', public_path('maintenance.html')),
            'maintenance_status' => (int) config('maintenance-page.status', 503),
            'maintenance_except' => config('maintenance-page.except', ['up', 'api/tripay/callback']),
            'maintenance_message' => 'Situs sedang maintenance. Silakan coba lagi beberapa saat lagi.',
            'anti_sniping_enabled_default' => true,
            'anti_sniping_window_seconds' => 60,
            'anti_sniping_extend_seconds' => 90,
            'anti_sniping_max_extensions' => 3,
            'settlement_auto_create_enabled' => true,
            'settlement_payout_delay_days' => 0,
            'settlement_min_payout_amount' => 0,
            'settlement_requires_admin_review' => true,
            'settlement_hold_on_dispute' => true,
            'settlement_hold_on_violation' => true,
        ];
    }

    private function defaultWhatsappAdminContactTemplate(): string
    {
        return "Halo admin {site_name}, saya butuh bantuan.\n\nRole: {user_role}\nNama: {user_name}\nEmail: {user_email}\nNo. Telepon: {user_phone}\nID Transaksi: {transaction_id}\nHalaman: {current_url}";
    }
}
