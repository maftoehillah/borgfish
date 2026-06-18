<?php

namespace App\Services;

use App\Models\AuctionBidHold;
use App\Models\Ikan;
use App\Models\SaldoLedger;
use App\Models\Transaksi;
use App\Models\User;
use DomainException;

class SaldoService
{
    public function ensureBidCoverage(Ikan $ikan, int $userId, float $amount): void
    {
        $context = $this->loadLockedContext($ikan, $userId);
        $user = $context['users'][$userId] ?? null;
        $existingHold = $context['holds']->firstWhere('user_id', $userId);

        if (! $user) {
            throw new DomainException('Akun pembeli tidak valid.');
        }

        $biddingPower = $user->effectiveBiddingPower((float) ($existingHold?->amount ?? 0));
        if ($biddingPower + 0.00001 < $amount) {
            throw new DomainException('Saldo tersedia tidak cukup untuk nominal bid ini.');
        }
    }

    public function syncLeadingHold(Ikan $ikan, int $leaderId, float $targetAmount, string $reason = 'leading_bid'): void
    {
        $context = $this->loadLockedContext($ikan, $leaderId);
        $holds = $context['holds'];
        $users = $context['users'];
        $leader = $users[$leaderId] ?? null;

        if (! $leader) {
            throw new DomainException('Akun pemimpin lelang tidak ditemukan.');
        }

        $leaderHold = $holds->firstWhere('user_id', $leaderId);
        $leaderHold = $leaderHold instanceof AuctionBidHold
            ? $leaderHold
            : new AuctionBidHold([
                'ikan_id' => $ikan->id,
                'user_id' => $leaderId,
            ]);

        $currentAmount = round((float) ($leaderHold->amount ?? 0), 2);
        $targetAmount = round(max(0, $targetAmount), 2);
        $delta = round($targetAmount - $currentAmount, 2);

        if ($delta > 0 && $leader->saldoTersedia() + 0.00001 < $delta) {
            throw new DomainException('Saldo tersedia tidak cukup untuk mengunci dana bid teratas.');
        }

        foreach ($holds as $hold) {
            if ((int) $hold->user_id === $leaderId) {
                continue;
            }

            $previousLeader = $users[(int) $hold->user_id] ?? null;
            if (! $previousLeader) {
                continue;
            }

            $this->releaseHold($hold, $previousLeader, 'auction_hold_released', 'leading_bid_lost');
        }

        if ($delta > 0) {
            $this->applyLedger(
                $leader,
                -$delta,
                $delta,
                'auction_hold_locked',
                $reason,
                $ikan->id,
                'Dana bid teratas ditahan otomatis.'
            );
        } elseif ($delta < 0) {
            $releaseAmount = abs($delta);

            $this->applyLedger(
                $leader,
                $releaseAmount,
                -$releaseAmount,
                'auction_hold_rebalanced',
                $reason,
                $ikan->id,
                'Dana bid teratas disesuaikan kembali.'
            );
        }

        $leaderHold->amount = $targetAmount;
        $leaderHold->status = 'active';
        $leaderHold->reason = $reason;
        $leaderHold->held_at = $leaderHold->held_at ?? now();
        $leaderHold->released_at = null;
        $leaderHold->captured_at = null;
        $leaderHold->release_reason = null;
        $leaderHold->transaksi_id = null;
        $leaderHold->save();
    }

    public function captureWinningFunds(Ikan $ikan, int $userId, float $amount, ?Transaksi $transaksi = null, string $reason = 'auction_won'): bool
    {
        $targetAmount = round(max(0, $amount), 2);

        try {
            $this->syncLeadingHold($ikan, $userId, $targetAmount, $reason);
        } catch (DomainException) {
            return false;
        }

        $context = $this->loadLockedContext($ikan, $userId);
        $hold = $context['holds']->firstWhere('user_id', $userId);
        $user = $context['users'][$userId] ?? null;

        if (! $hold || ! $user) {
            return false;
        }

        foreach ($context['holds'] as $otherHold) {
            if ((int) $otherHold->user_id === $userId) {
                continue;
            }

            $otherUser = $context['users'][(int) $otherHold->user_id] ?? null;
            if (! $otherUser) {
                continue;
            }

            $this->releaseHold($otherHold, $otherUser, 'auction_hold_released', 'winner_captured');
        }

        $heldAmount = round((float) $hold->amount, 2);
        if ($heldAmount + 0.00001 < $targetAmount || $user->saldoDitahan() + 0.00001 < $targetAmount) {
            return false;
        }

        if ($heldAmount - $targetAmount > 0.00001) {
            $excess = round($heldAmount - $targetAmount, 2);

            $this->applyLedger(
                $user,
                $excess,
                -$excess,
                'auction_hold_rebalanced',
                $reason,
                $ikan->id,
                'Kelebihan hold dilepas sebelum capture.'
            );

            $hold->amount = $targetAmount;
        }

        $this->applyLedger(
            $user,
            0,
            -$targetAmount,
            'auction_hold_captured',
            $reason,
            $transaksi?->id ?? $ikan->id,
            'Dana bid pemenang dikonversi menjadi pembayaran otomatis.'
        );

        $hold->status = 'captured';
        $hold->reason = $reason;
        $hold->captured_at = now();
        $hold->released_at = null;
        $hold->release_reason = null;
        $hold->transaksi_id = $transaksi?->id;
        $hold->save();

        return true;
    }

