<?php

if (! function_exists('formatRupiah')) {
    function formatRupiah($angka): string
    {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }
}

if (! function_exists('maskedBidderName')) {
    function maskedBidderName(?string $name, int|string|null $seed = null): string
    {
        $clean = trim((string) $name);
        if ($clean === '') {
            $clean = 'Bidder';
        }

        $first = substr($clean, 0, 1);
        $hash = strtoupper(substr(sha1((string) ($seed ?? $clean)), 0, 3));

        return $first . '***-' . $hash;
    }
}

if (! function_exists('safeInternalReturnUrl')) {
    function safeInternalReturnUrl(mixed $candidate, string $fallback): string
    {
        if (! is_string($candidate)) {
            return $fallback;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return $fallback;
        }

        // Allow root-relative internal paths.
        if (str_starts_with($candidate, '/')) {
            if (str_starts_with($candidate, '//')) {
                return $fallback;
            }

            return url($candidate);
        }

        $parsedCandidate = parse_url($candidate);
        $parsedAppUrl = parse_url((string) config('app.url'));

        if ($parsedCandidate === false || $parsedAppUrl === false) {
            return $fallback;
        }

        $candidateScheme = strtolower((string) ($parsedCandidate['scheme'] ?? ''));
        $candidateHost = strtolower((string) ($parsedCandidate['host'] ?? ''));
        $appScheme = strtolower((string) ($parsedAppUrl['scheme'] ?? ''));
        $appHost = strtolower((string) ($parsedAppUrl['host'] ?? ''));

        if ($candidateScheme === '' || $candidateHost === '' || $appScheme === '' || $appHost === '') {
            return $fallback;
        }

        if ($candidateScheme !== $appScheme || $candidateHost !== $appHost) {
            return $fallback;
        }

        $candidatePort = $parsedCandidate['port'] ?? null;
        $appPort = $parsedAppUrl['port'] ?? null;
        if ($candidatePort !== null || $appPort !== null) {
            if ((int) ($candidatePort ?? 0) !== (int) ($appPort ?? 0)) {
                return $fallback;
            }
        }

        return $candidate;
    }
}

