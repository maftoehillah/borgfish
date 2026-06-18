<?php

namespace Tests\Feature\Auth;

use App\Models\Ikan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BypassOnboardingBidTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_bid_when_bypass_onboarding_is_enabled(): void
    {
        config(['app.bypass_onboarding' => true]);

        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
            'user_status' => 'active',
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
            'whatsapp_number' => null,
            'whatsapp_verified_at' => null,
            'onboarding_completed_at' => null,
            'user_status' => 'active',
        ]);

        $lot = Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Uji Bypass Bid',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk uji bypass bid.',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100000,
            'harga_tertinggi' => 100000,
            'minimal_increment' => 5000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => false,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addHour(),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'state_version' => 1,
        ]);

        $response = $this->actingAs($buyer)
            ->post(route('bid.store', $lot), [
                'jumlah_bid' => 105000,
                'return_url' => route('ikans.show', $lot),
            ]);

        $response->assertRedirect(route('ikans.show', $lot));

        $this->assertDatabaseHas('bids', [
            'ikan_id' => $lot->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 105000,
        ]);
    }
}
