# Manual Book Penjual Borgfish

Dokumen ini menjelaskan cara menggunakan website Borgfish untuk role penjual. Gunakan dokumen ini sebagai bahan manual book pengguna dan panduan pengambilan screenshot.

## Panduan Screenshot

Sebelum mengambil screenshot, gunakan akun penjual yang sudah aktif, sudah melengkapi data toko, sudah verifikasi OTP WhatsApp, dan sudah memiliki beberapa lot contoh. Jika memungkinkan, gunakan ukuran browser desktop sekitar 1366 x 768 agar navbar, kartu statistik, dan form terlihat jelas.

Format nama file screenshot yang disarankan: `penjual-01-login-google.png`, `penjual-02-register-role.png`, dan seterusnya.

Saat screenshot diminta pada bagian tertentu, ambil area layar yang disebutkan saja jika bisa. Jika tidak, ambil full page lalu crop sesuai bagian yang disebutkan.

## Ringkasan Alur Penjual

1. Penjual masuk atau daftar menggunakan Google.
2. Penjual baru wajib memilih role `Penjual`, mengisi nomor WhatsApp, nama toko, alamat lengkap, titik GPS toko, dan foto toko atau etalase ikan.
3. Penjual verifikasi OTP WhatsApp.
4. Penjual membuka `Dashboard Penjual` untuk melihat identitas toko dan history penjualan selesai.
5. Penjual membuka `Aktivitas Lot` untuk memantau lot, order, pembayaran, packing, dan penjemputan.
6. Penjual upload lot ikan melalui tombol `Upload Ikan`.
7. Sistem menjalankan lelang sampai selesai, lalu pembeli pemenang membayar invoice.
8. Setelah pembayaran lunas, penjual melakukan packing dan validasi penjemputan.
9. Setelah pembeli konfirmasi selesai, transaksi masuk ke history penjualan selesai.

## 1. Masuk Akun Penjual

Halaman masuk berada di `/login`. Penjual lama masuk dengan tombol `Pilih akun Google untuk masuk`. Akun penjual yang sudah terdaftar akan masuk sesuai role yang tersimpan.

Screenshot yang harus diambil: bagian card login yang berisi judul `Masuk ke Borgfish`, tombol `Pilih akun Google untuk masuk`, kotak `Alur masuk`, dan link `Belum punya akun? Pilih role dan daftar`.

Nama file disarankan: `penjual-01-login-google.png`.

## 2. Daftar Akun Penjual

Halaman daftar berada di `/register`. Untuk membuat akun penjual, pilih role `Penjual`, lalu klik tombol `Pilih akun Google`.

Screenshot yang harus diambil: bagian card `Buat Akun Borgfish`, pilihan role `Pembeli` dan `Penjual`, kotak penjelasan `Setelah memilih Google`, tombol `Pilih akun Google`, dan catatan bahwa email admin whitelist bukan untuk pendaftaran pembeli/penjual.

Nama file disarankan: `penjual-02-register-role.png`.

## 3. Memilih Akun Google

Setelah klik tombol Google, browser akan diarahkan ke halaman pemilihan akun Google. Pilih akun Google yang akan digunakan sebagai penjual.

Screenshot yang harus diambil: halaman Google Account Chooser yang menampilkan daftar akun Google di perangkat. Jika dokumentasi akan dibagikan publik, blur email pribadi sebelum digunakan.

Nama file disarankan: `penjual-03-google-account-chooser.png`.

## 4. Lengkapi Data Akun Penjual

Setelah akun Google baru berhasil dipilih, penjual diarahkan ke `/auth/onboarding`. Penjual wajib mengisi nomor WhatsApp dan seluruh data toko sebelum bisa masuk dashboard.

Data wajib penjual adalah `Nomor WhatsApp`, `Nama Toko`, `Alamat Lengkap Toko`, `Titik GPS Toko`, dan `Foto Toko / Box / Etalase Ikan`.

Screenshot yang harus diambil: bagian atas `Lengkapi Data Akun` yang berisi field `Nomor WhatsApp`, informasi bahwa data toko wajib lengkap, field `Nama Toko`, dan field `Alamat Lengkap Toko`.

Nama file disarankan: `penjual-04-onboarding-data-toko.png`.

Screenshot tambahan yang harus diambil: bagian `Titik GPS Toko` yang berisi tombol `Ambil Titik GPS`, status titik GPS, latitude, longitude, dan akurasi jika sudah berhasil.

Nama file disarankan: `penjual-05-onboarding-gps.png`.

Screenshot tambahan yang harus diambil: bagian `Foto Toko / Box / Etalase Ikan`, kotak `Langkah berikutnya: OTP WhatsApp`, dan tombol `Simpan dan Kirim OTP`.

