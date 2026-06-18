<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\NotificationOutbox;
use App\Models\User;
use App\Models\WhatsappOtpChallenge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserDataResetService
{
    /**
     * @return array{
     *     seller_lots_deleted: int,
     *     seller_bids_deleted: int,
     *     seller_transactions_deleted: int,
     *     seller_settlements_deleted: int,
     *     in_app_notifications_deleted: int,
     *     notification_outbox_deleted: int,
     *     otp_challenges_deleted: int,
     *     public_files_deleted: int
     * }
     */
    public function reset(User $user, bool $includeSellerLots = false): array
    {
        return DB::transaction(function () use ($user, $includeSellerLots): array {
            $user->loadMissing(['ikans.transaksi.sellerSettlement']);

            $publicFilePaths = collect();

            $sellerLotsDeleted = 0;
            $sellerBidsDeleted = 0;
            $sellerTransactionsDeleted = 0;
            $sellerSettlementsDeleted = 0;
            $inAppNotificationsDeleted = 0;
            $notificationOutboxDeleted = 0;
            $otpChallengesDeleted = 0;

            if ($includeSellerLots && $user->isPenjual()) {
                $lotData = $this->collectSellerLotData($user);

                $publicFilePaths = $publicFilePaths->merge($lotData['public_file_paths']);
                $sellerLotsDeleted = $lotData['lots_deleted'];
                $sellerBidsDeleted = $lotData['bids_deleted'];
                $sellerTransactionsDeleted = $lotData['transactions_deleted'];
                $sellerSettlementsDeleted = $lotData['settlements_deleted'];
            }

            $inAppNotificationsDeleted = InAppNotification::query()
                ->where('user_id', $user->id)
                ->delete();

            $notificationOutboxDeleted = NotificationOutbox::query()
                ->where('recipient_user_id', $user->id)
                ->delete();

            $otpChallengesDeleted = WhatsappOtpChallenge::query()
                ->where('user_id', $user->id)
                ->delete();

            $publicFilePaths = $publicFilePaths
                ->filter(fn (?string $path): bool => filled($path))
                ->unique()
                ->values()
                ->all();

            if ($publicFilePaths !== []) {
                DB::afterCommit(function () use ($publicFilePaths): void {
                    Storage::disk('public')->delete($publicFilePaths);
                });
            }

            return [
                'seller_lots_deleted' => $sellerLotsDeleted,
                'seller_bids_deleted' => $sellerBidsDeleted,
                'seller_transactions_deleted' => $sellerTransactionsDeleted,
                'seller_settlements_deleted' => $sellerSettlementsDeleted,
                'in_app_notifications_deleted' => (int) $inAppNotificationsDeleted,
                'notification_outbox_deleted' => (int) $notificationOutboxDeleted,
                'otp_challenges_deleted' => (int) $otpChallengesDeleted,
                'public_files_deleted' => count($publicFilePaths),
            ];
        });
    }

    /**
     * @return array{
     *     public_file_paths: Collection<int, string>,
     *     lots_deleted: int,
     *     bids_deleted: int,
     *     transactions_deleted: int,
     *     settlements_deleted: int
     * }
     */
    private function collectSellerLotData(User $user): array
    {
        $lots = $user->ikans()
            ->with(['transaksi.sellerSettlement'])
            ->get();

        $lotIds = $lots->pluck('id')->filter()->values();
        $transactionIds = $lots
            ->pluck('transaksi.id')
            ->filter()
            ->values();

        $publicFilePaths = $lots
            ->flatMap(function ($lot): array {
                $transaksi = $lot->transaksi;
                $settlement = $transaksi?->sellerSettlement;

                return array_values(array_filter([
                    $lot->foto,
                    $lot->video,
                    $transaksi?->packing_proof,
                    $transaksi?->buyer_pickup_photo,
                    $transaksi?->buyer_pickup_vehicle_photo,
                    $transaksi?->seller_pickup_driver_photo,
                    $transaksi?->seller_pickup_vehicle_photo,
                    $settlement?->transfer_proof_path,
                ]));
            })
            ->values();

        $bidsDeleted = $lotIds->isEmpty()
            ? 0
            : (int) DB::table('bids')->whereIn('ikan_id', $lotIds)->count();

        $transactionsDeleted = $lotIds->isEmpty()
            ? 0
            : (int) DB::table('transaksis')->whereIn('ikan_id', $lotIds)->count();

        $settlementsDeleted = $transactionIds->isEmpty()
            ? 0
            : (int) DB::table('seller_settlements')->whereIn('transaksi_id', $transactionIds)->count();

        $lotsDeleted = (int) $lots->count();

        if (! $lotIds->isEmpty()) {
            DB::table('ikans')->whereIn('id', $lotIds)->delete();
        }

        return [
            'public_file_paths' => $publicFilePaths,
            'lots_deleted' => $lotsDeleted,
            'bids_deleted' => $bidsDeleted,
            'transactions_deleted' => $transactionsDeleted,
            'settlements_deleted' => $settlementsDeleted,
        ];
    }
}
