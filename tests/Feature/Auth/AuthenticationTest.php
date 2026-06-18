<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_post_redirects_to_google_oauth(): void
    {
        $response = $this->post('/login', [
            'role' => 'pembeli',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('auth.google.redirect', ['flow' => 'login'], absolute: false));
    }

    public function test_google_redirect_forces_account_chooser(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('scopes')
            ->once()
            ->with(['openid', 'profile', 'email'])
            ->andReturnSelf();
        $provider->shouldReceive('with')
            ->once()
            ->with(['prompt' => 'select_account'])
            ->andReturnSelf();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/v2/auth?prompt=select_account'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?prompt=select_account');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_whitelisted_google_email_is_registered_as_admin(): void
    {
        config(['marketplace.admin_whitelist' => ['sabiqmaftu@gmail.com']]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(new class {
            public function getEmail(): string
            {
                return 'sabiqmaftu@gmail.com';
            }

            public function getId(): string
            {
                return 'google-admin-001';
            }

            public function getName(): string
            {
                return 'Sabiq Maftu';
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'sabiqmaftu@gmail.com',
            'role' => 'pembeli',
            'is_admin' => true,
        ]);
    }

    public function test_whitelisted_google_email_cannot_be_used_for_buyer_or_seller_registration(): void
    {
        config(['marketplace.admin_whitelist' => ['sabiqmaftu@gmail.com']]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(new class {
            public function getEmail(): string
            {
                return 'sabiqmaftu@gmail.com';
            }

            public function getId(): string
            {
                return 'google-admin-register-001';
            }

            public function getName(): string
            {
                return 'Sabiq Maftu';
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->withSession([
            'auth.flow' => 'register',
            'auth.intended_role' => 'pembeli',
        ])
            ->get(route('auth.google.callback'));

        $response->assertRedirect(route('register'));
        $response->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'sabiqmaftu@gmail.com',
        ]);
    }

    public function test_new_non_admin_google_login_without_selected_role_returns_to_register(): void
    {
        config(['marketplace.admin_whitelist' => ['sabiqmaftu@gmail.com']]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(new class {
            public function getEmail(): string
            {
                return 'buyer@example.test';
            }

            public function getId(): string
            {
                return 'google-buyer-001';
            }

            public function getName(): string
            {
                return 'Buyer Test';
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('register'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'buyer@example.test',
        ]);
    }

    public function test_incomplete_google_registration_can_rechoose_role_before_activation(): void
    {
        config(['marketplace.admin_whitelist' => ['sabiqmaftu@gmail.com']]);

        $user = User::factory()->create([
            'email' => 'pending-role@example.test',
            'google_id' => 'google-pending-role-001',
            'role' => 'penjual',
            'is_admin' => false,
            'whatsapp_number' => null,
            'whatsapp_verified_at' => null,
            'onboarding_completed_at' => null,
        ]);

        $this->assertTrue($user->sellerProfile()->exists());

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(new class {
            public function getEmail(): string
            {
                return 'pending-role@example.test';
            }

            public function getId(): string
            {
                return 'google-pending-role-001';
            }

            public function getName(): string
            {
                return 'Pending Role';
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->withSession([
            'auth.flow' => 'register',
            'auth.intended_role' => 'pembeli',
        ])
            ->get(route('auth.google.callback'));

        $response->assertRedirect(route('auth.onboarding.show'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('pembeli', $user->fresh()->role);
        $this->assertFalse($user->fresh()->sellerProfile()->exists());
    }

    public function test_completed_google_account_cannot_change_role_from_register_flow(): void
    {
        config(['marketplace.admin_whitelist' => ['sabiqmaftu@gmail.com']]);

        $user = User::factory()->create([
            'email' => 'registered-seller@example.test',
            'google_id' => 'google-registered-seller-001',
            'role' => 'penjual',
            'is_admin' => false,
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(new class {
            public function getEmail(): string
            {
                return 'registered-seller@example.test';
            }

            public function getId(): string
            {
                return 'google-registered-seller-001';
            }

            public function getName(): string
            {
                return 'Registered Seller';
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->withSession([
            'auth.flow' => 'register',
            'auth.intended_role' => 'pembeli',
        ])
            ->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
        $this->assertSame('penjual', $user->fresh()->role);
    }

    public function test_existing_verified_google_user_can_login_without_login_otp_challenge(): void
    {
        $user = User::factory()->create([
            'email' => 'verified-buyer@example.test',
            'google_id' => 'google-verified-buyer-001',
            'role' => 'pembeli',
            'is_admin' => false,
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
            'onboarding_completed_at' => now(),
            'user_status' => 'active',
        ]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn(new class {
            public function getEmail(): string
            {
                return 'verified-buyer@example.test';
            }

            public function getId(): string
            {
                return 'google-verified-buyer-001';
            }

            public function getName(): string
            {
                return 'Verified Buyer';
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame($user->id, session('otp_verified_user_id'));
    }
}
