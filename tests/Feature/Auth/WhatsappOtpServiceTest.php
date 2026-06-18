<?php

namespace Tests\Feature\Auth;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappOtpChallenge;
use App\Services\Whatsapp\WhatsappMessageProvider;
use App\Services\Whatsapp\WhatsappSendResult;
use App\Services\WhatsappOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappOtpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fonnte_provider_is_used_for_otp_delivery_and_request_is_logged(): void
    {
        config([
            'whatsapp.driver' => 'fonnte',
            'whatsapp.fonnte.endpoint' => 'https://api.fonnte.com/send',
            'whatsapp.fonnte.token' => 'fonnte-test-token',
            'whatsapp.show_dev_otp' => false,
            'marketplace.otp.ttl_minutes' => 5,
            'marketplace.otp.rate_limit_per_hour' => 10,
            'marketplace.otp.rate_limit_per_number_per_hour' => 10,
        ]);
        app()->forgetInstance(WhatsappMessageProvider::class);

        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'process' => ['fon-001'],
            ], 200),
        ]);

        $user = $this->makeBuyer();

        $challenge = app(WhatsappOtpService::class)->issue($user, 'phone_verification');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.fonnte.com/send'
            && $request->hasHeader('Authorization', 'fonnte-test-token')
            && $request['target'] === '6281234567890'
            && str_contains((string) $request['message'], 'Kode OTP Borgfish Anda:')
        );

        $this->assertSame('pending', $challenge->status);
        $this->assertTrue($challenge->expires_at->between(now()->addMinutes(4), now()->addMinutes(6)));
        $this->assertFalse(session()->has('dev_whatsapp_otp'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.otp_requested',
            'resource_type' => 'users',
            'resource_id' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.otp_sent',
            'resource_type' => 'whatsapp_otp_challenges',
            'resource_id' => $challenge->id,
        ]);
    }

    public function test_resend_otp_is_blocked_during_cooldown(): void
    {
        $provider = $this->fakeWhatsappProvider();
        config([
            'marketplace.otp.resend_cooldown_seconds' => 60,
            'marketplace.otp.rate_limit_per_hour' => 10,
            'marketplace.otp.rate_limit_per_number_per_hour' => 10,
        ]);

        $user = $this->makeBuyer();
        $service = app(WhatsappOtpService::class);
        $challenge = $service->issue($user, 'login');

        try {
            $service->resend($user, 'login');
            $this->fail('Resend OTP should be blocked by cooldown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Tunggu', $e->getMessage());
        }

        $this->assertCount(1, $provider->messages);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.otp_resend_cooldown_blocked',
            'resource_type' => 'users',
            'resource_id' => $user->id,
        ]);
        $this->assertDatabaseHas('whatsapp_otp_challenges', [
            'id' => $challenge->id,
            'status' => 'pending',
        ]);
    }

    public function test_resend_otp_respects_max_resend_limit(): void
    {
        $provider = $this->fakeWhatsappProvider();
        config([
            'marketplace.otp.max_resend' => 1,
            'marketplace.otp.resend_cooldown_seconds' => 1,
            'marketplace.otp.rate_limit_per_hour' => 10,
            'marketplace.otp.rate_limit_per_number_per_hour' => 10,
        ]);

        $user = $this->makeBuyer();
        $service = app(WhatsappOtpService::class);
        $service->issue($user, 'login');

        $this->travel(2)->seconds();
        $service->resend($user, 'login');

        $this->travel(2)->seconds();
        try {
            $service->resend($user, 'login');
            $this->fail('Resend OTP should respect max resend limit.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Batas kirim ulang OTP', $e->getMessage());
        }

        $this->assertCount(2, $provider->messages);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.otp_max_resend_blocked',
            'resource_type' => 'users',
            'resource_id' => $user->id,
        ]);
    }

    public function test_otp_rate_limit_is_checked_per_number(): void
    {
        $this->fakeWhatsappProvider();
        config([
            'marketplace.otp.rate_limit_per_hour' => 10,
            'marketplace.otp.rate_limit_per_number_per_hour' => 1,
        ]);

        $user = $this->makeBuyer();
        $service = app(WhatsappOtpService::class);
        $service->issue($user, 'login');

        try {
            $service->issue($user, 'login');
            $this->fail('OTP request should be blocked per WhatsApp number.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('nomor WhatsApp ini', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.otp_rate_limited_phone',
            'resource_type' => 'users',
            'resource_id' => $user->id,
        ]);
    }

    public function test_otp_rate_limit_message_shows_user_retry_estimate(): void
    {
        $this->fakeWhatsappProvider();
        config([
            'marketplace.otp.rate_limit_per_hour' => 1,
            'marketplace.otp.rate_limit_per_number_per_hour' => 10,
        ]);

        $user = $this->makeBuyer();
        $service = app(WhatsappOtpService::class);
        $service->issue($user, 'login');

        $this->travel(10)->minutes();

        try {
            $service->issue($user, 'login');
            $this->fail('OTP request should be blocked per user account.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('akun ini', $e->getMessage());
            $this->assertStringContainsString('50 menit', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.otp_rate_limited_user',
            'resource_type' => 'users',
            'resource_id' => $user->id,
        ]);
    }

    public function test_wrong_otp_is_cancelled_after_max_attempts(): void
    {
        config(['marketplace.otp.max_attempts' => 3]);

        $user = $this->makeBuyer();
        $challenge = WhatsappOtpChallenge::create([
            'user_id' => $user->id,
            'phone_number' => $user->whatsapp_number,
            'purpose' => 'login',
            'otp_hash' => Hash::make('123456'),
            'session_token' => 'token-test',
            'attempts' => 2,
            'resend_count' => 0,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
            'sent_at' => now(),
        ]);

        try {
            app(WhatsappOtpService::class)->verify($user, 'token-test', '000000', 'login');
            $this->fail('Wrong OTP should be cancelled at max attempts.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Percobaan OTP melebihi batas', $e->getMessage());
        }

        $this->assertDatabaseHas('whatsapp_otp_challenges', [
            'id' => $challenge->id,
            'attempts' => 3,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.otp_verify_failed',
            'resource_type' => 'whatsapp_otp_challenges',
            'resource_id' => $challenge->id,
        ]);
    }

    public function test_otp_ttl_can_be_overridden_from_system_settings(): void
    {
        $provider = $this->fakeWhatsappProvider();

        SystemSetting::query()->updateOrCreate(
            ['key' => 'otp_ttl_minutes'],
            ['group' => 'security', 'value' => '9', 'type' => 'integer']
        );

        $user = $this->makeBuyer();
        $challenge = app(WhatsappOtpService::class)->issue($user, 'login');

        $this->assertCount(1, $provider->messages);
        $this->assertTrue($challenge->expires_at->between(now()->addMinutes(8), now()->addMinutes(10)));
    }

    private function fakeWhatsappProvider(): object
    {
        $provider = new class implements WhatsappMessageProvider {
            public array $messages = [];

            public function name(): string
            {
                return 'fake';
            }

            public function sendMessage(string $phone, string $message): WhatsappSendResult
            {
                $this->messages[] = compact('phone', 'message');

                return new WhatsappSendResult('fake', 'fake-ref-' . count($this->messages));
            }
        };

        app()->instance(WhatsappMessageProvider::class, $provider);

        return $provider;
    }

    private function makeBuyer(): User
    {
        return User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
        ]);
    }
}
