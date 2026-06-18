<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerFulfillmentPipelineDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_activity_displays_three_fulfillment_pipeline_boxes(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $lotMenungguBayar = $this->makeLot($seller, 'Lot Menunggu Bayar');
        $lotPenjemputan = $this->makeLot($seller, 'Lot Penjemputan');
        $lotSelesai = $this->makeLot($seller, 'Lot Selesai');

        $this->makeBid($lotMenungguBayar, $buyer, 120_000);
        $this->makeBid($lotPenjemputan, $buyer, 130_000);
        $this->makeBid($lotSelesai, $buyer, 140_000);

        Transaksi::create([
            'ikan_id' => $lotMenungguBayar->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 120_000,
            'status' => 'menunggu_bayar',
            'bayar_sebelum' => now()->addHours(6),
            'pickup_status' => 'waiting_payment',
        ]);

        Transaksi::create([
            'ikan_id' => $lotPenjemputan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 130_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(6),
            'dibayar_pada' => now()->subHours(4),
            'pickup_status' => 'awaiting_pickup',
            'packed_at' => now()->subHours(3),
            'buyer_pickup_name' => 'Pak Rudi',
            'buyer_pickup_plate_number' => 'B 1234 TEST',
            'buyer_pickup_submitted_at' => now()->subHours(2),
        ]);

        Transaksi::create([
            'ikan_id' => $lotSelesai->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 140_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(6),
            'dibayar_pada' => now()->subHours(5),
            'pickup_status' => 'pickup_arrived',
            'packed_at' => now()->subHours(4),
            'pickup_verified_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas'));

        $response->assertOk();
        $response->assertSee('1. Bayar');
        $response->assertSee('2. Penjemputan');
        $response->assertSee('3. Selesai');
        $response->assertSee('Lot Menunggu Bayar');
        $response->assertSee('Lot Penjemputan');
        $response->assertSee('Lot Selesai');
        $response->assertSee('B 1234 TEST');
        $response->assertSee('Beri Penilaian');
        $response->assertSee(route('pembeli.aktivitas.penilaian', ['ikan' => $lotSelesai]), false);
    }

    public function test_buyer_activity_detail_displays_pickup_information(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $lotPenjemputan = $this->makeLot($seller, 'Lot Penjemputan Detail');
        $this->makeBid($lotPenjemputan, $buyer, 150_000);

        Transaksi::create([
            'ikan_id' => $lotPenjemputan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 150_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(6),
            'dibayar_pada' => now()->subHours(5),
            'pickup_status' => 'pickup_arrived',
            'packed_at' => now()->subHours(4),
            'packing_proof' => 'delivery-proof/packing-detail.jpg',
            'buyer_pickup_name' => 'Pak Rudi',
            'buyer_pickup_plate_number' => 'B 5678 TEST',
            'buyer_pickup_photo' => 'pickup-proof/buyer-detail.jpg',
            'buyer_pickup_vehicle_photo' => 'pickup-proof/buyer-vehicle-detail.jpg',
            'buyer_pickup_submitted_at' => now()->subHours(3),
            'seller_pickup_driver_photo' => 'pickup-proof/driver-detail.jpg',
            'seller_pickup_vehicle_photo' => 'pickup-proof/vehicle-detail.jpg',
            'seller_pickup_recorded_at' => now()->subHours(2),
            'pickup_verified_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas.detail', [
                'ikan' => $lotPenjemputan,
            ]));

        $response->assertOk();
        $response->assertSee('Detail Penjemputan');
        $response->assertSee('Dalam Penjemputan');
        $response->assertSee('Sopir dari pembeli');
        $response->assertSee('Pak Rudi');
        $response->assertSee('Plat dari pembeli');
        $response->assertSee('B 5678 TEST');
        $response->assertSee('Foto Packing & Penjemputan');
        $response->assertSee('Bukti Packing');
        $response->assertSee('Pemenang Ditentukan');
        $response->assertSee('Pembayaran Berhasil');
        $response->assertSee('Data Penjemput Dikirim');
        $response->assertSee('Foto Sopir dari Pembeli');
        $response->assertSee('Foto Kendaraan dari Pembeli');
        $response->assertSee('Foto Sopir Validasi Penjual');
    }

    public function test_buyer_finished_pipeline_opens_review_page_directly(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $lotSelesai = $this->makeLot($seller, 'Lot Siap Dinilai');
        $this->makeBid($lotSelesai, $buyer, 175_000);

        Transaksi::create([
            'ikan_id' => $lotSelesai->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 175_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(6),
            'dibayar_pada' => now()->subHours(5),
            'pickup_status' => 'pickup_arrived',
            'packed_at' => now()->subHours(4),
            'buyer_pickup_name' => 'Pak Rudi',
            'buyer_pickup_plate_number' => 'B 9012 TEST',
            'buyer_pickup_submitted_at' => now()->subHours(3),
            'seller_pickup_recorded_at' => now()->subHours(2),
            'pickup_verified_at' => now()->subHours(2),
        ]);

        $activityResponse = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas'));

        $activityResponse->assertOk();
        $activityResponse->assertSee('Beri Penilaian');
        $activityResponse->assertSee(route('pembeli.aktivitas.penilaian', ['ikan' => $lotSelesai]), false);

        $reviewResponse = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas.penilaian', ['ikan' => $lotSelesai]));

        $reviewResponse->assertOk();
        $reviewResponse->assertSee('Penilaian Transaksi');
        $reviewResponse->assertSee('Review dan Konfirmasi Selesai');
        $reviewResponse->assertSee('Konfirmasi Selesai');
        $reviewResponse->assertDontSee('Detail Aktivitas Bid');
    }

    public function test_buyer_pickup_submission_requires_driver_and_vehicle_photos(): void
    {
        Storage::fake('public');

        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $lot = $this->makeLot($seller, 'Lot Wajib Foto Sopir');
        $this->makeBid($lot, $buyer, 165_000);

        $transaksi = Transaksi::create([
            'ikan_id' => $lot->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 165_000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'bayar_sebelum' => now()->addHours(6),
            'dibayar_pada' => now()->subHour(),
            'pickup_status' => 'packing',
            'packed_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->from(route('pembeli.aktivitas.detail', $lot))
            ->post(route('pembeli.ikans.pickup', $lot), [
                'buyer_pickup_name' => 'Pak Sopir',
                'buyer_pickup_plate_number' => 'B 4567 TEST',
            ])
            ->assertSessionHasErrors(['buyer_pickup_photo', 'buyer_pickup_vehicle_photo'])
            ->assertRedirect(route('pembeli.aktivitas.detail', $lot));

        $this->assertNull($transaksi->fresh()->buyer_pickup_submitted_at);

        $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->post(route('pembeli.ikans.pickup', $lot), [
                'buyer_pickup_name' => 'Pak Sopir',
                'buyer_pickup_plate_number' => 'B 4567 TEST',
                'buyer_pickup_photo' => UploadedFile::fake()->image('foto-sopir.jpg'),
                'buyer_pickup_vehicle_photo' => UploadedFile::fake()->image('foto-kendaraan.jpg'),
                'buyer_pickup_notes' => 'Penjemput dari pembeli.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pembeli.aktivitas.detail', $lot));

        $transaksi->refresh();

        $this->assertSame('Pak Sopir', $transaksi->buyer_pickup_name);
        $this->assertSame('B 4567 TEST', $transaksi->buyer_pickup_plate_number);
        $this->assertNotNull($transaksi->buyer_pickup_submitted_at);
        $this->assertNotNull($transaksi->buyer_pickup_photo);
        $this->assertNotNull($transaksi->buyer_pickup_vehicle_photo);
        Storage::disk('public')->assertExists($transaksi->buyer_pickup_photo);
        Storage::disk('public')->assertExists($transaksi->buyer_pickup_vehicle_photo);

        $detailResponse = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas.detail', $lot));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Data penjemput sudah tersimpan');
        $detailResponse->assertDontSee('Penjemput Dalam Perjalanan');
    }

    public function test_buyer_cannot_fill_pickup_data_before_seller_confirms_packing(): void
    {
        Storage::fake('public');

        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $lot = $this->makeLot($seller, 'Lot Belum Dipacking');
        $this->makeBid($lot, $buyer, 170_000);

        $transaksi = Transaksi::create([
            'ikan_id' => $lot->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 170_000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'bayar_sebelum' => now()->addHours(6),
            'dibayar_pada' => now()->subHour(),
            'pickup_status' => 'awaiting_buyer_pickup',
        ]);

        $detailResponse = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('pembeli.aktivitas.detail', $lot));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Menunggu Konfirmasi Packing');
        $detailResponse->assertDontSee('Simpan Data Penjemput');
        $detailResponse->assertDontSee('Foto Sopir Penjemput');
        $detailResponse->assertDontSee('Foto Kendaraan Penjemput');

        $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->from(route('pembeli.aktivitas.detail', $lot))
            ->post(route('pembeli.ikans.pickup', $lot), [
                'buyer_pickup_name' => 'Pak Sopir',
                'buyer_pickup_plate_number' => 'B 4567 TEST',
                'buyer_pickup_photo' => UploadedFile::fake()->image('foto-sopir.jpg'),
                'buyer_pickup_vehicle_photo' => UploadedFile::fake()->image('foto-kendaraan.jpg'),
            ])
            ->assertSessionHas('error', 'Data penjemput baru bisa diisi setelah penjual mengonfirmasi packing.')
            ->assertRedirect(route('pembeli.aktivitas.detail', $lot));

        $this->assertNull($transaksi->fresh()->buyer_pickup_submitted_at);
    }

    private function makeLot(User $seller, string $name): Ikan
    {
        return Ikan::create([
            'user_id' => $seller->id,
            'nama_ikan' => $name,
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot test pipeline buyer',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'reserve_price' => null,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'payment_deadline_minutes' => 360,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subHours(2),
            'waktu_selesai' => now()->addHours(3),
            'status' => 'aktif',
            'state_version' => 1,
        ]);
    }

    private function makeBid(Ikan $ikan, User $buyer, int $amount): void
    {
        Bid::create([
            'ikan_id' => $ikan->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => $amount,
            'bidder_ip' => '127.0.0.1',
            'bidder_user_agent' => 'phpunit',
        ]);
    }
}
