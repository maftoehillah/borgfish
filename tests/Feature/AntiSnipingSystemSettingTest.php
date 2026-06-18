<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AntiSnipingSystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_lot_uses_admin_default_anti_sniping_values(): void
    {
        Storage::fake('public');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'anti_sniping_enabled_default'],
            ['group' => 'auction', 'value' => '1', 'type' => 'boolean'],
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'anti_sniping_window_seconds'],
            ['group' => 'auction', 'value' => '45', 'type' => 'integer'],
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'anti_sniping_extend_seconds'],
            ['group' => 'auction', 'value' => '75', 'type' => 'integer'],
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'anti_sniping_max_extensions'],
            ['group' => 'auction', 'value' => '2', 'type' => 'integer'],
        );

        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->post(route('penjual.ikans.store'), [
                'nama_ikan' => 'Lot Anti Sniping Setting',
                'berat' => 12,
                'estimasi_jumlah_ekor' => 24,
                'jenis_kemasan' => 'keranjang',
                'kondisi' => 'segar',
                'deskripsi' => 'Lot untuk test anti-sniping dari system settings.',
                'tipe_lelang' => 'naik',
                'harga_awal' => 100000,
                'minimal_increment' => 5000,
                'buy_now_enabled' => 0,
                'anti_sniping_enabled' => 1,
                'mulai_sekarang' => 1,
                'waktu_selesai' => now()->addHours(2)->format('Y-m-d H:i:s'),
                'foto' => UploadedFile::fake()->image('ikan.jpg'),
            ]);

        $response->assertRedirect(route('penjual.ikans.index'));

        $ikan = Ikan::query()->where('nama_ikan', 'Lot Anti Sniping Setting')->firstOrFail();

        $this->assertTrue((bool) $ikan->anti_sniping_enabled);
        $this->assertSame(45, (int) $ikan->anti_sniping_window_seconds);
        $this->assertSame(75, (int) $ikan->anti_sniping_extend_seconds);
        $this->assertSame(2, (int) $ikan->anti_sniping_max_extensions);
    }

    public function test_seller_lot_rejects_video_longer_than_thirty_seconds(): void
    {
        Storage::fake('public');

        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->from(route('penjual.ikans.create'))
            ->post(route('penjual.ikans.store'), [
                'nama_ikan' => 'Lot Video Panjang',
                'berat' => 8,
                'estimasi_jumlah_ekor' => 10,
                'jenis_kemasan' => 'keranjang',
                'kondisi' => 'segar',
                'tipe_lelang' => 'naik',
                'harga_awal' => 120000,
                'minimal_increment' => 5000,
                'buy_now_enabled' => 0,
                'anti_sniping_enabled' => 1,
                'mulai_sekarang' => 1,
                'waktu_selesai' => now()->addHour()->format('Y-m-d H:i:s'),
                'foto' => UploadedFile::fake()->image('ikan.jpg'),
                'video' => UploadedFile::fake()->create('ikan.mp4', 512, 'video/mp4'),
                'video_duration_seconds' => 31,
            ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringStartsWith(route('penjual.ikans.create'), (string) $response->headers->get('Location'));
        $response->assertInvalid([
            'video_duration_seconds' => 'Durasi video maksimal 30 detik.',
        ]);

        $this->assertDatabaseMissing('ikans', [
            'nama_ikan' => 'Lot Video Panjang',
        ]);
    }
}
