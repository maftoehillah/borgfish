# Production Queue & Scheduler

Dokumen ini menyiapkan proses background untuk Borgfish agar expiry payment, reminder deadline pembayaran, auto pelanggaran gagal bayar, dan notifikasi berjalan stabil di production.

## Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.test

QUEUE_CONNECTION=database
CACHE_STORE=database

PAYMENT_GATEWAY=tripay
TRIPAY_ENVIRONMENT=sandbox
TRIPAY_SANDBOX_API_KEY=
TRIPAY_SANDBOX_PRIVATE_KEY=
TRIPAY_SANDBOX_MERCHANT_CODE=
TRIPAY_PRODUCTION_API_KEY=
TRIPAY_PRODUCTION_PRIVATE_KEY=
TRIPAY_PRODUCTION_MERCHANT_CODE=
TRIPAY_CALLBACK_URL="${APP_URL}/api/tripay/callback"

WHATSAPP_DRIVER=fonnte
WHATSAPP_SHOW_DEV_OTP=false
FONNTE_ENDPOINT=https://api.fonnte.com/send
FONNTE_TOKEN=
OTP_TTL_MINUTES=5
OTP_MAX_ATTEMPTS=5
OTP_MAX_RESEND=3
OTP_RATE_LIMIT_PER_HOUR=6
OTP_RATE_LIMIT_PER_NUMBER_PER_HOUR=6
OTP_RESEND_COOLDOWN_SECONDS=60

AUCTION_PAYMENT_DEADLINE_MINUTES=30
PAYMENT_DEADLINE_REMINDER_MINUTES=10
```

Gunakan `TRIPAY_ENVIRONMENT=sandbox` untuk testing dan ubah ke `live` setelah callback sandbox, signature, dan flow order selesai diuji. Jika ingin memakai satu set kredensial aktif saja, isi `TRIPAY_API_KEY`, `TRIPAY_PRIVATE_KEY`, dan `TRIPAY_MERCHANT_CODE`.

## Cron Scheduler

Tambahkan satu cron entry di server:

```cron
* * * * * cd /path/to/Borgfish && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler akan mengirim job berikut ke queue setiap menit:

- `RunAuctionAutomationJob` untuk aktivasi/tutup lelang, expiry payment, auto pelanggaran gagal bayar, dan reminder deadline.
- `ProcessNotificationOutboxJob` untuk mengirim outbox notifikasi in-app.
- `queue:prune-failed --hours=168` setiap hari untuk membersihkan failed job lama.

## Supervisor Worker

Contoh konfigurasi Supervisor untuk queue database:

```ini
[program:borgfish-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/Borgfish/artisan queue:work database --queue=automation,notifications,default --sleep=2 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/Borgfish/storage/logs/worker.log
stopwaitsecs=180
```

Setelah membuat file Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start borgfish-worker:*
```

## Deploy Checklist

- Jalankan `php artisan migrate --force`.
- Jalankan `php artisan optimize:clear` lalu `php artisan optimize`.
- Pastikan cron scheduler aktif dengan `php artisan schedule:list`.
- Pastikan worker hidup dengan `php artisan queue:monitor automation,notifications,default --max=100`.
- Set callback TriPay ke `https://domain-anda.test/api/tripay/callback`.
- Uji callback sandbox melalui simulator TriPay sebelum mengubah `TRIPAY_ENVIRONMENT=live`.
