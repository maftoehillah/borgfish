<?php

namespace Tests\Feature\Auth;

use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WhatsappOtpChallenge;
use App\Services\Whatsapp\LogWhatsappProvider;
use App\Services\Whatsapp\WhatsappMessageProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_without_whatsapp_is_forced_to_onboarding_even_with_otp_session(): void
    {
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
            'whatsapp_number' => null,
            'whatsapp_verified_at' => null,
            'user_status' => 'active',
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('dashboard'));

        $response->assertRedirect(route('auth.onboarding.show'));
    }

    public function test_logged_in_incomplete_buyer_is_forced_to_onboarding_from_marketplace(): void
    {
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
            'whatsapp_number' => null,
            'whatsapp_verified_at' => null,
            'user_status' => 'active',
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('ikans.index'));

        $response->assertRedirect(route('auth.onboarding.show'));
    }

    public function test_logged_in_verified_user_can_open_marketplace_without_otp_session(): void
    {
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
            'user_status' => 'active',
            'onboarding_completed_at' => now(),
        ]);

        $response = $this->actingAs($buyer)
            ->get(route('ikans.index'));

        $response->assertOk();
    }

    public function test_guest_can_still_open_public_marketplace(): void
    {
        $response = $this->get(route('ikans.index'));

        $response->assertOk();
    }

    public function test_seller_without_store_profile_is_forced_to_onboarding_even_with_otp_session(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
            'user_status' => 'active',
        ]);
        $seller->sellerProfile()->delete();

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('penjual.ikans.index'));

        $response->assertRedirect(route('auth.onboarding.show'));
    }

    public function test_seller_onboarding_requires_gps_and_store_photo(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
            'whatsapp_number' => null,
            'whatsapp_verified_at' => null,
            'user_status' => 'active',
            'onboarding_completed_at' => null,
        ]);
        $seller->sellerProfile()->delete();

        $response = $this->actingAs($seller)
            ->from(route('auth.onboarding.show'))
            ->post(route('auth.onboarding.store'), [
                'whatsapp_number' => '081234567890',
                'store_name' => 'Toko GPS Uji',
                'full_address' => 'Jl. Pasar Ikan No. 10',
            ]);

        $response->assertRedirect(route('auth.onboarding.show'));
        $response->assertSessionHasErrors(['store_latitude', 'store_longitude', 'store_photo']);
    }

    public function test_seller_onboarding_stores_address_gps_and_store_photo(): void
    {
        config([
            'whatsapp.driver' => 'log',
            'whatsapp.show_dev_otp' => true,
        ]);
        $this->app->forgetInstance(WhatsappMessageProvider::class);
        $this->app->instance(WhatsappMessageProvider::class, new LogWhatsappProvider());

        Storage::fake('public');

        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
            'whatsapp_number' => null,
            'whatsapp_verified_at' => null,
            'user_status' => 'active',
            'onboarding_completed_at' => null,
        ]);
        $seller->sellerProfile()->delete();

        $response = $this->actingAs($seller)
            ->post(route('auth.onboarding.store'), [
                'whatsapp_number' => '081234567890',
                'store_name' => 'Toko GPS Uji',
                'full_address' => 'Jl. Pasar Ikan No. 10',
                'store_latitude' => '-6.2000000',
                'store_longitude' => '106.8166667',
                'store_gps_accuracy' => '12.50',
                'store_photo' => UploadedFile::fake()->image('etalase.jpg', 900, 600),
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
                'bank_account_name' => 'Toko GPS Uji',
            ]);

        $challenge = WhatsappOtpChallenge::query()
            ->where('user_id', $seller->id)
            ->where('purpose', 'phone_verification')
            ->latest('id')
            ->first();

        $response->assertRedirect(route('auth.otp.challenge', [
            'token' => $challenge->session_token,
        ]));

        $profile = $seller->fresh()->sellerProfile;

        $this->assertSame('Toko GPS Uji', $profile->store_name);
        $this->assertSame('Jl. Pasar Ikan No. 10', $profile->full_address);
        $this->assertSame('-6.2000000', (string) $profile->store_latitude);
        $this->assertSame('106.8166667', (string) $profile->store_longitude);
        $this->assertSame('BCA', $profile->bank_name);
        $this->assertSame('1234567890', $profile->bank_account_number);
        $this->assertSame('Toko GPS Uji', $profile->bank_account_name);
        $this->assertNotEmpty($profile->store_photo_path);
        Storage::disk('public')->assertExists($profile->store_photo_path);
    }

    public function test_seller_with_required_profile_but_unverified_whatsapp_goes_to_otp(): void
    {
        $seller = $this->makeSellerWithProfile([
            'whatsapp_verified_at' => null,
        ]);

        $response = $this->actingAs($seller)
            ->get(route('dashboard'));

        $response->assertRedirect(route('auth.otp.challenge'));
    }

    public function test_otp_page_redirects_verified_user_back_to_dashboard_for_login_purpose(): void
    {
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
            'user_status' => 'active',
            'onboarding_completed_at' => now(),
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['auth.otp_purpose' => 'login'])
            ->get(route('auth.otp.challenge'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame($buyer->id, session('otp_verified_user_id'));
    }

    public function test_seller_with_required_profile_and_otp_can_enter_seller_dashboard_flow(): void
    {
        $seller = $this->makeSellerWithProfile();

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('dashboard'));

        $response->assertRedirect(route('penjual.dashboard'));
    }

    private function makeSellerWithProfile(array $overrides = []): User
    {
        $seller = User::factory()->create(array_merge([
            'role' => 'penjual',
            'is_admin' => false,
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
            'user_status' => 'active',
            'onboarding_completed_at' => now(),
        ], $overrides));

        SellerProfile::query()->updateOrCreate(
            ['user_id' => $seller->id],
            [
                'store_name' => 'Toko Ikan Uji',
                'store_location' => 'Jakarta',
                'full_address' => 'Jl. Pasar Ikan No. 1',
                'supporting_information' => 'Supplier ikan segar.',
                'store_latitude' => -6.2000000,
                'store_longitude' => 106.8166667,
                'store_gps_accuracy' => 20.00,
                'store_gps_captured_at' => now(),
                'store_photo_path' => 'seller-profiles/testing-store.jpg',
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
                'bank_account_name' => 'Toko Ikan Uji',
            ]
        );

        return $seller;
    }
}
