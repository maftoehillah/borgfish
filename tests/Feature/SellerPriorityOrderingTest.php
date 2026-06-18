<?php

namespace Tests\Feature;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPriorityOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_packing_priority_orders_oldest_payment_first(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $lotOldest = $this->makeLot($seller, [
            'nama_ikan' => 'Priority Lot Oldest',
            'tipe_lelang' => 'naik',
        ]);

        $lotMiddle = $this->makeLot($seller, [
            'nama_ikan' => 'Priority Lot Middle',
            'tipe_lelang' => 'naik',
        ]);

        $lotNewest = $this->makeLot($seller, [
            'nama_ikan' => 'Priority Lot Newest',
            'tipe_lelang' => 'naik',
        ]);

        $this->makePackingTransaction($lotNewest, $buyer, now()->subHours(2));
        $this->makePackingTransaction($lotOldest, $buyer, now()->subHours(12));
        $this->makePackingTransaction($lotMiddle, $buyer, now()->subHours(6));

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('penjual.ikans.index', [
                'tipe_lelang' => 'naik',
            ]));

        $response->assertOk();

        $content = $response->getContent();
        $sectionStart = strpos($content, 'Siapkan Packing');
        $sectionEnd = strpos($content, 'Penjemputan');

        $this->assertNotFalse($sectionStart, 'Bagian prioritas packing tidak ditemukan di halaman dashboard.');
        $this->assertNotFalse($sectionEnd, 'Batas akhir bagian prioritas packing tidak ditemukan di halaman dashboard.');

        $section = substr($content, $sectionStart, $sectionEnd - $sectionStart);

        $oldestPos = strpos($section, 'Priority Lot Oldest');
        $middlePos = strpos($section, 'Priority Lot Middle');
        $newestPos = strpos($section, 'Priority Lot Newest');

        $this->assertNotFalse($oldestPos, 'Lot tertua tidak muncul pada prioritas packing.');
        $this->assertNotFalse($middlePos, 'Lot menengah tidak muncul pada prioritas packing.');
        $this->assertNotFalse($newestPos, 'Lot terbaru tidak muncul pada prioritas packing.');

        $this->assertTrue($oldestPos < $middlePos, 'Urutan lot tertua dan menengah tidak sesuai.');
        $this->assertTrue($middlePos < $newestPos, 'Urutan lot menengah dan terbaru tidak sesuai.');
    }

    public function test_seller_pickup_priority_orders_oldest_packed_first(): void
    {
        $seller = $this->makeUser('penjual');
        $buyer = $this->makeUser('pembeli');

        $lotOldest = $this->makeLot($seller, [
            'nama_ikan' => 'Pickup Lot Oldest',
            'tipe_lelang' => 'naik',
        ]);

        $lotMiddle = $this->makeLot($seller, [
            'nama_ikan' => 'Pickup Lot Middle',
            'tipe_lelang' => 'naik',
        ]);

        $lotNewest = $this->makeLot($seller, [
            'nama_ikan' => 'Pickup Lot Newest',
            'tipe_lelang' => 'naik',
        ]);

        $this->makeReadyForPickupTransaction($lotNewest, $buyer, now()->subHours(2));
        $this->makeReadyForPickupTransaction($lotOldest, $buyer, now()->subHours(12));
        $this->makeReadyForPickupTransaction($lotMiddle, $buyer, now()->subHours(6));

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('penjual.ikans.index', [
                'tipe_lelang' => 'naik',
            ]));

        $response->assertOk();

        $content = $response->getContent();
        $sectionStart = strpos($content, 'Penjemputan');
        $sectionEnd = strpos($content, 'Selesai');

        $this->assertNotFalse($sectionStart, 'Bagian prioritas penjemputan tidak ditemukan di halaman dashboard.');
        $this->assertNotFalse($sectionEnd, 'Batas akhir bagian prioritas penjemputan tidak ditemukan di halaman dashboard.');

        $section = substr($content, $sectionStart, $sectionEnd - $sectionStart);

        $oldestPos = strpos($section, 'Pickup Lot Oldest');
        $middlePos = strpos($section, 'Pickup Lot Middle');
        $newestPos = strpos($section, 'Pickup Lot Newest');

        $this->assertNotFalse($oldestPos, 'Lot penjemputan packed tertua tidak muncul pada prioritas penjemputan.');
        $this->assertNotFalse($middlePos, 'Lot penjemputan packed menengah tidak muncul pada prioritas penjemputan.');
        $this->assertNotFalse($newestPos, 'Lot penjemputan packed terbaru tidak muncul pada prioritas penjemputan.');

        $this->assertTrue($oldestPos < $middlePos, 'Urutan lot penjemputan packed tertua dan menengah tidak sesuai.');
        $this->assertTrue($middlePos < $newestPos, 'Urutan lot penjemputan packed menengah dan terbaru tidak sesuai.');
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => false,
        ]);
    }

    private function makeLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Uji Prioritas',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test urutan prioritas penjemputan',
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
            'state_version' => 1,
        ], $overrides));
    }

    private function makePackingTransaction(Ikan $ikan, User $buyer, \DateTimeInterface $dibayarPada): Transaksi
    {
        return Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 95_000,
            'status' => 'lunas',
            'pickup_status' => 'awaiting_pickup',
            'dibayar_pada' => $dibayarPada,
        ]);
    }

    private function makeReadyForPickupTransaction(Ikan $ikan, User $buyer, \DateTimeInterface $packedAt): Transaksi
    {
        return Transaksi::create([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 95_000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subDay(),
            'pickup_status' => 'awaiting_pickup',
            'packed_at' => $packedAt,
            'buyer_pickup_name' => 'Pak Rudi',
            'buyer_pickup_plate_number' => 'B 1234 TEST',
            'buyer_pickup_submitted_at' => $packedAt,
        ]);
    }
}
