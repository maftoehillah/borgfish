# Manual Book Pembeli Borgfish

Dokumen ini menjelaskan cara menggunakan website Borgfish untuk role pembeli. Gunakan dokumen ini sebagai bahan manual book pengguna dan panduan pengambilan screenshot.

## Panduan Screenshot

Sebelum mengambil screenshot, gunakan akun pembeli yang sudah aktif dan sudah verifikasi OTP WhatsApp. Jika memungkinkan, gunakan ukuran browser desktop sekitar 1366 x 768 agar tampilan navbar dan kartu utama terlihat jelas.

Format nama file screenshot yang disarankan: `pembeli-01-login-google.png`, `pembeli-02-register-role.png`, dan seterusnya.

Saat screenshot diminta pada bagian tertentu, ambil area layar yang disebutkan saja jika bisa. Jika tidak, ambil full page lalu crop sesuai bagian yang disebutkan.

## Ringkasan Alur Pembeli

1. Pembeli masuk atau daftar menggunakan Google.
2. Pembeli baru wajib memilih role `Pembeli`, mengisi nomor WhatsApp, lalu verifikasi OTP WhatsApp.
3. Pembeli membuka Marketplace, memilih lot, melihat detail lot, lalu memasang bid.
4. Jika menang, pembeli membayar invoice melalui TriPay sebelum deadline.
5. Setelah pembayaran sukses, pembeli mengisi data penjemput.
6. Pembeli memantau packing dan penjemputan di halaman Aktivitas Bid.
7. Setelah barang diterima, pembeli memberi rating/review dan konfirmasi selesai.
8. Pesanan selesai masuk ke halaman Riwayat Pembelian.

## 1. Masuk Akun Pembeli

Halaman masuk berada di `/login`. Pembeli lama masuk dengan tombol `Pilih akun Google untuk masuk`. Google akan menampilkan pilihan akun terlebih dahulu.

Screenshot yang harus diambil: bagian card login yang berisi judul `Masuk ke Borgfish`, deskripsi Google, tombol `Pilih akun Google untuk masuk`, kotak `Alur masuk`, dan link `Belum punya akun? Pilih role dan daftar`.

Nama file disarankan: `pembeli-01-login-google.png`.

## 2. Daftar Akun Pembeli

Halaman daftar berada di `/register`. Untuk membuat akun pembeli, pilih role `Pembeli`, lalu klik tombol `Pilih akun Google`.

Screenshot yang harus diambil: bagian card `Buat Akun Borgfish`, pilihan role `Pembeli` dan `Penjual`, kotak penjelasan `Setelah memilih Google`, tombol `Pilih akun Google`, dan link `Sudah punya akun?`.

Nama file disarankan: `pembeli-02-register-role.png`.

## 3. Memilih Akun Google

Setelah klik tombol Google, browser akan diarahkan ke halaman pemilihan akun Google. Pilih akun Google yang akan digunakan sebagai pembeli.

Screenshot yang harus diambil: halaman Google Account Chooser yang menampilkan daftar akun Google di perangkat. Pastikan email terlihat hanya jika aman untuk dokumentasi; jika tidak, blur email sebelum dipakai di manual publik.

Nama file disarankan: `pembeli-03-google-account-chooser.png`.

## 4. Lengkapi Data Akun Pembeli

Setelah akun Google baru berhasil dipilih, pembeli diarahkan ke `/auth/onboarding`. Pembeli wajib mengisi `Nomor WhatsApp`. Sistem tidak meminta password karena login memakai Google.

Screenshot yang harus diambil: bagian `Lengkapi Data Akun` yang berisi field `Nomor WhatsApp`, kotak `Langkah berikutnya: OTP WhatsApp`, dan tombol `Simpan dan Kirim OTP`.

Nama file disarankan: `pembeli-04-onboarding-whatsapp.png`.

## 5. Verifikasi OTP WhatsApp

Setelah nomor WhatsApp disimpan, pembeli masuk ke halaman `/auth/otp`. Masukkan kode OTP yang dikirim ke WhatsApp, lalu klik tombol verifikasi. Jika kode belum diterima, gunakan tombol `Kirim ulang OTP` setelah cooldown selesai.

Screenshot yang harus diambil: bagian `Verifikasi WhatsApp` yang berisi nomor WhatsApp tersamarkan, field `Kode OTP`, tombol verifikasi, dan tombol `Kirim ulang OTP`.