Nama file disarankan: `penjual-06-onboarding-foto-otp.png`.

## 5. Verifikasi OTP WhatsApp

Setelah data toko valid, sistem mengirim OTP ke nomor WhatsApp penjual. Penjual masuk ke `/auth/otp`, mengisi kode OTP, lalu melanjutkan ke dashboard.

Screenshot yang harus diambil: bagian `Verifikasi WhatsApp` yang berisi nomor WhatsApp tersamarkan, field `Kode OTP`, tombol verifikasi, dan tombol `Kirim ulang OTP`.

Nama file disarankan: `penjual-07-otp-whatsapp.png`.

## 6. Navigasi Utama Penjual

Setelah login berhasil, penjual melihat navbar utama. Menu penting untuk penjual adalah `Marketplace`, `Dashboard Penjual`, `Aktivitas Lot`, `+ Upload Ikan`, ikon notifikasi, profil pengguna, dan logout.

Screenshot yang harus diambil: area navbar bagian atas dari logo Borgfish sampai menu user. Pastikan `Dashboard Penjual`, `Aktivitas Lot`, dan `+ Upload Ikan` terlihat.

Nama file disarankan: `penjual-08-navbar.png`.

## 7. Dashboard Penjual

Halaman `Dashboard Penjual` berada di `/penjual/dashboard`. Halaman ini menampilkan identitas toko, foto toko, titik GPS, alamat lengkap, statistik penjualan selesai, dan history penjualan yang sudah dikonfirmasi selesai oleh pembeli.

Screenshot yang harus diambil: bagian hero `Dashboard Penjual` yang menampilkan nama toko, tombol `Aktivitas Lot`, tombol `Upload Ikan`, dan kartu statistik `Penjualan Selesai`, `Nilai Penjualan Selesai`, serta `Rating Masuk`.

Nama file disarankan: `penjual-09-dashboard-hero.png`.

Screenshot tambahan yang harus diambil: bagian `Identitas Toko` yang menampilkan foto toko, nama penjual, nomor WhatsApp, nama toko, titik GPS, akurasi, dan alamat lengkap.

Nama file disarankan: `penjual-10-dashboard-identitas-toko.png`.

Screenshot tambahan yang harus diambil: bagian `History Penjualan Selesai` yang menampilkan lot selesai, pembeli, harga final, rating, review, dan foto packing atau penjemputan.

Nama file disarankan: `penjual-11-dashboard-history-penjualan.png`.

## 8. Aktivitas Lot

Halaman `Aktivitas Lot` berada di `/penjual/ikans`. Halaman ini dipakai untuk memantau lot milik penjual, status pembayaran, packing, penjemputan, dan aksi operasional.

Screenshot yang harus diambil: bagian atas `Aktivitas Lot` yang menampilkan kartu statistik seperti `Total Lot`, `Menunggu Tayang`, `Perlu Penjemputan`, dan `Lot Aktif`.

Nama file disarankan: `penjual-12-aktivis-lot-hero.png`.

Screenshot tambahan yang harus diambil: bagian prioritas tiga kolom `Siapkan Packing`, `Penjemputan`, dan `Selesai`.

Nama file disarankan: `penjual-13-aktivis-lot-prioritas.png`.

Screenshot tambahan yang harus diambil: bagian filter tipe lelang yang menampilkan pilihan semua lelang, lelang naik, dan lelang turun.

Nama file disarankan: `penjual-14-aktivis-lot-filter.png`.

Screenshot tambahan yang harus diambil: salah satu kartu lot yang menampilkan nama lot, berat, kondisi, badge status, harga saat ini, waktu selesai, status pembayaran, fulfillment, dan tombol `Detail`, `Upload Ulang`, `Edit`, atau `Hapus`.

Nama file disarankan: `penjual-15-aktivis-lot-kartu.png`.

## 9. Upload Ikan Baru

Klik tombol `+ Upload Ikan` di navbar atau `Upload Ikan` di dashboard. Halaman upload berada di `/penjual/ikans/create`. Form upload terdiri dari dua step: `Produk & Media` dan `Pengaturan Lelang`.

Screenshot yang harus diambil: bagian hero `Upload Ikan Baru`, tombol `Kembali`, deskripsi form, dan tombol step `1. Produk & Media` serta `2. Pengaturan Lelang`.

Nama file disarankan: `penjual-16-upload-hero-step.png`.

