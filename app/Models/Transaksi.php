<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $fillable = [
        'order_code',
        'ikan_id',
        'pemenang_id',
        'winner_rank',
        'assigned_at',
        'harga_final',
        'status',
        'payment_status',
        'fulfillment_state',
        'state_version',
        'state_reason_code',
        'state_reason_text',
        'metode_pembayaran',
        'bayar_sebelum',
        'payment_expired_at',
        'dibayar_pada',
        'paid_at',
        'seller_ack_at',
        'completed_at',
        'failed_at',
        'disputed_at',
        'seller_ack_deadline_at',
        'seller_process_deadline_at',
        'buyer_confirm_deadline_at',
        'packed_at',
        'packing_proof',
        'packing_location',
        'packing_recorded_at',
        'packing_description',
        'pickup_status',
        'buyer_pickup_name',
        'buyer_pickup_plate_number',
        'buyer_pickup_photo',
        'buyer_pickup_vehicle_photo',
        'buyer_pickup_notes',
        'buyer_pickup_submitted_at',
        'seller_pickup_driver_name',
        'seller_pickup_driver_photo',
        'seller_pickup_vehicle_photo',
        'seller_pickup_plate_number',
        'seller_pickup_recorded_at',
        'pickup_match_status',
        'pickup_verified_at',
        'buyer_rating',
        'buyer_review',
        'buyer_reviewed_at',
        'completed_by_buyer_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'bayar_sebelum' => 'datetime',
        'payment_expired_at' => 'datetime',
        'dibayar_pada' => 'datetime',
        'paid_at' => 'datetime',
        'seller_ack_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'disputed_at' => 'datetime',
        'seller_ack_deadline_at' => 'datetime',
        'seller_process_deadline_at' => 'datetime',
        'buyer_confirm_deadline_at' => 'datetime',
        'packed_at' => 'datetime',
        'packing_recorded_at' => 'datetime',
        'buyer_pickup_submitted_at' => 'datetime',
        'seller_pickup_recorded_at' => 'datetime',
        'pickup_verified_at' => 'datetime',
        'buyer_reviewed_at' => 'datetime',
        'completed_by_buyer_at' => 'datetime',
        'state_version' => 'integer',
        'winner_rank' => 'integer',
        'buyer_rating' => 'integer',
        'harga_final' => 'decimal:2',
    ];

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function pemenang()
    {
        return $this->belongsTo(User::class, 'pemenang_id');
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function stateLogs()
    {
        return $this->hasMany(TransactionStateLog::class);
    }

    public function disputes()
    {
        return $this->hasMany(TransactionDispute::class)->orderByDesc('id');
    }

    public function sellerSettlement()
    {
        return $this->hasOne(SellerSettlement::class);
    }

    public function isBelumBayar(): bool
    {
        return $this->status === 'menunggu_bayar'
            && ! in_array((string) $this->payment_status, ['paid', 'refunded'], true);
    }

    public function isLunas(): bool
    {
        return $this->status === 'lunas'
            || $this->payment_status === 'paid';
    }

    public function isKadaluarsa(): bool
    {
        return $this->status === 'kadaluarsa'
            || ($this->bayar_sebelum && now()->gt($this->bayar_sebelum));
    }

    public function totalTagihan(): float
    {
        return (float) $this->harga_final;
    }

    public function latestPayment()
    {
        return $this->paymentAttempts()->latest('id')->first();
    }

    public function markWaitingPayment(int $rank, \DateTimeInterface $deadline): void
    {
        $this->winner_rank = $rank;
        $this->assigned_at = $this->assigned_at ?? now();
        $this->status = 'menunggu_bayar';
        $this->payment_status = 'pending';
        $this->bayar_sebelum = $deadline;
        $this->dibayar_pada = null;
        $this->paid_at = null;
        $this->payment_expired_at = null;
        $this->pickup_status = 'waiting_payment';
    }

    public function markPaid(?string $method = null): void
    {
        $this->status = 'lunas';
        $this->payment_status = 'paid';
        $this->metode_pembayaran = $method ?: $this->metode_pembayaran;
        $this->dibayar_pada = $this->dibayar_pada ?? now();
        $this->paid_at = $this->paid_at ?? $this->dibayar_pada ?? now();
        $this->pickup_status = $this->buyer_pickup_submitted_at ? 'awaiting_pickup' : 'awaiting_buyer_pickup';
    }

    public function markPaymentExpired(): void
    {
        $this->status = 'kadaluarsa';
        $this->payment_status = 'expired';
        $this->payment_expired_at = $this->payment_expired_at ?? now();
        $this->pickup_status = 'payment_expired';
    }

    public function markPaymentFailed(): void
    {
        $this->status = 'gagal';
        $this->payment_status = 'failed';
        $this->failed_at = $this->failed_at ?? now();
        $this->pickup_status = 'payment_failed';
    }

    public function markDipacking(?string $proofPath = null, ?string $location = null, ?string $description = null, ?\DateTimeInterface $recordedAt = null): void
    {
        $this->pickup_status = 'packing';
        $this->packed_at = now();
        $this->packing_recorded_at = $recordedAt ?? now();
        $this->packing_location = $location;
        $this->packing_description = $description;

        if ($proofPath !== null) {
            $this->packing_proof = $proofPath;
        }
    }

    public function markBuyerPickupSubmitted(string $name, string $plate, ?string $driverPhotoPath = null, ?string $vehiclePhotoPath = null, ?string $notes = null): void
    {
        $this->buyer_pickup_name = $name;
        $this->buyer_pickup_plate_number = $plate;
        $this->buyer_pickup_photo = $driverPhotoPath;
        $this->buyer_pickup_vehicle_photo = $vehiclePhotoPath;
        $this->buyer_pickup_notes = $notes;
        $this->buyer_pickup_submitted_at = now();
        $this->pickup_status = 'awaiting_pickup';
    }

    public function markPickupArrived(string $driverName, string $plateNumber, ?string $driverPhotoPath = null, ?string $vehiclePhotoPath = null, bool $matched = false): void
    {
        $this->seller_pickup_driver_name = $driverName;
        $this->seller_pickup_plate_number = $plateNumber;
        $this->seller_pickup_driver_photo = $driverPhotoPath;
        $this->seller_pickup_vehicle_photo = $vehiclePhotoPath;
        $this->seller_pickup_recorded_at = now();
        $this->pickup_match_status = $matched ? 'matched' : 'mismatch';
        $this->pickup_verified_at = $matched ? now() : null;
        $this->pickup_status = 'pickup_arrived';
    }

    public function markDiterima(?int $rating = null, ?string $review = null): void
    {
        $this->pickup_status = 'completed';
        $this->completed_by_buyer_at = $this->completed_by_buyer_at ?? now();
        $this->buyer_rating = $rating;
        $this->buyer_review = $review;
        $this->buyer_reviewed_at = $rating !== null || $review !== null ? now() : $this->buyer_reviewed_at;
    }

    public function isCompletedForSettlement(): bool
    {
        return $this->isLunas()
            && (
                $this->completed_by_buyer_at !== null
                || (string) $this->pickup_status === 'completed'
                || (string) $this->fulfillment_state === 'SELESAI'
            );
    }

    public function buyerProgressKey(): string
    {
        if ((string) $this->fulfillment_state === 'DISENGKETAKAN') {
            return 'komplain';
        }

        if ((string) $this->fulfillment_state === 'GAGAL' || (string) $this->status === 'gagal') {
            return 'gagal';
        }

        if ($this->completed_by_buyer_at !== null
            || (string) $this->pickup_status === 'completed'
            || (string) $this->fulfillment_state === 'SELESAI') {
            return 'selesai';
        }

        if ((string) $this->pickup_status === 'pickup_arrived' || (string) $this->fulfillment_state === 'DIKIRIM') {
            return 'dalam_penjemputan';
        }

        if ((string) $this->pickup_status === 'awaiting_pickup') {
            return 'menunggu_penjemput';
        }

        if ($this->isLunas() && $this->packed_at) {
            return 'siap_dijemput';
        }

        if ($this->isLunas()) {
            return 'diproses_penjual';
        }

        if ($this->isKadaluarsa()) {
            return 'kadaluarsa';
        }

        if ($this->isBelumBayar()) {
            return 'menunggu_pembayaran';
        }

        return 'belum_tersedia';
    }

    public function buyerProgressLabel(): string
    {
        return match ($this->buyerProgressKey()) {
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'diproses_penjual' => 'Diproses Penjual',
            'siap_dijemput' => 'Siap Dijemput',
            'menunggu_penjemput' => 'Menunggu Penjemput',
            'dalam_penjemputan' => 'Dalam Penjemputan',
            'selesai' => 'Selesai',
            'kadaluarsa' => 'Kadaluarsa',
            'komplain' => 'Dalam Komplain',
            'gagal' => 'Gagal',
            default => 'Belum Tersedia',
        };
    }

    public function buyerProgressBadgeClass(): string
    {
        return match ($this->buyerProgressKey()) {
            'menunggu_pembayaran' => 'bg-amber-100 text-amber-700',
            'diproses_penjual' => 'bg-cyan-100 text-cyan-700',
            'siap_dijemput' => 'bg-teal-100 text-teal-700',
            'menunggu_penjemput' => 'bg-sky-100 text-sky-700',
            'dalam_penjemputan' => 'bg-blue-100 text-blue-700',
            'selesai' => 'bg-emerald-100 text-emerald-700',
            'kadaluarsa', 'gagal' => 'bg-rose-100 text-rose-700',
            'komplain' => 'bg-orange-100 text-orange-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function buyerProgressDescription(): string
    {
        return match ($this->buyerProgressKey()) {
            'menunggu_pembayaran' => 'Pembayaran belum selesai. Segera bayar sebelum tenggat berakhir.',
            'diproses_penjual' => 'Pembayaran sudah masuk dan penjual sedang menyiapkan lot.',
            'siap_dijemput' => 'Lot sudah dipacking. Silakan lanjutkan dengan data penjemput.',
            'menunggu_penjemput' => 'Data penjemput sudah masuk dan penjual sedang menunggu kedatangan penjemput.',
            'dalam_penjemputan' => 'Penjemput sudah divalidasi. Transaksi menunggu konfirmasi selesai dari pembeli.',
            'selesai' => 'Transaksi telah dikonfirmasi selesai.',
            'kadaluarsa' => 'Batas waktu pembayaran sudah lewat.',
            'komplain' => 'Transaksi sedang ditinjau melalui proses komplain admin.',
            'gagal' => 'Transaksi gagal dan memerlukan tindak lanjut admin.',
            default => 'Status transaksi belum tersedia.',
        };
    }

    /**
     * @return array<int, array{title:string, description:string, at:?string, done:bool, current:bool}>
     */
    public function buyerTimeline(): array
    {
        $steps = [
            [
                'key' => 'assigned',
                'title' => 'Pemenang Ditentukan',
                'description' => 'Anda tercatat sebagai pemenang lot dan invoice siap diproses.',
                'at' => $this->assigned_at?->format('d M Y H:i') ?? $this->created_at?->format('d M Y H:i'),
                'done' => $this->assigned_at !== null || $this->exists,
            ],
            [
                'key' => 'paid',
                'title' => 'Pembayaran Berhasil',
                'description' => 'Pembayaran dikonfirmasi dan transaksi resmi berjalan.',
                'at' => $this->dibayar_pada?->format('d M Y H:i') ?? $this->paid_at?->format('d M Y H:i'),
                'done' => $this->isLunas(),
            ],
            [
                'key' => 'packed',
                'title' => 'Penjual Menyiapkan Lot',
                'description' => 'Penjual mengunggah bukti packing dan menyiapkan lot untuk dijemput.',
                'at' => $this->packed_at?->format('d M Y H:i') ?? $this->packing_recorded_at?->format('d M Y H:i'),
                'done' => $this->packed_at !== null || $this->packing_recorded_at !== null,
            ],
            [
                'key' => 'pickup_submitted',
                'title' => 'Data Penjemput Dikirim',
                'description' => 'Nama sopir, plat nomor, dan foto penjemput telah dikirim.',
                'at' => $this->buyer_pickup_submitted_at?->format('d M Y H:i'),
                'done' => $this->buyer_pickup_submitted_at !== null,
            ],
            [
                'key' => 'pickup_validated',
                'title' => 'Penjemput Tervalidasi',
                'description' => 'Penjual mencocokkan data sopir dan kendaraan di lokasi.',
                'at' => $this->seller_pickup_recorded_at?->format('d M Y H:i'),
                'done' => $this->seller_pickup_recorded_at !== null || (string) $this->pickup_status === 'pickup_arrived',
            ],
            [
                'key' => 'completed',
                'title' => 'Transaksi Selesai',
                'description' => 'Pembeli mengonfirmasi barang diterima atau sistem menyelesaikan transaksi.',
                'at' => $this->completed_by_buyer_at?->format('d M Y H:i') ?? $this->completed_at?->format('d M Y H:i'),
                'done' => $this->completed_by_buyer_at !== null
                    || $this->completed_at !== null
                    || (string) $this->pickup_status === 'completed'
                    || (string) $this->fulfillment_state === 'SELESAI',
            ],
        ];

        $currentKey = match ($this->buyerProgressKey()) {
            'menunggu_pembayaran', 'kadaluarsa', 'gagal' => 'assigned',
            'diproses_penjual' => 'paid',
            'siap_dijemput' => 'packed',
            'menunggu_penjemput' => 'pickup_submitted',
            'dalam_penjemputan', 'komplain' => 'pickup_validated',
            'selesai' => 'completed',
            default => 'assigned',
        };

        return array_map(fn (array $step): array => [
            'title' => $step['title'],
            'description' => $step['description'],
            'at' => $step['at'],
            'done' => $step['done'],
            'current' => $step['key'] === $currentKey,
        ], $steps);
    }
}