Nama file disarankan: `pembeli-05-otp-whatsapp.png`.

## 6. Navigasi Utama Pembeli

Setelah login berhasil, pembeli melihat navbar utama. Menu yang penting untuk pembeli adalah `Marketplace`, `Aktivitas Bid`, `Riwayat`, ikon notifikasi, profil pengguna, dan logout.

Screenshot yang harus diambil: area navbar bagian atas dari logo Borgfish sampai menu user. Pastikan menu `Marketplace`, `Aktivitas Bid`, dan `Riwayat` terlihat.

Nama file disarankan: `pembeli-06-navbar.png`.

## 7. Marketplace Lelang Ikan

Halaman Marketplace berada di `/ikans`. Di halaman ini pembeli dapat melihat daftar lot yang sedang berlangsung, lot menunggu tayang, dan riwayat lelang selesai. Kartu statistik di atas dapat dipakai sebagai filter cepat.

Screenshot yang harus diambil: bagian hero `Marketplace Lelang Ikan`, kartu statistik seperti `Lot Aktif`, `Menunggu Tayang`, `Segera Berakhir`, `Lelang Selesai`, dan area filter tipe lelang jika terlihat.

Nama file disarankan: `pembeli-07-marketplace-hero.png`.

Screenshot tambahan yang harus diambil: bagian daftar `Lelang Sedang Berlangsung` yang menampilkan beberapa kartu lot dan tombol `Lihat Detail`.

Nama file disarankan: `pembeli-08-marketplace-lot-list.png`.

## 8. Detail Lot

Klik `Lihat Detail` pada salah satu lot untuk membuka halaman detail lot di `/ikans/{id}`. Pembeli dapat melihat tombol `Kembali ke daftar`, chip `Toko Penjual`, nama lot, status lot, spesifikasi, riwayat bid, countdown, harga tertinggi, dan form bid.

Screenshot yang harus diambil: bagian paling atas detail lot yang berisi tombol `Kembali ke daftar`, chip `Toko Penjual` dengan foto bulat dan nama toko, judul lot, status lot, dan badge tipe lelang.

Nama file disarankan: `pembeli-09-detail-lot-header-toko.png`.

Screenshot tambahan yang harus diambil: bagian `Spesifikasi Lot` yang menampilkan harga awal, aturan bid atau minimal increment, waktu mulai, waktu selesai, kemasan, Buy Now, dan data lot lainnya.

Nama file disarankan: `pembeli-10-detail-lot-spesifikasi.png`.

Screenshot tambahan yang harus diambil: panel kanan yang menampilkan harga tertinggi, countdown `Berakhir dalam`, tombol atau panel `Buy Now Instan` jika ada, dan form `Pasang Bid`.

Nama file disarankan: `pembeli-11-detail-lot-bid-panel.png`.

## 9. Melihat Dashboard Toko Penjual

Pada detail lot, klik chip `Toko Penjual`. Pembeli akan masuk ke halaman `/toko/{seller}`. Halaman ini menampilkan identitas toko, foto toko, titik GPS, isi toko, dan riwayat lot selesai. Halaman ini tidak menampilkan tombol internal penjual seperti `Aktivitas Lot` atau `Upload Ikan`.

Screenshot yang harus diambil: bagian atas halaman `Dashboard Toko Penjual` yang menampilkan foto toko bulat, nama toko, statistik `Lot Tayang`, `Lot Selesai`, dan `Total Lot`.

Nama file disarankan: `pembeli-12-dashboard-toko-penjual.png`.

Screenshot tambahan yang harus diambil: bagian `Identitas Toko` dan `Isi Toko` yang menampilkan alamat, titik GPS, status toko, dan kartu lot yang tersedia.

Nama file disarankan: `pembeli-13-dashboard-toko-isi.png`.

## 10. Memasang Bid

Pada halaman detail lot yang masih aktif, isi nominal bid pada panel `Pasang Bid`, lalu klik tombol `Pasang Bid`. Sistem akan memvalidasi bid agar sesuai aturan lelang dan mencegah manipulasi.

Screenshot yang harus diambil: panel `Pasang Bid`, informasi nominal minimal, input nominal bid, dan tombol `Pasang Bid`.