Screenshot tambahan yang harus diambil: step `Produk & Media` yang menampilkan field foto ikan, video ikan jika ada, nama ikan, berat, estimasi jumlah ekor, kondisi, jenis kemasan, asal pelabuhan, tanggal tangkap, metode tangkap, grade mutu, suhu penyimpanan, surveyor, catatan survey, dan deskripsi.

Nama file disarankan: `penjual-17-upload-produk-media.png`.

Screenshot tambahan yang harus diambil: step `Pengaturan Lelang` yang menampilkan tipe lelang, harga awal, reserve price jika lelang turun, minimal increment, Buy Now, anti-sniping, waktu mulai, waktu selesai, dan tombol `Upload Ikan ke Lelang`.

Nama file disarankan: `penjual-18-upload-pengaturan-lelang.png`.

## 10. Edit dan Upload Ulang Lot

Pada halaman `Aktivitas Lot`, penjual dapat memakai tombol `Edit` jika lot belum aktif dan belum mulai. Tombol `Upload Ulang` menyalin data lot lama ke form upload baru agar penjual tidak perlu mengetik ulang semua data.

Screenshot yang harus diambil: kartu lot di `Aktivitas Lot` yang menampilkan tombol `Upload Ulang`, tombol `Edit`, dan status `Edit Terkunci` jika lelang sudah berjalan.

Nama file disarankan: `penjual-19-edit-upload-ulang.png`.

Screenshot tambahan yang harus diambil: halaman upload yang menampilkan banner `Mode Upload Ulang Aktif` saat penjual membuka upload ulang.

Nama file disarankan: `penjual-20-mode-upload-ulang.png`.

## 11. Detail Lot Penjual

Klik tombol `Detail` pada salah satu lot untuk membuka `/penjual/ikans/{ikan}`. Halaman ini menampilkan panel detail lot untuk operasional penjual: edit, monitor bid, pembayaran, packing, dan penjemputan.

Screenshot yang harus diambil: bagian atas detail lot yang menampilkan tombol `Kembali`, nama lot, deskripsi operasional, badge kondisi, badge status, tipe lelang, jumlah bid, dan harga.

Nama file disarankan: `penjual-21-detail-lot-header.png`.

Screenshot tambahan yang harus diambil: bagian `Spesifikasi Lot` yang menampilkan foto ikan, video jika ada, berat, estimasi ekor, harga awal, harga saat ini, aturan bid, Buy Now, kebijakan pembayaran, state lelang, anti-sniping, waktu mulai, dan waktu selesai.

Nama file disarankan: `penjual-22-detail-lot-spesifikasi.png`.

Screenshot tambahan yang harus diambil: bagian `Aksi Penjual` yang menampilkan tombol `Edit Ikan`, `Hapus Ikan`, atau status terkunci.

Nama file disarankan: `penjual-23-detail-lot-aksi.png`.

Screenshot tambahan yang harus diambil: bagian `Semua Bid` yang menampilkan daftar bid, bidder tersamarkan saat lelang aktif, waktu bid, dan nominal bid terbaik.

Nama file disarankan: `penjual-24-detail-lot-semua-bid.png`.

## 12. Info Pembayaran Setelah Ada Pemenang

Jika lot sudah selesai dan memiliki transaksi, halaman detail lot menampilkan `Info Pembayaran`. Penjual dapat melihat pemenang, harga final, Order ID, status penjemputan, fulfillment, Payment ID terakhir, status bayar, deadline bayar, dan waktu pembayaran.

Screenshot yang harus diambil: bagian `Info Pembayaran` lengkap dengan pemenang, harga final, Order ID, status bayar, payment ID, dan status fulfillment.

Nama file disarankan: `penjual-25-info-pembayaran.png`.

## 13. Konfirmasi Packing

Setelah pembeli membayar dan status transaksi lunas, penjual melakukan packing. Pada bagian `Aksi Packing & Penjemputan`, penjual mengisi bukti packing, lokasi packing, jam packing, dan deskripsi opsional, lalu klik `Simpan Packing`.

Screenshot yang harus diambil: form `Konfirmasi Packing` yang berisi upload `Bukti Packing (foto)`, field `Lokasi Packing`, field `Jam Packing`, field `Deskripsi Opsional`, dan tombol `Simpan Packing`.

Nama file disarankan: `penjual-26-konfirmasi-packing.png`.

## 14. Menunggu Data Penjemput Pembeli

Jika packing sudah dikonfirmasi tetapi pembeli belum mengisi data penjemput, sistem menampilkan pesan `Menunggu data penjemput dari pembeli`.

Screenshot yang harus diambil: bagian `Packing sudah dikonfirmasi` dan kotak status `Menunggu data penjemput dari pembeli`.

Nama file disarankan: `penjual-27-menunggu-data-penjemput.png`.