if (! function_exists('notificationDestinationUrl')) {
    function notificationDestinationUrl(\App\Models\User $user, \App\Models\InAppNotification $notification, ?string $fallback = null): string
    {
        $fallbackUrl = $fallback ?: route('notifications.index');
        $transaksiId = (int) data_get($notification->payload, 'transaksi_id', 0);
        $disputeId = (int) data_get($notification->payload, 'dispute_id', 0);
        $violationId = (int) data_get($notification->payload, 'violation_id', 0);
        $ikanIdFromPayload = (int) data_get($notification->payload, 'ikan_id', 0);
        $notificationEvent = (string) data_get($notification->payload, 'event', '');
        $notificationCategory = (string) ($notification->category ?? '');

        static $transaksiCache = [];
        static $disputeCache = [];
        static $ikanCache = [];

        $dispute = null;
        if ($disputeId > 0) {
            if (! array_key_exists($disputeId, $disputeCache)) {
                $disputeCache[$disputeId] = \App\Models\TransactionDispute::query()
                    ->with(['transaksi.ikan'])
                    ->find($disputeId);
            }

            $dispute = $disputeCache[$disputeId];
            if ($transaksiId <= 0 && $dispute) {
                $transaksiId = (int) $dispute->transaksi_id;
            }
        }

        if ($user->isPanelAdmin()) {
            if ($violationId > 0) {
                return url('/admin/violations?tableSearch=' . rawurlencode((string) $violationId));
            }

            if ($dispute && (int) $dispute->id > 0) {
                return url('/admin/transaction-disputes/' . $dispute->id);
            }

            if ($transaksiId > 0) {
                if (! array_key_exists($transaksiId, $transaksiCache)) {
                    $transaksiCache[$transaksiId] = \App\Models\Transaksi::query()
                        ->with(['ikan'])
                        ->find($transaksiId);
                }

                $adminTransaksi = $transaksiCache[$transaksiId];
                if ($adminTransaksi) {
                    return url('/admin/transaksis/' . $adminTransaksi->id);
                }
            }

            if ($ikanIdFromPayload > 0) {
                if (! array_key_exists($ikanIdFromPayload, $ikanCache)) {
                    $ikanCache[$ikanIdFromPayload] = \App\Models\Ikan::query()->find($ikanIdFromPayload);
                }

                $adminIkan = $ikanCache[$ikanIdFromPayload];
                if ($adminIkan) {
                    return url('/admin/ikans/' . $adminIkan->id);
                }
            }

            return match ($notificationCategory) {
                'pelanggaran' => url('/admin/violations'),
                'sengketa' => url('/admin/transaction-disputes'),
                'pembayaran', 'pesanan', 'penjemputan', 'operasional' => url('/admin/transaksis'),
                'lelang' => url('/admin/ikans'),
                default => url('/admin/notifikasi'),
            };
        }

        if ($transaksiId <= 0) {
            if ($ikanIdFromPayload <= 0) {
                if ($user->isPembeli() && in_array($notificationCategory, ['pembayaran', 'pesanan', 'penjemputan', 'sengketa', 'lelang', 'operasional'], true)) {
                    return route('pembeli.aktivitas');
                }

                if ($user->role === 'penjual' && in_array($notificationCategory, ['pembayaran', 'pesanan', 'penjemputan', 'sengketa', 'lelang', 'operasional'], true)) {
                    return route('penjual.ikans.index');
                }

                return $fallbackUrl;
            }

            if (! array_key_exists($ikanIdFromPayload, $ikanCache)) {
                $ikanCache[$ikanIdFromPayload] = \App\Models\Ikan::query()->find($ikanIdFromPayload);
            }

            $ikanOnly = $ikanCache[$ikanIdFromPayload];
            if (! $ikanOnly) {
                return $fallbackUrl;
            }

            if ($user->isPembeli()) {
                $hasBidActivity = \App\Models\Bid::query()
                    ->where('ikan_id', (int) $ikanOnly->id)
                    ->where('user_id', (int) $user->id)
                    ->exists();

                if ($hasBidActivity) {
                    return route('pembeli.aktivitas.detail', [
                        'ikan' => $ikanOnly,
                        'return_url' => $fallbackUrl,
                    ]);
                }

                return route('ikans.show', $ikanOnly);
            }

            if ((int) $ikanOnly->user_id === (int) $user->id && $user->role === 'penjual') {
                return route('penjual.ikans.show', [
                    'ikan' => $ikanOnly,
                    'return_url' => $fallbackUrl,
                ]);
            }

            return route('ikans.show', $ikanOnly);
        }

        if (! array_key_exists($transaksiId, $transaksiCache)) {
            $transaksiCache[$transaksiId] = \App\Models\Transaksi::query()
                ->with(['ikan'])
                ->find($transaksiId);
        }

        $transaksi = $transaksiCache[$transaksiId];
        if (! $transaksi || ! $transaksi->ikan) {
            if ($user->isPembeli() && in_array($notificationCategory, ['pembayaran', 'pesanan', 'penjemputan', 'sengketa', 'lelang', 'operasional'], true)) {
                return route('pembeli.aktivitas');
            }

            if ($user->role === 'penjual' && in_array($notificationCategory, ['pembayaran', 'pesanan', 'penjemputan', 'sengketa', 'lelang', 'operasional'], true)) {
                return route('penjual.ikans.index');
            }

            return $fallbackUrl;
        }

        $ikan = $transaksi->ikan;

        if ($user->isPembeli() && (int) $transaksi->pemenang_id === (int) $user->id) {
            if (in_array((string) $transaksi->status, ['menunggu_bayar', 'kadaluarsa'], true)) {
                return route('pembayaran.show', [
                    'transaksi' => $transaksi,
                    'return_url' => $fallbackUrl,
                ]);
            }

            $hasBidActivity = \App\Models\Bid::query()
                ->where('ikan_id', (int) $ikan->id)
                ->where('user_id', (int) $user->id)
                ->exists();

            if (! $hasBidActivity) {
                return route('pembeli.aktivitas');
            }

            return route('pembeli.aktivitas.detail', [
                'ikan' => $ikan,
                'return_url' => $fallbackUrl,
            ]);
        }

        if ($user->isPembeli() && in_array($notificationEvent, ['payment_expired', 'payment_reassigned'], true)) {
            return route('pembeli.aktivitas');
        }

        if ($user->isPembeli() && in_array($notificationEvent, ['auction_lost', 'auction_no_winner'], true)) {
            $hasBidActivity = \App\Models\Bid::query()
                ->where('ikan_id', (int) $ikan->id)
                ->where('user_id', (int) $user->id)
                ->exists();

            if ($hasBidActivity) {
                return route('pembeli.aktivitas.detail', [
                    'ikan' => $ikan,
                    'return_url' => $fallbackUrl,
                ]);
            }

            return route('pembeli.aktivitas');
        }

        if ((int) $ikan->user_id === (int) $user->id && $user->role === 'penjual') {
            return route('penjual.ikans.show', [
                'ikan' => $ikan,
                'return_url' => $fallbackUrl,
            ]);
        }

        if ($user->isPembeli() && in_array($notificationCategory, ['pembayaran', 'pesanan', 'penjemputan', 'packing', 'sengketa', 'lelang', 'operasional', 'pelanggaran'], true)) {
            return route('pembeli.aktivitas');
        }

        if ($user->role === 'penjual' && in_array($notificationCategory, ['pembayaran', 'pesanan', 'penjemputan', 'packing', 'sengketa', 'lelang', 'operasional', 'pelanggaran'], true)) {
            return route('penjual.ikans.index');
        }

        return route('ikans.show', $ikan);
    }
}