Nama file disarankan: `pembeli-14-pasang-bid.png`.

## 11. Memantau Status Bid

Setelah memasang bid, pembeli dapat melihat status bid di detail lot dan di halaman `Aktivitas Bid`. Jika pembeli sedang memimpin, sistem menampilkan informasi bahwa pembeli berada di posisi terbaik.

Screenshot yang harus diambil: bagian `Status Bid Anda` pada halaman detail lot setelah bid berhasil dipasang.

Nama file disarankan: `pembeli-15-status-bid-detail.png`.

## 12. Jika Menang Lelang

Setelah waktu lelang selesai, sistem menentukan pemenang berdasarkan bid terbaik. Jika pembeli menang, halaman detail lot menampilkan panel `Pemenang Lelang` dan tombol `Bayar Sekarang`.

Screenshot yang harus diambil: panel `Pemenang Lelang` yang berisi nama pemenang, harga final, status pembayaran, status penjemputan, dan tombol `Bayar Sekarang`.

Nama file disarankan: `pembeli-16-menang-lelang.png`.

## 13. Pembayaran Invoice

Halaman pembayaran berada di `/pembayaran/{transaksi}`. Di halaman ini pembeli melihat detail lot, `Order ID`, status payment, deadline pembayaran, pilihan metode TriPay, dan tombol `Buat Invoice dan Bayar Sekarang`.

Screenshot yang harus diambil: bagian `Selesaikan Pembayaran` dari atas sampai ringkasan lot, total tagihan, `Order ID`, dan countdown `Bayar sebelum`.

Nama file disarankan: `pembeli-17-pembayaran-ringkasan.png`.

Screenshot tambahan yang harus diambil: bagian `Metode Pembayaran` yang menampilkan pilihan kanal pembayaran dan tombol `Buat Invoice dan Bayar Sekarang`.

Nama file disarankan: `pembeli-18-pembayaran-metode.png`.

## 14. Pembayaran Berhasil

Jika pembayaran berhasil, sistem menampilkan status `Pembayaran Berhasil`. Pembeli kemudian melanjutkan ke pengisian data penjemputan dari halaman aktivitas.

Screenshot yang harus diambil: halaman atau panel `Pembayaran Berhasil` yang menampilkan status sukses dan tombol menuju detail atau aktivitas.

Nama file disarankan: `pembeli-19-pembayaran-berhasil.png`.

## 15. Aktivitas Bid Saya

Halaman `Aktivitas Bid` berada di `/pembeli/aktivitas`. Halaman ini menjadi pusat kontrol pembeli untuk memantau bid, tagihan, packing, penjemputan, dan konfirmasi selesai.

Screenshot yang harus diambil: bagian hero `Aktivitas Bid Saya`, badge `memimpin`, `menunggu bayar`, `perlu konfirmasi`, serta kartu statistik `Lot Diikuti`, `Memimpin Aktif`, `Sudah Lunas`, dan `Tagihan Berjalan`.

Nama file disarankan: `pembeli-20-aktivitas-hero.png`.

Screenshot tambahan yang harus diambil: bagian pipeline tiga kolom `1. Bayar`, `2. Penjemputan`, dan `3. Selesai`.

Nama file disarankan: `pembeli-21-aktivitas-pipeline.png`.

Screenshot tambahan yang harus diambil: salah satu kartu lot di daftar aktivitas yang menampilkan status, batas bayar, tombol `Lanjut Bayar`, `Detail Packing`, atau `Detail Penjemputan`.

Nama file disarankan: `pembeli-22-aktivitas-kartu-lot.png`.

## 16. Detail Aktivitas Bid

Klik detail dari halaman aktivitas untuk membuka `/pembeli/aktivitas/{ikan}`. Di sini pembeli dapat melihat ringkasan lot, riwayat bid sendiri, status lot, detail packing, detail penjemputan, dan foto-foto bukti.

Screenshot yang harus diambil: bagian atas `Detail Aktivitas Bid` yang berisi ringkasan lot, harga, dan status.

Nama file disarankan: `pembeli-23-detail-aktivitas-header.png`.

Screenshot tambahan yang harus diambil: bagian `Detail Packing`, `Detail Penjemputan`, dan komponen `Foto Packing & Penjemputan`.

Nama file disarankan: `pembeli-24-detail-aktivitas-foto.png`.