## 15. Validasi Penjemput Datang

Setelah pembeli mengisi data penjemput, penjual melihat nama sopir, plat nomor, foto sopir dari pembeli, dan foto kendaraan dari pembeli. Saat penjemput datang, penjual wajib mencocokkan data tersebut, mengisi nama sopir, plat nomor, upload foto sopir validasi penjual, upload foto kendaraan validasi penjual, lalu klik `Validasi Penjemput Datang`.

Screenshot yang harus diambil: bagian `Data penjemput pembeli` yang menampilkan sopir, plat, link foto sopir dari pembeli, dan link foto kendaraan dari pembeli.

Nama file disarankan: `penjual-28-data-penjemput-pembeli.png`.

Screenshot tambahan yang harus diambil: form `Penjemput Datang` yang berisi input nama sopir, input plat nomor, upload `Foto Sopir`, upload `Foto Kendaraan`, dan tombol `Validasi Penjemput Datang`.

Nama file disarankan: `penjual-29-validasi-penjemput-datang.png`.

## 16. Foto Packing dan Penjemputan

Setelah packing atau penjemputan diproses, halaman detail lot menampilkan komponen `Foto Packing & Penjemputan`. Komponen ini memudahkan penjual melihat bukti yang sudah diupload pada setiap tahap.

Screenshot yang harus diambil: komponen `Foto Packing & Penjemputan` yang menampilkan thumbnail bukti packing, foto sopir dari pembeli, foto kendaraan dari pembeli, foto sopir validasi penjual, dan foto kendaraan validasi penjual jika tersedia.

Nama file disarankan: `penjual-30-foto-packing-penjemputan.png`.

## 17. Menunggu Konfirmasi Pembeli

Setelah penjemput divalidasi, transaksi menunggu pembeli melakukan review dan konfirmasi selesai. Di `Aktivitas Lot`, transaksi akan terlihat di kolom `Selesai` dengan status menunggu konfirmasi buyer.

Screenshot yang harus diambil: kolom `Selesai` di halaman `Aktivitas Lot` yang menampilkan lot dengan badge `Menunggu Konfirmasi Buyer` dan tombol `Lihat Detail`.

Nama file disarankan: `penjual-31-menunggu-konfirmasi-buyer.png`.

## 18. Penjualan Selesai

Setelah pembeli melakukan konfirmasi selesai, lot tersebut keluar dari `Aktivitas Lot` dan masuk ke `Dashboard Penjual` pada bagian `History Penjualan Selesai`.

Screenshot yang harus diambil: bagian `History Penjualan Selesai` di dashboard penjual yang menampilkan lot selesai, harga final, rating pembeli, review pembeli, dan foto bukti.

Nama file disarankan: `penjual-32-penjualan-selesai-history.png`.

## 19. Halaman Toko Publik Penjual

Pembeli dapat membuka halaman toko publik penjual dari chip `Toko Penjual` di detail lot. Halaman publik berada di `/toko/{seller}` dan menampilkan identitas toko serta isi toko, tanpa tombol internal seperti `Aktivitas Lot` dan `Upload Ikan`.

Screenshot yang harus diambil: halaman `Dashboard Toko Penjual` dari sisi pembeli, terutama bagian foto toko, nama toko, statistik `Lot Tayang`, `Lot Selesai`, dan `Total Lot`.

Nama file disarankan: `penjual-33-toko-publik-header.png`.

Screenshot tambahan yang harus diambil: bagian `Identitas Toko`, `Isi Toko`, dan `Riwayat Lot Selesai` dari halaman toko publik.

Nama file disarankan: `penjual-34-toko-publik-isi.png`.

## 20. Notifikasi Penjual

Ikon notifikasi berada di navbar. Penjual menerima notifikasi untuk pembayaran sukses, packing, penjemputan, pesanan selesai, dan pelanggaran jika ada.

Screenshot yang harus diambil: popover notifikasi setelah ikon lonceng diklik, termasuk judul `Notifikasi`, daftar notifikasi, tombol `Buka`, tombol `Dibaca`, dan link `Lihat semua notifikasi`.

Nama file disarankan: `penjual-35-notifikasi-popover.png`.

Screenshot tambahan yang harus diambil: halaman `/notifikasi` yang menampilkan daftar notifikasi lengkap.

Nama file disarankan: `penjual-36-notifikasi-halaman.png`.

## 21. Profil dan Hapus Akun

Penjual dapat membuka halaman profil untuk memperbarui informasi dasar. Karena akun memakai Google dan tidak memakai password manual, penghapusan akun memakai OTP WhatsApp. Jika penjual masih memiliki transaksi aktif, sistem dapat menolak penghapusan akun sampai transaksi selesai.

