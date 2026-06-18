<?php

namespace App\Services;

use App\Models\Ikan;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;

class ViolationService
{
    public function recordBuyerNoPayment(User $user, Transaksi $order, ?Ikan $lot = null): Violation
    {
        return DB::transaction(function () use ($user, $order, $lot): Violation {
            $priorCount = Violation::query()
                ->where('user_id', $user->id)
                ->where('type', 'buyer_non_payment')
                ->count();

            $banThreshold = (int) config('marketplace.buyer_violation_ban_threshold', 3);
            $suspendHours = (int) config('marketplace.buyer_violation_suspend_hours', 24);

            $action = ($priorCount + 1) >= $banThreshold ? 'banned' : 'suspend';
            $effectiveUntil = $action === 'suspend' ? now()->addHours($suspendHours) : null;

            $violation = Violation::create([
                'user_id' => $user->id,
                'ikan_id' => $lot?->id ?? $order->ikan_id,
                'transaksi_id' => $order->id,
                'role' => 'buyer',
                'type' => 'buyer_non_payment',
                'status' => 'active',
                'action' => $action,
                'reason' => 'Menang bid tetapi tidak menyelesaikan pembayaran sebelum tenggat berakhir.',
                'duration_hours' => $action === 'suspend' ? $suspendHours : null,
                'effective_from' => now(),
                'effective_until' => $effectiveUntil,
            ]);

            if ($action === 'banned') {
                $user->forceFill([
                    'user_status' => 'banned',
                    'suspended_until' => null,
                    'status_reason' => 'Akun diblokir permanen karena pelanggaran pembayaran berulang.',
                ])->save();
            } else {
                $user->forceFill([
                    'user_status' => 'suspend',
                    'suspended_until' => $effectiveUntil,
                    'status_reason' => 'Akun disuspend otomatis karena tidak membayar kemenangan lelang.',
                ])->save();
            }

            AuditService::log('system', null, 'violation.created', 'violations', (int) $violation->id, [
                'user_id' => $user->id,
                'transaksi_id' => $order->id,
                'action' => $action,
            ]);

            app(NotificationOutboxService::class)->queue(
                (int) $user->id,
                'pelanggaran',
                'Pelanggaran akun tercatat',
                $action === 'banned'
                    ? 'Akun Anda diblokir permanen karena tidak membayar kemenangan lelang berulang kali.'
                    : 'Akun Anda disuspend sementara karena tidak membayar kemenangan lelang tepat waktu.',
                [
                    'violation_id' => $violation->id,
                    'transaksi_id' => $order->id,
                    'action' => $action,
                    'effective_until' => $effectiveUntil?->toIso8601String(),
                ],
                'violation:' . $violation->id
            );

            return $violation;
        }, 3);
    }

    public function suspend(User $user, string $reason, int $adminId, ?int $durationHours = null, ?string $notes = null): Violation
    {
        return $this->recordManual($user, 'manual_suspend', 'suspend', $reason, $adminId, $durationHours, $notes);
    }

    public function ban(User $user, string $reason, int $adminId, ?string $notes = null): Violation
    {
        return $this->recordManual($user, 'manual_ban', 'banned', $reason, $adminId, null, $notes);
    }

    public function lift(User $user, int $adminId, ?string $notes = null): void
    {
        DB::transaction(function () use ($user, $adminId, $notes): void {
            $user->forceFill([
                'user_status' => 'active',
                'suspended_until' => null,
                'status_reason' => $notes,
            ])->save();

            Violation::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'resolved',
                    'admin_executor_id' => $adminId,
                    'notes' => $notes,
                    'resolved_at' => now(),
                ]);
        }, 3);
    }

    public function deleteViolation(Violation $violation, int $adminId, ?string $notes = null): void
    {
        $violation->admin_executor_id = $adminId;
        $violation->notes = $notes;
        $violation->status = 'deleted';
        $violation->resolved_at = now();
        $violation->save();
        $violation->delete();
    }

    private function recordManual(User $user, string $type, string $action, string $reason, int $adminId, ?int $durationHours = null, ?string $notes = null): Violation
    {
        return DB::transaction(function () use ($user, $type, $action, $reason, $adminId, $durationHours, $notes): Violation {
            $effectiveUntil = $action === 'suspend' && $durationHours ? now()->addHours($durationHours) : null;

            $violation = Violation::create([
                'user_id' => $user->id,
                'admin_executor_id' => $adminId,
                'role' => $user->isPenjual() ? 'seller' : 'buyer',
                'type' => $type,
                'status' => 'active',
                'action' => $action,
                'reason' => $reason,
                'notes' => $notes,
                'duration_hours' => $durationHours,
                'effective_from' => now(),
                'effective_until' => $effectiveUntil,
            ]);

            $user->forceFill([
                'user_status' => $action === 'banned' ? 'banned' : 'suspend',
                'suspended_until' => $effectiveUntil,
                'status_reason' => $reason,
            ])->save();

            return $violation;
        }, 3);
    }
}