## 17. Mengisi Data Penjemput

Setelah pembayaran sukses, pembeli menunggu penjual mengonfirmasi packing terlebih dahulu. Setelah packing dikonfirmasi, pembeli mengisi data penjemput: nama sopir, plat nomor, foto sopir penjemput, dan foto kendaraan penjemput. Data ini akan dicocokkan oleh penjual saat penjemput datang.

Screenshot yang harus diambil: form `Isi Data Penjemput` yang berisi input nama sopir, input plat nomor, upload `Foto Sopir Penjemput`, upload `Foto Kendaraan Penjemput`, dan tombol `Simpan Data Penjemput`.

Nama file disarankan: `pembeli-25-isi-data-penjemput.png`.

## 18. Memantau Validasi Penjemput

Setelah data penjemput tersimpan, pembeli tidak perlu menekan tombol tambahan. Sistem menampilkan status bahwa data penjemput sudah tersimpan dan pembeli tinggal menunggu penjual mencocokkan sopir serta kendaraan saat penjemput tiba di lokasi.

Screenshot yang harus diambil: informasi `Data penjemput sudah tersimpan`, status penjemputan, dan detail sopir/plat pada halaman detail aktivitas.

Nama file disarankan: `pembeli-26-validasi-penjemput.png`.

## 19. Review dan Konfirmasi Selesai

Setelah penjual memvalidasi penjemput datang, pembeli dapat memberi rating, menulis review, lalu klik `Konfirmasi Selesai`. Setelah dikonfirmasi, transaksi keluar dari Aktivitas Bid dan masuk ke Riwayat Pembelian.

Screenshot yang harus diambil: form `Review dan Konfirmasi Selesai` yang berisi dropdown rating, textarea review, dan tombol `Konfirmasi Selesai`.

Nama file disarankan: `pembeli-27-review-konfirmasi-selesai.png`.

## 20. Halaman Penilaian

Jika pembeli membuka opsi selesai dari aktivitas, sistem dapat langsung membuka halaman `Penilaian Transaksi`. Halaman ini menampilkan ringkasan lot selesai, harga final, status penjemputan, form review, dan ringkasan penjemputan.

Screenshot yang harus diambil: bagian `Penilaian Transaksi` dari judul sampai kartu ringkasan lot.

Nama file disarankan: `pembeli-28-penilaian-header.png`.

Screenshot tambahan yang harus diambil: bagian `Review dan Konfirmasi Selesai`, `Ringkasan Penjemputan`, dan `Foto Packing & Penjemputan`.

Nama file disarankan: `pembeli-29-penilaian-review-foto.png`.

## 21. Riwayat Pembelian

Halaman `Riwayat` berada di `/pembeli/riwayat`. Halaman ini hanya menampilkan pesanan yang sudah dikonfirmasi selesai oleh pembeli.

Screenshot yang harus diambil: bagian hero `Riwayat Pembelian`, kartu `Total Pesanan Selesai`, dan nilai pembelian selesai.

Nama file disarankan: `pembeli-30-riwayat-hero.png`.

Screenshot tambahan yang harus diambil: bagian `Pesanan Selesai` yang menampilkan lot selesai, harga final, rating, review, serta foto packing dan penjemputan.

Nama file disarankan: `pembeli-31-riwayat-pesanan-selesai.png`.

## 22. Notifikasi

Ikon notifikasi berada di navbar. Notifikasi digunakan untuk informasi menang bid, deadline pembayaran, pembayaran sukses, packing, penjemput datang, pesanan selesai, dan pelanggaran.

Screenshot yang harus diambil: popover notifikasi setelah ikon lonceng diklik, termasuk judul `Notifikasi`, daftar notifikasi, tombol `Buka`, tombol `Dibaca`, dan link `Lihat semua notifikasi`.

Nama file disarankan: `pembeli-32-notifikasi-popover.png`.

Screenshot tambahan yang harus diambil: halaman `/notifikasi` yang menampilkan daftar notifikasi lengkap.

Nama file disarankan: `pembeli-33-notifikasi-halaman.png`.

## 23. Profil dan Hapus Akun

Pembeli dapat membuka halaman profil untuk memperbarui informasi dasar. Karena akun memakai Google dan tidak memakai password manual, penghapusan akun memakai OTP WhatsApp.

