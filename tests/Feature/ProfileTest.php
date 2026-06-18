<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertDontSee('Update Password');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $originalVerifiedAt = $user->email_verified_at;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'attempted-change@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertEquals($originalVerifiedAt->timestamp, $user->email_verified_at->timestamp);
    }

    public function test_profile_information_can_be_updated_without_email_payload(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_user_can_request_whatsapp_otp_and_disable_their_account(): void
    {
        config([
            'whatsapp.driver' => 'log',
            'whatsapp.show_dev_otp' => true,
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.delete_otp'));

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('account_deletion_otp_token')
            ->assertSessionHas('dev_whatsapp_otp')
            ->assertRedirect('/profile');

        $token = session('account_deletion_otp_token');
        $otp = session('dev_whatsapp_otp');

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'session_token' => $token,
                'otp' => $otp,
                'confirmation' => '1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ikans.index', absolute: false));

        $this->assertGuest();
        $this->assertSame('deleted', $user->fresh()->user_status);
    }

    public function test_correct_whatsapp_otp_must_be_provided_to_disable_account(): void
    {
        config([
            'whatsapp.driver' => 'log',
            'whatsapp.show_dev_otp' => true,
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.delete_otp'));

        $response->assertSessionHasNoErrors();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'session_token' => session('account_deletion_otp_token'),
                'otp' => '000000',
                'confirmation' => '1',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'otp')
            ->assertRedirect('/profile');

        $this->assertSame('active', $user->fresh()->user_status);
    }

    public function test_account_deletion_otp_is_blocked_when_transaction_is_active(): void
    {
        config([
            'whatsapp.driver' => 'log',
            'whatsapp.show_dev_otp' => true,
        ]);

        $seller = User::factory()->create(['role' => 'penjual']);
        $buyer = User::factory()->create(['role' => 'pembeli']);
        $lot = $this->makeLot($seller, ['status' => 'selesai', 'auction_state' => 'MENUNGGU_PEMBAYARAN']);

        Transaksi::create([
            'ikan_id' => $lot->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 125_000,
            'status' => 'menunggu_bayar',
            'payment_status' => 'pending',
            'bayar_sebelum' => now()->addMinutes(30),
            'pickup_status' => 'waiting_payment',
        ]);

        $response = $this
            ->actingAs($buyer)
            ->from('/profile')
            ->post(route('profile.delete_otp'));

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'delete_account')
            ->assertRedirect('/profile');

        $this->assertSame('active', $buyer->fresh()->user_status);
    }

    private function makeLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Profile Deletion',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test hapus akun',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addHours(3),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'state_version' => 1,
        ], $overrides));
    }
}
