<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\InAppNotification;
use App\Models\Ikan;
use App\Models\NotificationOutbox;
use App\Models\SellerSettlement;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\WhatsappOtpChallenge;
use App\Services\UserDataResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserDataResetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_account_and_registration_data_intact_while_clearing_user_notifications(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('seller-profiles/store-photo.jpg', 'profile-photo');

        $user = User::factory()->create([
            'role' => 'penjual',
            'whatsapp_number' => '6281234567890',
            'whatsapp_verified_at' => now(),
            'last_otp_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $user->sellerProfile()->update([
            'store_name' => 'Toko Laut Segar',
            'store_location' => 'Sidoarjo',
            'full_address' => 'Jl. Ikan No. 1',
            'store_photo_path' => 'seller-profiles/store-photo.jpg',
        ]);

        InAppNotification::query()->create([
            'user_id' => $user->id,
            'category' => 'system',
            'title' => 'Tes notif',
            'message' => 'Notif user',
        ]);

        NotificationOutbox::query()->create([
            'recipient_user_id' => $user->id,
            'recipient_role' => 'penjual',
            'category' => 'system',
            'title' => 'Tes outbox',
            'message' => 'Outbox user',
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        WhatsappOtpChallenge::query()->create([
            'user_id' => $user->id,
            'phone_number' => '6281234567890',
            'purpose' => 'login',
            'otp_hash' => bcrypt('123456'),
            'session_token' => Str::random(32),
            'expires_at' => now()->addMinutes(5),
            'status' => 'pending',
        ]);

        $summary = app(UserDataResetService::class)->reset($user->fresh());

        $this->assertSame(0, $summary['seller_lots_deleted']);
        $this->assertSame(0, $summary['seller_bids_deleted']);
        $this->assertSame(0, $summary['seller_transactions_deleted']);
        $this->assertSame(0, $summary['seller_settlements_deleted']);
        $this->assertSame(1, $summary['in_app_notifications_deleted']);
        $this->assertSame(1, $summary['notification_outbox_deleted']);
        $this->assertSame(1, $summary['otp_challenges_deleted']);
        $this->assertSame(0, $summary['public_files_deleted']);

        $user->refresh();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => 'penjual',
        ]);
        $this->assertSame('6281234567890', $user->whatsapp_number);
        $this->assertNotNull($user->whatsapp_verified_at);
        $this->assertNotNull($user->last_otp_verified_at);
        $this->assertNotNull($user->onboarding_completed_at);

        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('notification_outbox', [
            'recipient_user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('whatsapp_otp_challenges', [
            'user_id' => $user->id,
        ]);

        Storage::disk('public')->assertExists('seller-profiles/store-photo.jpg');
    }

    public function test_it_can_also_delete_seller_lots_and_related_marketplace_data(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('seller-profiles/store-photo.jpg', 'profile-photo');
        Storage::disk('public')->put('ikans/lot-photo.jpg', 'lot-photo');
        Storage::disk('public')->put('ikans-videos/lot-video.mp4', 'lot-video');
        Storage::disk('public')->put('delivery-proof/packing-proof.jpg', 'packing-proof');
        Storage::disk('public')->put('pickup-proof/buyer-driver.jpg', 'buyer-driver');
        Storage::disk('public')->put('pickup-proof/buyer-vehicle.jpg', 'buyer-vehicle');
        Storage::disk('public')->put('pickup-proof/seller-driver.jpg', 'seller-driver');
        Storage::disk('public')->put('pickup-proof/seller-vehicle.jpg', 'seller-vehicle');
        Storage::disk('public')->put('settlement-proof/transfer-proof.jpg', 'transfer-proof');

        $seller = User::factory()->create([
            'role' => 'penjual',
            'whatsapp_number' => '6281234567891',
            'whatsapp_verified_at' => now(),
            'last_otp_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $seller->sellerProfile()->update([
            'store_name' => 'Toko Laut Segar',
            'store_location' => 'Sidoarjo',
            'full_address' => 'Jl. Ikan No. 1',
            'store_photo_path' => 'seller-profiles/store-photo.jpg',
        ]);

        InAppNotification::query()->create([
            'user_id' => $seller->id,
            'category' => 'system',
            'title' => 'Tes notif',
            'message' => 'Notif user',
        ]);

        NotificationOutbox::query()->create([
            'recipient_user_id' => $seller->id,
            'recipient_role' => 'penjual',
            'category' => 'system',
            'title' => 'Tes outbox',
            'message' => 'Outbox user',
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        WhatsappOtpChallenge::query()->create([
            'user_id' => $seller->id,
            'phone_number' => '6281234567891',
            'purpose' => 'login',
            'otp_hash' => bcrypt('123456'),
            'session_token' => Str::random(32),
            'expires_at' => now()->addMinutes(5),
            'status' => 'pending',
        ]);

        $buyer = User::factory()->create([
            'role' => 'pembeli',
        ]);

        $lot = Ikan::query()->create([
            'user_id' => $seller->id,
            'nama_ikan' => 'Kakap Merah',
            'berat' => 12.5,
            'estimasi_jumlah_ekor' => 8,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test hapus data penjual.',
            'harga_awal' => 150000,
            'harga_tertinggi' => 175000,
            'minimal_increment' => 5000,
            'buy_now_enabled' => false,
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHour(),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'foto' => 'ikans/lot-photo.jpg',
            'video' => 'ikans-videos/lot-video.mp4',
            'foto_diambil_pada' => now(),
        ]);

        Bid::query()->create([
            'ikan_id' => $lot->id,
            'user_id' => $buyer->id,
            'jumlah_bid' => 175000,
        ]);

        $transaksi = Transaksi::query()->create([
            'ikan_id' => $lot->id,
            'pemenang_id' => $buyer->id,
            'harga_final' => 175000,
            'status' => 'lunas',
            'payment_status' => 'paid',
            'pickup_status' => 'completed',
            'packing_proof' => 'delivery-proof/packing-proof.jpg',
            'buyer_pickup_photo' => 'pickup-proof/buyer-driver.jpg',
            'buyer_pickup_vehicle_photo' => 'pickup-proof/buyer-vehicle.jpg',
            'seller_pickup_driver_photo' => 'pickup-proof/seller-driver.jpg',
            'seller_pickup_vehicle_photo' => 'pickup-proof/seller-vehicle.jpg',
        ]);

        SellerSettlement::query()->create([
            'transaksi_id' => $transaksi->id,
            'seller_id' => $seller->id,
            'amount' => 170000,
            'status' => 'paid',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => $seller->name,
            'transfer_proof_path' => 'settlement-proof/transfer-proof.jpg',
        ]);

        $summary = app(UserDataResetService::class)->reset($seller->fresh(), includeSellerLots: true);

        $this->assertSame(1, $summary['seller_lots_deleted']);
        $this->assertSame(1, $summary['seller_bids_deleted']);
        $this->assertSame(1, $summary['seller_transactions_deleted']);
        $this->assertSame(1, $summary['seller_settlements_deleted']);
        $this->assertSame(1, $summary['in_app_notifications_deleted']);
        $this->assertSame(1, $summary['notification_outbox_deleted']);
        $this->assertSame(1, $summary['otp_challenges_deleted']);
        $this->assertSame(8, $summary['public_files_deleted']);

        $seller->refresh();

        $this->assertDatabaseHas('users', [
            'id' => $seller->id,
            'email' => $seller->email,
            'role' => 'penjual',
        ]);
        $this->assertSame('6281234567891', $seller->whatsapp_number);
        $this->assertNotNull($seller->whatsapp_verified_at);
        $this->assertNotNull($seller->last_otp_verified_at);
        $this->assertNotNull($seller->onboarding_completed_at);
        $this->assertDatabaseMissing('ikans', [
            'id' => $lot->id,
        ]);
        $this->assertDatabaseMissing('bids', [
            'ikan_id' => $lot->id,
        ]);
        $this->assertDatabaseMissing('transaksis', [
            'id' => $transaksi->id,
        ]);
        $this->assertDatabaseMissing('seller_settlements', [
            'transaksi_id' => $transaksi->id,
        ]);
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $seller->id,
        ]);
        $this->assertDatabaseMissing('notification_outbox', [
            'recipient_user_id' => $seller->id,
        ]);
        $this->assertDatabaseMissing('whatsapp_otp_challenges', [
            'user_id' => $seller->id,
        ]);
        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $seller->id,
        ]);

        Storage::disk('public')->assertExists('seller-profiles/store-photo.jpg');
        Storage::disk('public')->assertMissing('ikans/lot-photo.jpg');
        Storage::disk('public')->assertMissing('ikans-videos/lot-video.mp4');
        Storage::disk('public')->assertMissing('delivery-proof/packing-proof.jpg');
        Storage::disk('public')->assertMissing('pickup-proof/buyer-driver.jpg');
        Storage::disk('public')->assertMissing('pickup-proof/buyer-vehicle.jpg');
        Storage::disk('public')->assertMissing('pickup-proof/seller-driver.jpg');
        Storage::disk('public')->assertMissing('pickup-proof/seller-vehicle.jpg');
        Storage::disk('public')->assertMissing('settlement-proof/transfer-proof.jpg');
    }
}