Screenshot yang harus diambil: halaman profil bagian data akun dan form penghapusan akun berbasis OTP WhatsApp.

Nama file disarankan: `pembeli-34-profil-hapus-akun-otp.png`.

## 24. Catatan Penting untuk Pembeli

Pembeli tidak menggunakan saldo internal dan tidak perlu deposit. Pembeli dapat ikut bidding tanpa top up. Kewajiban pembayaran muncul hanya jika pembeli menang lelang.

Screenshot yang harus diambil: gunakan halaman detail lot bagian form bid atau halaman pembayaran untuk menunjukkan bahwa pembayaran dilakukan setelah menang, bukan sebelum bidding.

Nama file disarankan: `pembeli-35-tanpa-deposit.png`.

## Checklist Screenshot Pembeli

| No | Nama File | Bagian Layar |
| --- | --- | --- |
| 1 | `pembeli-01-login-google.png` | Card login Google |
| 2 | `pembeli-02-register-role.png` | Card daftar role |
| 3 | `pembeli-03-google-account-chooser.png` | Pilihan akun Google |
| 4 | `pembeli-04-onboarding-whatsapp.png` | Form nomor WhatsApp |
| 5 | `pembeli-05-otp-whatsapp.png` | Form OTP WhatsApp |
| 6 | `pembeli-06-navbar.png` | Navbar pembeli |
| 7 | `pembeli-07-marketplace-hero.png` | Hero Marketplace |
| 8 | `pembeli-08-marketplace-lot-list.png` | Daftar lot marketplace |
| 9 | `pembeli-09-detail-lot-header-toko.png` | Header detail lot dan chip toko |
| 10 | `pembeli-10-detail-lot-spesifikasi.png` | Spesifikasi Lot |
| 11 | `pembeli-11-detail-lot-bid-panel.png` | Panel countdown dan bid |
| 12 | `pembeli-12-dashboard-toko-penjual.png` | Header dashboard toko penjual |
| 13 | `pembeli-13-dashboard-toko-isi.png` | Identitas dan isi toko |
| 14 | `pembeli-14-pasang-bid.png` | Form Pasang Bid |
| 15 | `pembeli-15-status-bid-detail.png` | Status Bid Anda |
| 16 | `pembeli-16-menang-lelang.png` | Panel Pemenang Lelang |
| 17 | `pembeli-17-pembayaran-ringkasan.png` | Ringkasan pembayaran |
| 18 | `pembeli-18-pembayaran-metode.png` | Metode pembayaran TriPay |
| 19 | `pembeli-19-pembayaran-berhasil.png` | Pembayaran Berhasil |
| 20 | `pembeli-20-aktivitas-hero.png` | Hero Aktivitas Bid |
| 21 | `pembeli-21-aktivitas-pipeline.png` | Pipeline Bayar, Penjemputan, Selesai |
| 22 | `pembeli-22-aktivitas-kartu-lot.png` | Kartu lot aktivitas |
| 23 | `pembeli-23-detail-aktivitas-header.png` | Header Detail Aktivitas |
| 24 | `pembeli-24-detail-aktivitas-foto.png` | Foto packing dan penjemputan |
| 25 | `pembeli-25-isi-data-penjemput.png` | Form data penjemput, foto sopir, dan foto kendaraan |
| 26 | `pembeli-26-penjemput-dalam-perjalanan.png` | Tombol penjemput dalam perjalanan |
| 27 | `pembeli-27-review-konfirmasi-selesai.png` | Review dan Konfirmasi Selesai |
| 28 | `pembeli-28-penilaian-header.png` | Header Penilaian Transaksi |
| 29 | `pembeli-29-penilaian-review-foto.png` | Review, ringkasan penjemputan, foto |
| 30 | `pembeli-30-riwayat-hero.png` | Hero Riwayat Pembelian |
| 31 | `pembeli-31-riwayat-pesanan-selesai.png` | Pesanan selesai |
| 32 | `pembeli-32-notifikasi-popover.png` | Popover notifikasi |
| 33 | `pembeli-33-notifikasi-halaman.png` | Halaman notifikasi |
| 34 | `pembeli-34-profil-hapus-akun-otp.png` | Profil dan hapus akun OTP |
| 35 | `pembeli-35-tanpa-deposit.png` | Bukti alur tanpa deposit |