    public function creditAvailableBalance(
        User|int $user,
        float $amount,
        string $entryType = 'topup',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null
    ): User {
        $creditAmount = round($amount, 2);
        if ($creditAmount <= 0) {
            throw new DomainException('Nominal kredit saldo harus lebih besar dari nol.');
        }

        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        $lockedUser = User::query()
            ->whereKey($userId)
            ->lockForUpdate()
            ->first();

        if (! $lockedUser) {
            throw new DomainException('Akun pembeli tidak valid.');
        }

        $this->applyLedger(
            $lockedUser,
            $creditAmount,
            0,
            $entryType,
            $referenceType,
            $referenceId,
            $note
        );

        if ($user instanceof User) {
            $user->saldo = $lockedUser->saldo;
            $user->saldo_tertahan = $lockedUser->saldo_tertahan;
        }

        return $lockedUser;
    }

    public function releaseActiveHolds(Ikan $ikan, string $releaseReason = 'auction_closed', ?int $exceptUserId = null): void
    {
        $context = $this->loadLockedContext($ikan, $exceptUserId);

        foreach ($context['holds'] as $hold) {
            if ($exceptUserId !== null && (int) $hold->user_id === $exceptUserId) {
                continue;
            }

            $user = $context['users'][(int) $hold->user_id] ?? null;
            if (! $user) {
                continue;
            }

            $this->releaseHold($hold, $user, 'auction_hold_released', $releaseReason);
        }
    }

    private function loadLockedContext(Ikan $ikan, ?int $preferredUserId = null): array
    {
        $holds = AuctionBidHold::query()
            ->where('ikan_id', $ikan->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->get();

        $userIds = $holds
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0);

        if ($preferredUserId !== null && $preferredUserId > 0) {
            $userIds->push($preferredUserId);
        }

        $users = User::query()
            ->whereIn('id', $userIds->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        return [
            'holds' => $holds,
            'users' => $users,
        ];
    }

    private function releaseHold(AuctionBidHold $hold, User $user, string $entryType, string $releaseReason): void
    {
        $amount = round((float) $hold->amount, 2);
        if ($amount > 0) {
            $this->applyLedger(
                $user,
                $amount,
                -$amount,
                $entryType,
                'auction_lot',
                $hold->ikan_id,
                'Dana bid dilepas kembali ke saldo tersedia.'
            );
        }

        $hold->status = 'released';
        $hold->released_at = now();
        $hold->release_reason = $releaseReason;
        $hold->save();
    }

    private function applyLedger(
        User $user,
        float $availableDelta,
        float $heldDelta,
        string $entryType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null
    ): void {
        $user->saldo = round($user->saldoTersedia() + $availableDelta, 2);
        $user->saldo_tertahan = round($user->saldoDitahan() + $heldDelta, 2);

        if ($user->saldo < -0.00001 || $user->saldo_tertahan < -0.00001) {
            throw new DomainException('Mutasi saldo menghasilkan nilai negatif yang tidak valid.');
        }

        $user->saldo = max(0, (float) $user->saldo);
        $user->saldo_tertahan = max(0, (float) $user->saldo_tertahan);
        $user->save();

        SaldoLedger::create([
            'user_id' => $user->id,
            'entry_type' => $entryType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'available_delta' => round($availableDelta, 2),
            'held_delta' => round($heldDelta, 2),
            'balance_after' => $user->saldo,
            'held_after' => $user->saldo_tertahan,
            'note' => $note,
        ]);
    }
}
