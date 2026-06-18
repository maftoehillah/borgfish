<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_read_marks_single_notification_only(): void
    {
        $user = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $first = InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'penjemputan',
            'title' => 'Penjemput datang',
            'message' => 'Penjemput sudah divalidasi penjual.',
            'payload' => ['transaksi_id' => 1],
            'read_at' => null,
        ]);

        $second = InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'sengketa',
            'title' => 'Sengketa dibuka',
            'message' => 'Sengketa Anda sedang ditinjau admin.',
            'payload' => ['transaksi_id' => 2],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['otp_verified_user_id' => $user->id])
            ->post(route('notifications.read', $first), [
                'return_url' => route('ikans.index'),
            ]);

        $response->assertRedirect(route('ikans.index'));
        $response->assertSessionHas('sukses');

        $this->assertDatabaseMissing('in_app_notifications', [
            'id' => $first->id,
            'read_at' => null,
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'id' => $second->id,
            'read_at' => null,
        ]);
    }

    public function test_mark_all_read_marks_all_unread_notifications(): void
    {
        $user = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'pesanan',
            'title' => 'Pesanan baru dibayar',
            'message' => 'Segera proses pesanan.',
            'payload' => ['transaksi_id' => 11],
            'read_at' => null,
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'operasional',
            'title' => 'Transaksi bermasalah',
            'message' => 'Perlu tindakan segera.',
            'payload' => ['transaksi_id' => 12],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['otp_verified_user_id' => $user->id])
            ->post(route('notifications.read_all'), [
                'return_url' => route('ikans.index'),
            ]);

        $response->assertRedirect(route('ikans.index'));
        $response->assertSessionHas('sukses');

        $unreadCount = InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $this->assertSame(0, $unreadCount);
    }

    public function test_layout_shows_unread_count_data_attribute(): void
    {
        $user = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'pembayaran',
            'title' => 'Pembayaran berhasil',
            'message' => 'Pembayaran Anda telah dikonfirmasi.',
            'payload' => ['transaksi_id' => 21],
            'read_at' => null,
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'penjemputan',
            'title' => 'Penjemput datang',
            'message' => 'Penjemput sudah divalidasi penjual.',
            'payload' => ['transaksi_id' => 22],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['otp_verified_user_id' => $user->id])
            ->get(route('ikans.index'));

        $response->assertOk();
        $response->assertSee('data-unread-count="2"', false);
    }

    public function test_notification_center_supports_unread_filter(): void
    {
        $user = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'penjemputan',
            'title' => 'Notif Unread A',
            'message' => 'Pesan unread A',
            'payload' => ['transaksi_id' => 1],
            'read_at' => null,
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'sengketa',
            'title' => 'Notif Unread B',
            'message' => 'Pesan unread B',
            'payload' => ['transaksi_id' => 2],
            'read_at' => null,
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'category' => 'pembayaran',
            'title' => 'Notif Sudah Dibaca',
            'message' => 'Pesan read',
            'payload' => ['transaksi_id' => 3],
            'read_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['otp_verified_user_id' => $user->id])
            ->get(route('notifications.index', ['filter' => 'unread']));

        $response->assertOk();
        $response->assertViewHas('notifications', function ($notifications): bool {
            $titles = collect($notifications->items())->pluck('title')->all();

            return in_array('Notif Unread A', $titles, true)
                && in_array('Notif Unread B', $titles, true)
                && ! in_array('Notif Sudah Dibaca', $titles, true);
        });
    }

    public function test_notification_center_only_shows_authenticated_user_notifications(): void
    {
        $firstUser = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $secondUser = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        InAppNotification::create([
            'user_id' => $firstUser->id,
            'category' => 'operasional',
            'title' => 'Notif User Pertama',
            'message' => 'Muncul hanya untuk user pertama.',
            'payload' => ['transaksi_id' => 10],
            'read_at' => null,
        ]);

        InAppNotification::create([
            'user_id' => $secondUser->id,
            'category' => 'operasional',
            'title' => 'Notif User Kedua',
            'message' => 'Tidak boleh terlihat user pertama.',
            'payload' => ['transaksi_id' => 11],
            'read_at' => null,
        ]);

        $response = $this->actingAs($firstUser)
            ->withSession(['otp_verified_user_id' => $firstUser->id])
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Notif User Pertama');
        $response->assertDontSee('Notif User Kedua');
    }

    public function test_open_notification_redirects_to_related_page_and_marks_read(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $ikan = $this->makeActiveLot($seller);
        $transaksi = $this->makeTransactionForNotification($ikan, $buyer, [
            'status' => 'menunggu_bayar',
            'dibayar_pada' => null,
            'pickup_status' => 'waiting_payment',
        ]);

        $notification = InAppNotification::create([
            'user_id' => $buyer->id,
            'category' => 'pembayaran',
            'title' => 'Segera lakukan pembayaran',
            'message' => 'Pesanan Anda menunggu pembayaran.',
            'payload' => ['transaksi_id' => $transaksi->id],
            'read_at' => null,
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->get(route('notifications.open', $notification));

        $expected = route('pembayaran.show', [
            'transaksi' => $transaksi,
            'return_url' => route('notifications.index'),
        ]);

        $response->assertRedirect($expected);

        $this->assertDatabaseMissing('in_app_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_open_notification_redirects_seller_to_seller_lot_detail(): void
    {
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $ikan = $this->makeActiveLot($seller);
        $transaksi = $this->makeTransactionForNotification($ikan, $buyer, [
            'status' => 'lunas',
            'pickup_status' => 'awaiting_pickup',
        ]);

        $notification = InAppNotification::create([
            'user_id' => $seller->id,
            'category' => 'pesanan',
            'title' => 'Pesanan perlu diproses',
            'message' => 'Buka detail lot untuk proses pesanan.',
            'payload' => ['transaksi_id' => $transaksi->id],
            'read_at' => null,
        ]);

        $response = $this->actingAs($seller)
            ->withSession(['otp_verified_user_id' => $seller->id])
            ->get(route('notifications.open', $notification));

        $expected = route('penjual.ikans.show', [
            'ikan' => $ikan,
            'return_url' => route('notifications.index'),
        ]);

        $response->assertRedirect($expected);

        $this->assertDatabaseMissing('in_app_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_open_notification_redirects_admin_to_admin_related_page(): void
    {
        $admin = User::factory()->create([
            'email' => 'sabiqmaftu@gmail.com',
            'role' => 'penjual',
            'is_admin' => true,
        ]);

        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
        ]);

        $ikan = $this->makeActiveLot($seller);
        $transaksi = $this->makeTransactionForNotification($ikan, $buyer);

        $notification = InAppNotification::create([
            'user_id' => $admin->id,
            'category' => 'operasional',
            'title' => 'Transaksi perlu ditinjau',
            'message' => 'Periksa transaksi terkait di panel admin.',
            'payload' => ['transaksi_id' => $transaksi->id],
            'read_at' => null,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['otp_verified_user_id' => $admin->id])
            ->get(route('notifications.open', $notification));

        $response->assertRedirect(url('/admin/transaksis/' . $transaksi->id));

        $this->assertDatabaseMissing('in_app_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    private function makeActiveLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Kakap Uji',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 25,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test notifikasi',
            'tipe_lelang' => 'naik',
            'harga_awal' => 200_000,
            'harga_tertinggi' => 200_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(20),
            'waktu_selesai' => now()->addMinutes(40),
            'status' => 'aktif',
            'state_version' => 1,
        ], $overrides));
    }

    private function makeTransactionForNotification(Ikan $ikan, User $buyer, array $overrides = []): Transaksi
    {
        return Transaksi::create(array_merge([
            'ikan_id' => $ikan->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 220_000,
            'status' => 'lunas',
            'bayar_sebelum' => now()->addHours(24),
            'dibayar_pada' => now()->subMinutes(15),
            'pickup_status' => 'awaiting_pickup',
        ], $overrides));
    }
}
