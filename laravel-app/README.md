# KOPPUJA — Simpan Pinjam (Laravel 12)

Versi Laravel dari aplikasi simpan pinjam Koperasi Konsumen Karyawan PT. Puninar Jaya.
Ketentuan bisnis mengikuti RAT Tahun Buku 2025 (plafon 2×/1× simpanan wajib, tenor 12/18 bulan,
bunga flat 1,5%/bulan, denda 0,5%/hari, rule engine IF–THEN, perhitungan SHU).

## Menjalankan

Prasyarat: PHP ≥ 8.2, Composer, dan MySQL/MariaDB XAMPP dalam keadaan **Start**.

```bash
# 1. Buat database (sekali saja), via phpMyAdmin atau:
mysql -u root -e "CREATE DATABASE koppuja_laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 2. Siapkan aplikasi
cd laravel-app
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve           # buka http://127.0.0.1:8000
```

`.env.example` sudah berisi koneksi MySQL XAMPP bawaan (`root` tanpa password, database
`koppuja_laravel`). Sesuaikan bila pengaturan MySQL Anda berbeda. Tes otomatis (`php artisan test`)
memakai SQLite di memori sehingga tidak menyentuh database MySQL.

Tandai angsuran terlambat setiap hari: `php artisan schedule:work` (atau cron `php artisan schedule:run`).
Email reset password ditulis ke `storage/logs/laravel.log` selama `MAIL_MAILER=log`.

## Akun demo (password `Password123`)

| Peran | NIK | Keterangan |
|---|---|---|
| Super Admin | ADMIN001 | |
| Admin | HRD001, FIN001 | |
| Nasabah | PUN001 | Driver, potongan HRD, simpanan Rp 3 jt → plafon Rp 6 jt |
| Nasabah | PUN002 | Karyawan, simpanan Rp 4 jt |
| Nasabah | PUN003 | Outsourcing → plafon 1×, selalu review manual |
| Nasabah | PUN004 | Masa kerja 6 bulan → belum boleh meminjam |

## Perbedaan dari versi PHP native

- Setoran simpanan dan pembayaran angsuran dari nasabah **menunggu verifikasi pengurus**
  (menu *Verifikasi*); pengurus juga bisa mencatat langsung (mis. potong gaji).
- Persetujuan pinjaman langsung membuat transaksi pencairan dan status `disbursed`.
- Angsuran wajib dibayar berurutan; pinjaman lunas hanya setelah angsuran terakhir.
- Fitur baru *Keluar Keanggotaan*: simpanan wajib dikembalikan dan rekening ditutup.
- Laporan SHU final dikunci; anggota melihat angka dari laporan final.

## Fitur tambahan koperasi

- **Potong gaji massal** (Admin → Potong Gaji): iuran simpanan wajib bulanan + angsuran jatuh tempo
  seluruh anggota berpotongan HRD dibukukan sekaligus; aman diproses ulang (tidak dobel).
- **Pelunasan dipercepat** (Admin → Pinjaman Aktif → detail): pokok tersisa + bunga angsuran berjalan
  + denda; bunga bulan berikutnya tidak ditagih. Anggota melihat simulasinya di halaman Pinjaman.
- **Kwitansi** yang dapat dicetak untuk setiap transaksi selesai.
- **Pengumuman koperasi** (mis. undangan RAT) tampil di beranda anggota, bisa dikirim sebagai notifikasi.
- **Kartu anggota digital** 3D (klik untuk membalik) di beranda dan profil.
- **Log aktivitas** untuk audit pengurus, dan cetak jadwal angsuran.

Tampilan memakai tema navy-emas, scene 3D Three.js di halaman login, serta efek tilt 3D pada kartu
(otomatis nonaktif bila perangkat meminta *reduced motion*).

## Tes

```bash
php artisan test
```
