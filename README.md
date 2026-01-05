# Monitoring Keuangan Pribadi (MiniFin)

Aplikasi web sederhana untuk memonitor pemasukan dan pengeluaran pribadi. Dibangun dengan PHP 8 (native), MySQL, PDO, Bootstrap 5, dan Chart.js dengan fokus pada kemudahan penggunaan dan UI ringan.

## Fitur
- Login & Register (password hash dengan `password_hash`).
- Dashboard: total pemasukan/pengeluaran/saldo bulan ini, grafik pengeluaran per kategori, grafik tren harian bulan berjalan.
- Transaksi: tambah/edit/hapus transaksi, pencarian, filter tanggal, kategori, tipe; tanggal default hari ini; validasi jelas; quick add kategori via modal.
- Kategori: CRUD kategori income/expense terpisah.
- Navigasi jelas: Dashboard | Transaksi | Kategori | Logout. Layout responsif dan ramah mobile.

## Struktur Folder
```
.
├── assets/
│   └── style.css        # styling ringan untuk tampilan lebih bersih
├── categories.php       # CRUD kategori income/expense
├── config.php           # konfigurasi database
├── dashboard.php        # ringkasan dan grafik bulan ini
├── db.php               # koneksi PDO
├── footer.php           # partial footer + script
├── header.php           # partial header + navbar + flash message
├── helpers.php          # helper session, auth, utils
├── index.php            # redirect ke dashboard/login
├── login.php            # form login
├── logout.php           # keluar sesi
├── register.php         # form registrasi
├── schema.sql           # skema database MySQL lengkap
└── transactions.php     # pencatatan dan daftar transaksi + quick add kategori
```

## Skema Database
Lihat `schema.sql` untuk definisi tabel lengkap (users, categories, transactions) beserta index yang relevan.

## Setup di XAMPP / Laragon
1. Pastikan PHP 8 dan MySQL aktif.
2. Kloning atau salin folder ini ke dalam direktori web server (contoh: `htdocs/mini_fin`).
3. Buat database baru, misal `mini_fin`, lalu impor `schema.sql` melalui phpMyAdmin atau CLI:
   ```bash
   mysql -u root -p mini_fin < schema.sql
   ```
4. Sesuaikan kredensial database di `config.php` (host, user, password, nama database). Anda juga dapat menggunakan variabel lingkungan `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` bila tersedia.
5. Buka aplikasi melalui browser: `http://localhost/mini_fin/login.php`.
6. Daftarkan akun baru, lalu mulai menambahkan kategori dan transaksi.

## Catatan Penggunaan
- Semua query menggunakan prepared statements (PDO) untuk keamanan.
- Form dibuat 1 layar dengan tombol besar, validasi sederhana, dan pesan error human-friendly.
- Empty state menyediakan instruksi singkat saat data belum ada.
- Quick Add kategori tersedia di halaman transaksi agar pengguna tidak perlu pindah halaman.
