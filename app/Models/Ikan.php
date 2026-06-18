<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Ikan extends Model
{
    protected $fillable = [
        'user_id',
        'nama_ikan',
        'berat',
        'estimasi_jumlah_ekor',
        'jenis_kemasan',
        'kondisi',
        'deskripsi',
        'asal_pelabuhan',
        'tanggal_tangkap',
        'metode_tangkap',
        'grade_mutu',
        'suhu_penyimpanan',
        'surveyor',
        'catatan_survey',
        'verifikasi_pelabuhan_at',
        'harga_awal',
        'harga_tertinggi',
        'reserve_price',
        'tipe_lelang',
        'minimal_increment',
        'buy_now_enabled',
        'buy_now_price',
        'payment_deadline_minutes',
        'payment_deadline_initial_minutes',
        'anti_sniping_enabled',
        'anti_sniping_window_seconds',
        'anti_sniping_extend_seconds',
        'anti_sniping_max_extensions',
        'anti_sniping_extensions_used',
        'waktu_mulai',
        'waktu_selesai',
        'status',
        'auction_state',
        'current_winner_rank',
        'hard_stop_reason',
        'ranking_frozen_at',
        'last_bidder_id',
        'last_bid_at',
        'state_version',
        'foto',
        'video',
        'foto_latitude',
        'foto_longitude',
        'foto_diambil_pada',
    ];

    protected $casts = [
        'tanggal_tangkap' => 'date',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'ranking_frozen_at' => 'datetime',
        'verifikasi_pelabuhan_at' => 'datetime',
        'last_bid_at' => 'datetime',
        'harga_awal' => 'decimal:2',
        'harga_tertinggi' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'tipe_lelang' => 'string',
        'minimal_increment' => 'decimal:2',
        'payment_deadline_minutes' => 'integer',
        'payment_deadline_initial_minutes' => 'integer',
        'estimasi_jumlah_ekor' => 'integer',
        'foto_latitude' => 'float',
        'foto_longitude' => 'float',
        'foto_diambil_pada' => 'datetime',
        'suhu_penyimpanan' => 'decimal:2',
        'buy_now_enabled' => 'boolean',
        'buy_now_price' => 'decimal:2',
        'anti_sniping_enabled' => 'boolean',
        'anti_sniping_window_seconds' => 'integer',
        'anti_sniping_extend_seconds' => 'integer',
        'anti_sniping_max_extensions' => 'integer',
        'anti_sniping_extensions_used' => 'integer',
        'current_winner_rank' => 'integer',
        'state_version' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function isLelangTurun(): bool
    {
        return $this->tipe_lelang === 'turun';
    }

    public function isLelangNaik(): bool
    {
        return ! $this->isLelangTurun();
    }

    public function transaksi()
    {
        return $this->hasOne(Transaksi::class);
    }

    public function auctionRankings()
    {
        return $this->hasMany(AuctionRanking::class);
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function auctionStateLogs()
    {
        return $this->hasMany(AuctionStateLog::class);
    }

    public function lastBidder()
    {
        return $this->belongsTo(User::class, 'last_bidder_id');
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif'
            && now()->between($this->waktu_mulai, $this->waktu_selesai);
    }

    public function isSelesai(): bool
    {
        return $this->status === 'selesai'
            || $this->status === 'terbayar'
            || now()->gt($this->waktu_selesai);
    }

    public function bidMinimal(): float
    {
        if ($this->isLelangTurun()) {
            $belowReference = max(0, (float) $this->harga_awal - 1);
            $roundedToThousand = floor($belowReference / 1000) * 1000;

            return max(1000, (float) $roundedToThousand);
        }

        return (float) $this->harga_tertinggi + (float) $this->minimal_increment;
    }

    public function bestBid()
    {
        return $this->isLelangTurun()
            ? $this->bids()->orderByDesc('jumlah_bid')->first()
            : $this->bids()->orderByDesc('jumlah_bid')->first();
    }

    public function buyNowTarget(): ?float
    {
        if ($this->isLelangTurun()) {
            return (float) $this->harga_awal;
        }

        if (! $this->buy_now_enabled || $this->buy_now_price === null) {
            return null;
        }

        return (float) $this->buy_now_price;
    }

    public function canBuyNow(): bool
    {
        $target = $this->buyNowTarget();

        if ($this->isLelangTurun()) {
            return $this->isAktif()
                && $target !== null
                && $target > 0;
        }

        return $this->isAktif()
            && $this->isLelangNaik()
            && $target !== null
            && $target > (float) $this->harga_tertinggi;
    }

    public function hasReachedBuyNow(float $nominal): bool
    {
        if ($this->isLelangTurun()) {
            return false;
        }

        $target = $this->buyNowTarget();

        return $target !== null && $nominal >= $target;
    }

    public function canApplyAntiSniping(Carbon $reference): bool
    {
        if (! $this->anti_sniping_enabled) {
            return false;
        }

        if (! $this->isAktif()) {
            return false;
        }

        if ($this->anti_sniping_extensions_used >= $this->anti_sniping_max_extensions) {
            return false;
        }

        $window = max(10, (int) $this->anti_sniping_window_seconds);

        return $reference->gte($this->waktu_selesai->copy()->subSeconds($window));
    }

    public function applyAntiSnipingIfNeeded(Carbon $reference): bool
    {
        if (! $this->canApplyAntiSniping($reference)) {
            return false;
        }

        $extendSeconds = max(10, (int) $this->anti_sniping_extend_seconds);

        $this->waktu_selesai = $this->waktu_selesai->copy()->addSeconds($extendSeconds);
        $this->anti_sniping_extensions_used = ((int) $this->anti_sniping_extensions_used) + 1;

        return true;
    }

    public function bumpStateVersion(): void
    {
        $this->state_version = ((int) $this->state_version) + 1;
    }

    public function resolvePaymentDeadlineMinutes(): int
    {
        return app(\App\Services\SystemSettingService::class)->paymentDeadlineMinutes();
    }

    public function resolveInitialPaymentDeadlineMinutes(): int
    {
        return $this->resolvePaymentDeadlineMinutes();
    }
}