if (! function_exists('pickupStatusLabel')) {
    function pickupStatusLabel(?string $status): string
    {
        return match ($status) {
            'waiting_payment' => 'Menunggu Pembayaran',
            'awaiting_buyer_pickup' => 'Menunggu Data Penjemput',
            'packing' => 'Sedang Dipacking',
            'awaiting_pickup' => 'Menunggu Penjemput',
            'pickup_arrived' => 'Penjemput Datang',
            'completed' => 'Selesai',
            'payment_expired' => 'Pembayaran Kedaluwarsa',
            'payment_failed' => 'Pembayaran Gagal',
            default => 'Belum Tersedia',
        };
    }
}

if (! function_exists('transactionStatusLabel')) {
    function transactionStatusLabel(?string $status): string
    {
        return match ($status) {
            'menunggu_bayar' => 'Menunggu Pembayaran',
            'proses' => 'Proses',
            'lunas' => 'Lunas',
            'gagal' => 'Gagal',
            'kadaluarsa' => 'Kadaluarsa',
            default => 'Belum Tersedia',
        };
    }
}

if (! function_exists('paymentStatusLabel')) {
    function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'paid' => 'Dibayar',
            'failed' => 'Gagal',
            'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
            'refunded' => 'Refund',
            default => 'Belum Tersedia',
        };
    }
}

if (! function_exists('lotStatusLabel')) {
    function lotStatusLabel(?string $status): string
    {
        return match ($status) {
            'aktif' => 'Aktif',
            'menunggu' => 'Menunggu',
            'terbayar' => 'Selesai',
            'selesai' => 'Selesai',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Belum Tersedia',
        };
    }
}

if (! function_exists('notificationCategoryLabel')) {
    function notificationCategoryLabel(?string $category): string
    {
        return match ($category) {
            'pembayaran' => 'Pembayaran',
            'pesanan' => 'Pesanan',
            'penjemputan' => 'Penjemputan',
            'sengketa' => 'Sengketa',
            'operasional' => 'Operasional',
            'pelanggaran' => 'Pelanggaran',
            'lelang' => 'Lelang',
            'packing' => 'Packing',
            default => $category ? ucfirst(str_replace('_', ' ', $category)) : 'Umum',
        };
    }
}

