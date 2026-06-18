<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class BenchmarkMarketQueriesCommand extends Command
{
    protected $signature = 'bench:market-queries
        {--lots=300 : Jumlah lot sintetis}
        {--bids-per-lot=90 : Jumlah bid per lot}
        {--buyers=20 : Jumlah akun pembeli sintetis}
        {--iterations=80 : Iterasi pengukuran per query}
        {--json : Keluarkan hasil ke terminal dalam JSON}
        {--report-name= : Nama dasar file report (tanpa ekstensi)}
        {--report-dir=benchmarks : Folder report relatif ke storage/app}
        {--prune-keep=0 : Simpan hanya N run report terbaru pada folder report}
        {--prune-dry-run : Simulasi prune tanpa menghapus file report}
        {--prune-confirm : Konfirmasi eksplisit agar prune benar-benar menghapus file}
        {--save-json : Simpan hasil benchmark ke file JSON di storage/app/benchmarks}
        {--save-csv : Simpan hasil benchmark ke file CSV di storage/app/benchmarks}';

    protected $description = 'Benchmark query marketplace dan dashboard menggunakan dataset sintetis dalam transaksi rollback';

    public function handle(): int
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error('Perintah ini saat ini hanya mendukung MySQL/MariaDB karena memakai IGNORE INDEX.');

            return self::FAILURE;
        }

        $lots = (int) $this->option('lots');
        $bidsPerLot = (int) $this->option('bids-per-lot');
        $buyers = (int) $this->option('buyers');
        $iterations = (int) $this->option('iterations');

        $pruneKeepOption = (string) $this->option('prune-keep');
        $pruneKeep = filter_var($pruneKeepOption, FILTER_VALIDATE_INT);
        $pruneDryRun = (bool) $this->option('prune-dry-run');
        $pruneConfirm = (bool) $this->option('prune-confirm');

        if ($pruneKeep === false || (int) $pruneKeep < 0) {
            $this->error('Nilai --prune-keep harus berupa integer >= 0.');

            return self::FAILURE;
        }

        $pruneKeep = (int) $pruneKeep;

        if ($lots < 10 || $bidsPerLot < 10 || $buyers < 5 || $iterations < 10) {
            $this->error('Nilai opsi terlalu kecil. Minimal: lots=10, bids-per-lot=10, buyers=5, iterations=10.');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $now = now();

            $sellerId = DB::table('users')->insertGetId([
                'name' => 'Benchmark Seller',
                'email' => 'bench-seller-' . uniqid() . '@local.test',
                'password' => Hash::make('password'),
                'role' => 'penjual',
                'is_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $buyerIds = [];
            for ($i = 0; $i < $buyers; $i++) {
                $buyerIds[] = DB::table('users')->insertGetId([
                    'name' => 'Benchmark Buyer ' . $i,
                    'email' => 'bench-buyer-' . $i . '-' . uniqid() . '@local.test',
                    'password' => Hash::make('password'),
                    'role' => 'pembeli',
                    'is_admin' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $trackedBuyerId = $buyerIds[0];
            $ikanIds = $this->seedIkanData($sellerId, $lots, $now);
            $this->seedBidData($ikanIds, $buyerIds, $trackedBuyerId, $bidsPerLot, $now);
            $this->seedTransaksiData($ikanIds, $buyerIds, $now);

            $sampleIkanId = $ikanIds[10] ?? $ikanIds[0];

            $results = [
                'Q1 bids latest per lot' => $this->compare(
                    'SELECT MAX(id) AS id FROM bids WHERE user_id = ? GROUP BY ikan_id',
                    'SELECT MAX(id) AS id FROM bids IGNORE INDEX (bids_user_ikan_id_idx) WHERE user_id = ? GROUP BY ikan_id',
                    [$trackedBuyerId],
                    $iterations
                ),
                'Q2 bids my max bid' => $this->compare(
                    'SELECT jumlah_bid FROM bids WHERE ikan_id = ? AND user_id = ? ORDER BY jumlah_bid DESC LIMIT 1',
                    'SELECT jumlah_bid FROM bids IGNORE INDEX (bids_ikan_user_amount_idx) WHERE ikan_id = ? AND user_id = ? ORDER BY jumlah_bid DESC LIMIT 1',
                    [$sampleIkanId, $trackedBuyerId],
                    $iterations
                ),
                'Q3 market list' => $this->compare(
                    "SELECT id, status, tipe_lelang, waktu_selesai FROM ikans WHERE status IN ('aktif', 'menunggu') AND tipe_lelang = 'naik' ORDER BY waktu_selesai ASC LIMIT 12",
                    "SELECT id, status, tipe_lelang, waktu_selesai FROM ikans IGNORE INDEX (ikans_status_tipe_end_idx) WHERE status IN ('aktif', 'menunggu') AND tipe_lelang = 'naik' ORDER BY waktu_selesai ASC LIMIT 12",
                    [],
                    $iterations
                ),
                'Q4 seller dashboard list' => $this->compare(
                    'SELECT id, user_id, tipe_lelang, status, created_at FROM ikans WHERE user_id = ? AND tipe_lelang = ? AND status = ? ORDER BY created_at DESC LIMIT 10',
                    'SELECT id, user_id, tipe_lelang, status, created_at FROM ikans IGNORE INDEX (ikans_user_tipe_status_created_idx) WHERE user_id = ? AND tipe_lelang = ? AND status = ? ORDER BY created_at DESC LIMIT 10',
                    [$sellerId, 'naik', 'aktif'],
                    $iterations
                ),
                'Q5 shipping priority' => $this->compare(
                    "SELECT ikan_id, status, escrow_status, delivery_status, dibayar_pada FROM transaksis WHERE ikan_id IN (SELECT id FROM ikans WHERE user_id = ? AND tipe_lelang = ?) AND status = 'lunas' AND escrow_status = 'ditahan' AND delivery_status IN ('menunggu_pengiriman', 'diproses') ORDER BY dibayar_pada ASC LIMIT 5",
                    "SELECT ikan_id, status, escrow_status, delivery_status, dibayar_pada FROM transaksis IGNORE INDEX (trx_status_escrow_delivery_paid_idx, trx_status_escrow_paid_delivery_ikan_idx) WHERE ikan_id IN (SELECT id FROM ikans IGNORE INDEX (ikans_user_tipe_id_idx) WHERE user_id = ? AND tipe_lelang = ?) AND status = 'lunas' AND escrow_status = 'ditahan' AND delivery_status IN ('menunggu_pengiriman', 'diproses') ORDER BY dibayar_pada ASC LIMIT 5",
                    [$sellerId, 'naik'],
                    $iterations
                ),
            ];

            $summary = [
                'lots' => $lots,
                'bids_per_lot' => $bidsPerLot,
                'buyers' => $buyers,
                'iterations' => $iterations,
                'total_bids' => $lots * $bidsPerLot,
            ];

            $payload = [
                'generated_at' => now()->toIso8601String(),
                'database_driver' => $driver,
                'summary' => $summary,
                'results' => $results,
            ];

            $rows = $this->buildRows($results);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($payload, JSON_PRETTY_PRINT));
            } else {
                $this->info('Dataset sintetis dibuat dalam transaksi benchmark (akan rollback).');
                $this->line(sprintf(
                    'Ringkasan: lots=%d, bids_per_lot=%d, buyers=%d, total_bids=%d, iterations=%d',
                    $summary['lots'],
                    $summary['bids_per_lot'],
                    $summary['buyers'],
                    $summary['total_bids'],
                    $summary['iterations']
                ));

                $this->table([
                    'Query',
                    'Avg With Index (ms)',
                    'Avg Ignore Index (ms)',
                    'Avg Gain (%)',
                    'P95 With Index (ms)',
                    'P95 Ignore Index (ms)',
                    'P95 Gain (%)',
                ], array_map(function (array $row): array {
                    return [
                        'query' => $row['query'],
                        'avg_with_idx_ms' => number_format($row['avg_with_idx_ms'], 3),
                        'avg_ignore_idx_ms' => number_format($row['avg_ignore_idx_ms'], 3),
                        'avg_gain_pct' => number_format($row['avg_gain_pct'], 2),
                        'p95_with_idx_ms' => number_format($row['p95_with_idx_ms'], 3),
                        'p95_ignore_idx_ms' => number_format($row['p95_ignore_idx_ms'], 3),
                        'p95_gain_pct' => number_format($row['p95_gain_pct'], 2),
                    ];
                }, $rows));
            }

            $savedReports = [];
            $shouldSaveReport = (bool) $this->option('save-json') || (bool) $this->option('save-csv');
            $reportBaseName = null;

            if ($shouldSaveReport) {
                $reportBaseName = $this->buildReportBaseName();
            } elseif ((string) $this->option('report-name') !== '') {
                $this->warn('Opsi --report-name diabaikan karena tidak ada --save-json/--save-csv.');
            }

            if ((bool) $this->option('save-json')) {
                $savedReports[] = $this->saveJsonReport($payload, (string) $reportBaseName);
            }

            if ((bool) $this->option('save-csv')) {
                $savedReports[] = $this->saveCsvReport($rows, (string) $reportBaseName);
            }

            foreach ($savedReports as $savedReport) {
                $this->info('Report tersimpan: ' . $savedReport);
            }

            if ($pruneKeep > 0) {
                if ($pruneDryRun) {
                    $pruneSummary = $this->pruneOldReports($pruneKeep, true);

                    $this->info(sprintf(
                        'Dry-run prune: keep %d run terbaru, %d file akan dihapus.',
                        $pruneKeep,
                        $pruneSummary['affected_files']
                    ));

                    $preview = array_slice($pruneSummary['affected_rel_paths'], 0, 10);
                    foreach ($preview as $path) {
                        $this->line('  - ' . $path);
                    }

                    if (count($pruneSummary['affected_rel_paths']) > count($preview)) {
                        $this->line('  ... dan file lainnya');
                    }
                } elseif (! $pruneConfirm) {
                    $this->warn('Prune dibatalkan. Tambahkan --prune-confirm untuk menghapus file lama.');
                } else {
                    $pruneSummary = $this->pruneOldReports($pruneKeep, false);

                    $this->info(sprintf(
                        'Prune report selesai: keep %d run terbaru, %d file lama dihapus.',
                        $pruneKeep,
                        $pruneSummary['affected_files']
                    ));
                }
            } elseif ($pruneDryRun) {
                $this->warn('Opsi --prune-dry-run diabaikan karena --prune-keep tidak lebih dari 0.');
            } elseif ($pruneConfirm) {
                $this->warn('Opsi --prune-confirm diabaikan karena --prune-keep tidak lebih dari 0.');
            }

            DB::rollBack();
            $this->info('Benchmark selesai. Seluruh data sintetis di-rollback.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->error('Benchmark gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function seedIkanData(int $sellerId, int $lots, $now): array
    {
        $ikanIds = [];

        for ($i = 0; $i < $lots; $i++) {
            $status = $i % 5 === 0 ? 'menunggu' : ($i % 7 === 0 ? 'selesai' : 'aktif');
            $tipe = $i % 2 === 0 ? 'naik' : 'turun';
            $hargaAwal = 20000 + ($i * 250);
            $hargaTertinggi = $hargaAwal + (($i % 9) * 1000);

            $ikanIds[] = DB::table('ikans')->insertGetId([
                'user_id' => $sellerId,
                'nama_ikan' => 'BENCH LOT ' . $i,
                'berat' => 10.5,
                'estimasi_jumlah_ekor' => 50,
                'jenis_kemasan' => 'keranjang',
                'kondisi' => 'segar',
                'deskripsi' => 'Benchmark lot',
                'harga_awal' => $hargaAwal,
                'harga_tertinggi' => $hargaTertinggi,
                'minimal_increment' => 1000,
                'buy_now_enabled' => false,
                'buy_now_price' => null,
                'anti_sniping_enabled' => true,
                'anti_sniping_window_seconds' => 120,
                'anti_sniping_extend_seconds' => 120,
                'anti_sniping_max_extensions' => 3,
                'anti_sniping_extensions_used' => 0,
                'tipe_lelang' => $tipe,
                'waktu_mulai' => $now->copy()->subHours(8)->addMinutes($i % 120),
                'waktu_selesai' => $now->copy()->addHours(4)->addMinutes($i % 180),
                'status' => $status,
                'state_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $ikanIds;
    }

    private function seedBidData(array $ikanIds, array $buyerIds, int $trackedBuyerId, int $bidsPerLot, $now): void
    {
        $batchSize = 1500;
        $rows = [];

        foreach ($ikanIds as $idx => $ikanId) {
            for ($j = 0; $j < $bidsPerLot; $j++) {
                $bidderId = $buyerIds[($idx + $j) % count($buyerIds)];
                if ($j % 7 === 0) {
                    $bidderId = $trackedBuyerId;
                }

                $rows[] = [
                    'ikan_id' => $ikanId,
                    'user_id' => $bidderId,
                    'jumlah_bid' => 20000 + ($j * 500) + (($idx % 11) * 100),
                    'is_suspicious' => ($j % 37 === 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= $batchSize) {
                    DB::table('bids')->insert($rows);
                    $rows = [];
                }
            }
        }

        if (! empty($rows)) {
            DB::table('bids')->insert($rows);
        }
    }

    private function seedTransaksiData(array $ikanIds, array $buyerIds, $now): void
    {
        $rows = [];

        foreach (array_slice($ikanIds, 0, min(180, count($ikanIds))) as $idx => $ikanId) {
            $status = $idx % 3 === 0 ? 'menunggu_bayar' : 'lunas';
            $escrow = $status === 'lunas' ? 'ditahan' : 'belum';
            $delivery = $status === 'lunas'
                ? (($idx % 2 === 0) ? 'menunggu_pengiriman' : 'diproses')
                : 'menunggu_pengiriman';

            $rows[] = [
                'ikan_id' => $ikanId,
                'pemenang_id' => $buyerIds[$idx % count($buyerIds)],
                'harga_final' => 50000 + ($idx * 1000),
                'status' => $status,
                'escrow_status' => $escrow,
                'escrow_amount' => 50000 + ($idx * 1000),
                'delivery_status' => $delivery,
                'dibayar_pada' => $status === 'lunas' ? $now->copy()->subHours($idx % 48) : null,
                'bayar_sebelum' => $status === 'menunggu_bayar' ? $now->copy()->addHours(6) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            DB::table('transaksis')->insert($rows);
        }
    }

    private function compare(string $withIndexSql, string $withoutIndexSql, array $bindings, int $iterations): array
    {
        $with = $this->measure($withIndexSql, $bindings, $iterations);
        $without = $this->measure($withoutIndexSql, $bindings, $iterations);

        $avgGain = $without['avg_ms'] > 0
            ? (($without['avg_ms'] - $with['avg_ms']) / $without['avg_ms']) * 100
            : 0;

        $p95Gain = $without['p95_ms'] > 0
            ? (($without['p95_ms'] - $with['p95_ms']) / $without['p95_ms']) * 100
            : 0;

        return [
            'with_index' => $with,
            'without_index' => $without,
            'gain_pct' => [
                'avg' => $avgGain,
                'p95' => $p95Gain,
            ],
        ];
    }

    private function measure(string $sql, array $bindings, int $iterations): array
    {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            DB::select($sql, $bindings);
            $times[] = (hrtime(true) - $start) / 1_000_000;
        }

        sort($times);

        $avg = array_sum($times) / max(1, count($times));
        $p95Index = (int) floor((count($times) - 1) * 0.95);
        $p95 = $times[max(0, $p95Index)] ?? $avg;

        return [
            'avg_ms' => $avg,
            'p95_ms' => $p95,
        ];
    }

    private function buildRows(array $results): array
    {
        $rows = [];

        foreach ($results as $label => $metric) {
            $rows[] = [
                'query' => $label,
                'avg_with_idx_ms' => (float) $metric['with_index']['avg_ms'],
                'avg_ignore_idx_ms' => (float) $metric['without_index']['avg_ms'],
                'avg_gain_pct' => (float) $metric['gain_pct']['avg'],
                'p95_with_idx_ms' => (float) $metric['with_index']['p95_ms'],
                'p95_ignore_idx_ms' => (float) $metric['without_index']['p95_ms'],
                'p95_gain_pct' => (float) $metric['gain_pct']['p95'],
            ];
        }

        return $rows;
    }

    private function buildReportBaseName(): string
    {
        $timestamp = now()->format('Ymd_His');
        $rawName = trim((string) $this->option('report-name'));

        if ($rawName === '') {
            return 'market-query-benchmark-' . $timestamp;
        }

        $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $rawName) ?? '';
        $normalized = trim($normalized, '-_');

        if ($normalized === '') {
            throw new \RuntimeException('Nilai --report-name tidak valid. Gunakan huruf, angka, tanda minus, atau underscore.');
        }

        return strtolower($normalized) . '-' . $timestamp;
    }

    private function saveJsonReport(array $payload, string $baseName): string
    {
        $directory = $this->benchmarkDirectory();
        $filename = $baseName . '.json';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($fullPath, json_encode($payload, JSON_PRETTY_PRINT));

        return $this->storageReportPath($filename);
    }

    private function saveCsvReport(array $rows, string $baseName): string
    {
        $directory = $this->benchmarkDirectory();
        $filename = $baseName . '.csv';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        $handle = fopen($fullPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuat file CSV benchmark.');
        }

        fputcsv($handle, [
            'query',
            'avg_with_idx_ms',
            'avg_ignore_idx_ms',
            'avg_gain_pct',
            'p95_with_idx_ms',
            'p95_ignore_idx_ms',
            'p95_gain_pct',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['query'],
                number_format($row['avg_with_idx_ms'], 6, '.', ''),
                number_format($row['avg_ignore_idx_ms'], 6, '.', ''),
                number_format($row['avg_gain_pct'], 6, '.', ''),
                number_format($row['p95_with_idx_ms'], 6, '.', ''),
                number_format($row['p95_ignore_idx_ms'], 6, '.', ''),
                number_format($row['p95_gain_pct'], 6, '.', ''),
            ]);
        }

        fclose($handle);

        return $this->storageReportPath($filename);
    }

    private function benchmarkDirectory(): string
    {
        $relativeDirectory = str_replace('/', DIRECTORY_SEPARATOR, $this->reportDirectoryRelative());
        $directory = storage_path('app' . DIRECTORY_SEPARATOR . $relativeDirectory);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Gagal membuat direktori benchmark.');
        }

        return $directory;
    }

    private function reportDirectoryRelative(): string
    {
        $raw = trim((string) $this->option('report-dir'));
        $normalized = str_replace('\\', '/', $raw);
        $normalized = trim($normalized, '/');

        if ($normalized === '') {
            $normalized = 'benchmarks';
        }

        if (str_contains($normalized, '..')) {
            throw new \RuntimeException('Nilai --report-dir tidak valid. Path traversal tidak diperbolehkan.');
        }

        if (preg_match('/[^A-Za-z0-9_\-\/]/', $normalized) === 1) {
            throw new \RuntimeException('Nilai --report-dir tidak valid. Gunakan huruf, angka, slash, tanda minus, atau underscore.');
        }

        return $normalized;
    }

    private function storageReportPath(string $filename): string
    {
        return 'storage/app/' . $this->reportDirectoryRelative() . '/' . $filename;
    }

    /**
     * @return array{affected_files:int, affected_rel_paths:array<int,string>}
     */
    private function pruneOldReports(int $keepRuns, bool $dryRun = false): array
    {
        $directory = $this->benchmarkDirectory();
        $jsonReports = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $csvReports = glob($directory . DIRECTORY_SEPARATOR . '*.csv') ?: [];
        $files = array_values(array_merge($jsonReports, $csvReports));

        if ($files === []) {
            return [
                'affected_files' => 0,
                'affected_rel_paths' => [],
            ];
        }

        $runsByBaseName = [];

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $baseName = pathinfo($file, PATHINFO_FILENAME);
            $modifiedAt = filemtime($file) ?: 0;

            if (! isset($runsByBaseName[$baseName]) || $modifiedAt > $runsByBaseName[$baseName]) {
                $runsByBaseName[$baseName] = $modifiedAt;
            }
        }

        arsort($runsByBaseName);

        $keepBaseNames = array_flip(array_slice(array_keys($runsByBaseName), 0, $keepRuns));
        $affectedFiles = 0;
        $affectedRelPaths = [];

        foreach ($files as $file) {
            $baseName = pathinfo($file, PATHINFO_FILENAME);

            if (isset($keepBaseNames[$baseName])) {
                continue;
            }

            if (! is_file($file)) {
                continue;
            }

            if ($dryRun) {
                $affectedFiles++;
                $affectedRelPaths[] = $this->storageRelativePath($file);
                continue;
            }

            if (unlink($file)) {
                $affectedFiles++;
                $affectedRelPaths[] = $this->storageRelativePath($file);
            }
        }

        return [
            'affected_files' => $affectedFiles,
            'affected_rel_paths' => $affectedRelPaths,
        ];
    }

    private function storageRelativePath(string $absolutePath): string
    {
        $storageRoot = str_replace('\\', '/', storage_path('app'));
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalizedPath, $storageRoot . '/')) {
            return 'storage/app/' . ltrim(substr($normalizedPath, strlen($storageRoot)), '/');
        }

        return $absolutePath;
    }
}
