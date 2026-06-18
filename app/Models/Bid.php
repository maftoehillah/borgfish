<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    protected $fillable = [
        'ikan_id',
        'user_id',
        'jumlah_bid',
        'bidder_ip',
        'bidder_user_agent',
        'is_suspicious',
        'suspicion_reason',
    ];

    protected $casts = [
        'jumlah_bid' => 'decimal:2',
        'is_suspicious' => 'boolean',
    ];

    public function ikan()
    {
        return $this->belongsTo(Ikan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function normalizeIp(?string $ip): ?string
    {
        $value = trim((string) $ip);
        if ($value === '') {
            return null;
        }

        $value = strtolower($value);

        if ($value === '::1') {
            return '127.0.0.1';
        }

        if (str_starts_with($value, '::ffff:')) {
            $mapped = substr($value, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $mapped;
            }
        }

        return $value;
    }

    public static function ipCandidates(?string $ip): array
    {
        $normalized = self::normalizeIp($ip);
        if ($normalized === null) {
            return [];
        }

        $candidates = [$normalized];

        if ($normalized === '127.0.0.1') {
            $candidates[] = '::1';
            $candidates[] = '::ffff:127.0.0.1';
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $candidates[] = '::ffff:' . $normalized;
        }

        return array_values(array_unique($candidates));
    }

    public static function deteksiAnomali(
        float $jumlahBid,
        float $hargaSaatIni,
        int $bidTerakhirDalamSatuMenit,
        bool $ipDipakaiAkunLainLot,
        bool $ipDipakaiAkunLainGlobal = false
    ): array {
        if ($ipDipakaiAkunLainLot) {
            return [true, 'IP sama dengan akun lain pada lot ini'];
        }

        if ($ipDipakaiAkunLainGlobal) {
            return [true, 'IP sama dengan akun lain pada lot berbeda'];
        }

        if ($bidTerakhirDalamSatuMenit >= 4) {
            return [true, 'Frekuensi bid terlalu cepat'];
        }

        if ($hargaSaatIni > 0) {
            $perubahan = (abs($jumlahBid - $hargaSaatIni) / $hargaSaatIni) * 100;
            if ($perubahan > 65) {
                return [true, 'Perubahan harga tidak wajar'];
            }
        }

        return [false, null];
    }
}