Screenshot yang harus diambil: halaman profil bagian data akun dan form penghapusan akun berbasis OTP WhatsApp.

Nama file disarankan: `penjual-37-profil-hapus-akun-otp.png`.

## 22. Catatan Penting untuk Penjual

Penjual tidak memiliki saldo internal dan tidak melakukan withdraw dari sistem. Pembayaran pembeli diproses melalui TriPay dan status order dipantau dari transaksi. Fokus penjual adalah upload lot, pantau pembayaran, packing, validasi penjemputan, dan melihat history penjualan selesai.

Screenshot yang harus diambil: gunakan halaman `Aktivitas Lot` atau `Detail Lot Penjual` yang menampilkan status pembayaran gateway dan tidak ada menu saldo/top up/withdraw.

Nama file disarankan: `penjual-38-tanpa-saldo-withdraw.png`.

## Checklist Screenshot Penjual

| No | Nama File | Bagian Layar |
| --- | --- | --- |
| 1 | `penjual-01-login-google.png` | Card login Google |
| 2 | `penjual-02-register-role.png` | Card daftar role |
| 3 | `penjual-03-google-account-chooser.png` | Pilihan akun Google |
| 4 | `penjual-04-onboarding-data-toko.png` | Form data toko awal |
| 5 | `penjual-05-onboarding-gps.png` | Ambil Titik GPS |
| 6 | `penjual-06-onboarding-foto-otp.png` | Foto toko dan Simpan OTP |
| 7 | `penjual-07-otp-whatsapp.png` | Form OTP WhatsApp |
| 8 | `penjual-08-navbar.png` | Navbar penjual |
| 9 | `penjual-09-dashboard-hero.png` | Hero Dashboard Penjual |
| 10 | `penjual-10-dashboard-identitas-toko.png` | Identitas toko |
| 11 | `penjual-11-dashboard-history-penjualan.png` | History penjualan selesai |
| 12 | `penjual-12-aktivitas-lot-hero.png` | Hero Aktivitas Lot |
| 13 | `penjual-13-aktivis-lot-prioritas.png` | Siapkan Packing, Penjemputan, Selesai |
| 14 | `penjual-14-aktivis-lot-filter.png` | Filter tipe lelang |
| 15 | `penjual-15-aktivis-lot-kartu.png` | Kartu lot dan tombol aksi |
| 16 | `penjual-16-upload-hero-step.png` | Hero Upload Ikan Baru |
| 17 | `penjual-17-upload-produk-media.png` | Step Produk & Media |
| 18 | `penjual-18-upload-pengaturan-lelang.png` | Step Pengaturan Lelang |
| 19 | `penjual-19-edit-upload-ulang.png` | Tombol Edit dan Upload Ulang |
| 20 | `penjual-20-mode-upload-ulang.png` | Banner Mode Upload Ulang Aktif |
| 21 | `penjual-21-detail-lot-header.png` | Header Detail Lot Penjual |
| 22 | `penjual-22-detail-lot-spesifikasi.png` | Spesifikasi Lot |
| 23 | `penjual-23-detail-lot-aksi.png` | Aksi Penjual |
| 24 | `penjual-24-detail-lot-semua-bid.png` | Semua Bid |
| 25 | `penjual-25-info-pembayaran.png` | Info Pembayaran |
| 26 | `penjual-26-konfirmasi-packing.png` | Form Konfirmasi Packing |
| 27 | `penjual-27-menunggu-data-penjemput.png` | Menunggu data penjemput |
| 28 | `penjual-28-data-penjemput-pembeli.png` | Data penjemput pembeli |
| 29 | `penjual-29-validasi-penjemput-datang.png` | Form validasi penjemput |
| 30 | `penjual-30-foto-packing-penjemputan.png` | Foto Packing & Penjemputan |
| 31 | `penjual-31-menunggu-konfirmasi-buyer.png` | Kolom Selesai menunggu buyer |
| 32 | `penjual-32-penjualan-selesai-history.png` | History penjualan selesai |
| 33 | `penjual-33-toko-publik-header.png` | Header toko publik |
| 34 | `penjual-34-toko-publik-isi.png` | Identitas dan isi toko publik |
| 35 | `penjual-35-notifikasi-popover.png` | Popover notifikasi |
| 36 | `penjual-36-notifikasi-halaman.png` | Halaman notifikasi |
| 37 | `penjual-37-profil-hapus-akun-otp.png` | Profil dan hapus akun OTP |
| 38 | `penjual-38-tanpa-saldo-withdraw.png` | Bukti tanpa saldo dan withdraw |
