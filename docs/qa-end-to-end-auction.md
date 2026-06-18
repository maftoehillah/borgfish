# QA End-to-End Auction Flow

Gunakan checklist ini untuk QA browser setelah kredensial Google OAuth, WhatsApp provider, dan TriPay sandbox sudah diisi.

## Persiapan

Pastikan OTP WhatsApp memakai provider sungguhan, bukan driver `log`:

```env
WHATSAPP_DRIVER=fonnte
WHATSAPP_SHOW_DEV_OTP=false
FONNTE_ENDPOINT=https://api.fonnte.com/send
FONNTE_TOKEN=isi-token-fonnte-anda
OTP_TTL_MINUTES=5
OTP_MAX_ATTEMPTS=5
OTP_MAX_RESEND=3
OTP_RATE_LIMIT_PER_HOUR=6
OTP_RATE_LIMIT_PER_NUMBER_PER_HOUR=6
OTP_RESEND_COOLDOWN_SECONDS=60
```

Jika memakai provider lain, isi `WHATSAPP_DRIVER=wablas` beserta `WABLAS_TOKEN` dan `WABLAS_SECRET_KEY`, atau gunakan `WHATSAPP_DRIVER=generic` dengan endpoint/token provider Anda.

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan serve
php artisan queue:work database --queue=automation,notifications,default --sleep=2 --tries=3 --timeout=120
```

Di terminal lain jalankan scheduler:

```bash
php artisan schedule:work
```

## Skenario Pembeli

1. Buka `/register`, pilih `Pembeli`, lanjut Google OAuth.
2. Isi nomor WhatsApp pada onboarding.
3. Pastikan OTP masuk ke WhatsApp dan verifikasi berhasil.
4. Buka halaman lot aktif, lakukan bid tanpa saldo.
5. Setelah lelang selesai, buka aktivitas pembeli dan pastikan invoice muncul dengan deadline 30 menit.
6. Klik bayar, pilih metode TriPay sandbox, dan pastikan diarahkan ke checkout/payment instruction.
7. Simulasikan callback paid dari TriPay sandbox, lalu pastikan status order berubah menjadi paid/lunas.
8. Sebelum seller konfirmasi packing, pastikan pembeli hanya melihat status menunggu packing dan belum bisa mengisi data penjemput.
9. Setelah seller konfirmasi packing, isi data penjemput termasuk foto sopir dan foto kendaraan, lalu klik penjemput dalam perjalanan.
10. Setelah seller validasi penjemput datang, review barang dan konfirmasi selesai.

## Skenario Penjual

1. Buka `/register`, pilih `Penjual`, lanjut Google OAuth.
2. Isi nomor WhatsApp, nama toko, lokasi, alamat lengkap, dan informasi toko.
3. Verifikasi OTP WhatsApp.
4. Buat lot lelang baru tanpa mengubah style UI.
5. Setelah pemenang membayar, upload foto packing, lokasi, jam, dan deskripsi opsional.
6. Validasi penjemput datang dengan mencocokkan data pembeli, lalu upload foto sopir, foto kendaraan, dan plat nomor versi penjual.
7. Pastikan status berubah ke menunggu konfirmasi pembeli.

## Skenario Admin

1. Login Google memakai email whitelist admin.
2. Pastikan otomatis masuk dashboard admin setelah OTP.
3. Cek user management, transaksi, lot lelang, payment attempts, pelanggaran, audit log, dan settings.
4. Uji suspend, lepas suspend, ban, dan tambah catatan pelanggaran manual.

## Skenario Negatif

1. Menangkan lelang lalu biarkan melewati 30 menit tanpa bayar.
2. Pastikan scheduler menandai payment expired.
3. Pastikan payment attempt pending ikut expired.
4. Pastikan pelanggaran gagal bayar otomatis tercatat.
5. Pastikan notifikasi deadline dan gagal bayar terkirim ke pembeli/penjual.