if (! function_exists('transactionStateLabel')) {
    function transactionStateLabel(?string $state): string
    {
        return match ($state) {
            'DIBAYAR' => 'Dibayar',
            'DIPROSES_PENJUAL' => 'Diproses Penjual',
            'DIKIRIM' => 'Dalam Penjemputan',
            'SELESAI' => 'Selesai',
            'GAGAL' => 'Gagal',
            'DISENGKETAKAN' => 'Dalam Komplain',
            default => 'Belum Tersedia',
        };
    }
}

if (! function_exists('sellerSettlementStatusLabel')) {
    function sellerSettlementStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pending Review',
            'ready_to_pay' => 'Siap Dibayar',
            'held' => 'Ditahan',
            'paid' => 'Sudah Dibayar',
            'cancelled' => 'Dibatalkan',
            default => 'Belum Tersedia',
        };
    }
}

if (! function_exists('sellerSettlementStatusBadgeClass')) {
    function sellerSettlementStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'pending' => 'bg-amber-100 text-amber-700',
            'ready_to_pay' => 'bg-sky-100 text-sky-700',
            'held' => 'bg-rose-100 text-rose-700',
            'paid' => 'bg-emerald-100 text-emerald-700',
            'cancelled' => 'bg-slate-100 text-slate-600',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}

if (! function_exists('humanDeadlineLabel')) {
    function humanDeadlineLabel($dateTime, string $prefixFuture = 'Sisa waktu', string $prefixPast = 'Lewat'): string
    {
        if (! $dateTime) {
            return '-';
        }

        if (now()->lte($dateTime)) {
            return $prefixFuture . ' ' . now()->diffForHumans($dateTime, true);
        }

        return $prefixPast . ' ' . $dateTime->diffForHumans(now(), true);
    }
}

if (! function_exists('fulfillmentStateLabel')) {
    function fulfillmentStateLabel(?string $state): string
    {
        return match ($state) {
            'DIBAYAR' => 'Dibayar',
            'DIPROSES_PENJUAL' => 'Diproses Penjual',
            'DIKIRIM' => 'Penjemput Datang',
            'SELESAI' => 'Selesai',
            'GAGAL' => 'Gagal',
            'DISENGKETAKAN' => 'Disengketakan',
            default => 'Belum Tersedia',
        };
    }
}

if (! function_exists('fulfillmentStateBadgeClass')) {
    function fulfillmentStateBadgeClass(?string $state): string
    {
        return match ($state) {
            'DIBAYAR' => 'bg-cyan-100 text-cyan-700',
            'DIPROSES_PENJUAL' => 'bg-amber-100 text-amber-700',
            'DIKIRIM' => 'bg-blue-100 text-blue-700',
            'SELESAI' => 'bg-emerald-100 text-emerald-700',
            'GAGAL' => 'bg-rose-100 text-rose-700',
            'DISENGKETAKAN' => 'bg-slate-200 text-slate-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}

if (! function_exists('fulfillmentStateDescription')) {
    function fulfillmentStateDescription(?string $state): string
    {
        return match ($state) {
            'DIBAYAR' => 'Pembayaran sukses, menunggu respons penjual.',
            'DIPROSES_PENJUAL' => 'Penjual sedang menyiapkan pesanan.',
            'DIKIRIM' => 'Penjemput sudah divalidasi dan transaksi menunggu konfirmasi pembeli.',
            'SELESAI' => 'Transaksi selesai.',
            'GAGAL' => 'Transaksi gagal dan perlu tindak lanjut.',
            'DISENGKETAKAN' => 'Transaksi masuk proses sengketa admin.',
            default => 'Status fulfillment belum tersedia.',
        };
    }
}

if (! function_exists('publicStorageUrl')) {
    function publicStorageUrl(?string $path): string
    {
        $normalized = trim((string) $path);
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');

        if (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, strlen('public/'));
        }

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $normalized)));
        return '/media/' . $encodedPath;
    }
}
