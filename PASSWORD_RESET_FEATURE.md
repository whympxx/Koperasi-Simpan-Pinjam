# Fitur Reset Password - Sistem Simpan Pinjam PT. Puninar Jaya

## Deskripsi
Fitur reset password memungkinkan pengguna untuk mereset password mereka ketika lupa, tanpa perlu menghubungi administrator. Fitur ini menggunakan sistem token yang aman dengan email verification.

## File yang Ditambahkan/Dimodifikasi

### File Baru:
1. **forgot-password.php** - Halaman untuk meminta reset password
2. **reset-password.php** - Halaman untuk mengatur password baru
3. **classes/EmailHelper.php** - Helper class untuk mengirim email
4. **database/add_password_reset_table.sql** - Script untuk menambahkan tabel password reset
5. **PASSWORD_RESET_FEATURE.md** - Dokumentasi ini

### File yang Dimodifikasi:
1. **login.php** - Menambahkan link "Lupa Password?" yang mengarah ke forgot-password.php
2. **classes/Auth.php** - Menambahkan method untuk password reset
3. **database/simpanpinjam_puninar.sql** - Menambahkan tabel password_reset_tokens

## Cara Menggunakan

### 1. Untuk Pengguna (Nasabah/Admin):
1. Buka halaman login
2. Klik "Lupa Password?"
3. Masukkan NIK dan email yang terdaftar
4. Cek email untuk link reset password
5. Klik link di email (berlaku 1 jam)
6. Masukkan password baru (minimal 8 karakter, huruf besar, huruf kecil, angka)
7. Login dengan password baru

### 2. Untuk Administrator:
- Monitor log email di `logs/email_notifications.log`
- Monitor aktivitas reset password di tabel `activity_logs`
- Token yang sudah digunakan akan otomatis dihapus

## Keamanan

### Fitur Keamanan:
1. **Token Expiration**: Token hanya berlaku 1 jam
2. **Single Use**: Token hanya bisa digunakan sekali
3. **Email Verification**: Harus menggunakan email yang terdaftar
4. **Password Strength**: Password harus memenuhi kriteria keamanan
5. **Activity Logging**: Semua aktivitas dicatat di log

### Validasi:
- NIK harus 16 digit
- Email harus valid dan terdaftar di sistem
- Password minimal 8 karakter dengan kombinasi huruf besar, kecil, dan angka
- Token harus valid dan belum kadaluarsa

## Database Schema

### Tabel Baru: `password_reset_tokens`
```sql
CREATE TABLE password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Index untuk Performance:
- `idx_password_reset_tokens_token` - Untuk pencarian token
- `idx_password_reset_tokens_user_expires` - Untuk cleanup token

## Konfigurasi Email

### Untuk Development:
- Email akan di-log ke file `logs/email_notifications.log`
- Tidak perlu konfigurasi SMTP

### Untuk Production:
1. Update konfigurasi di `config/config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@puninar.com');
define('FROM_NAME', 'PT. Puninar Jaya Logistik');
```

2. Gunakan App Password untuk Gmail
3. Pastikan server mendukung SSL/TLS

## Troubleshooting

### Masalah Umum:

1. **Email tidak terkirim**:
   - Cek konfigurasi SMTP di config.php
   - Cek log di `logs/email_notifications.log`
   - Pastikan server mendukung mail() function

2. **Token tidak valid**:
   - Token mungkin sudah kadaluarsa (1 jam)
   - Token sudah digunakan
   - Cek database untuk status token

3. **Password tidak memenuhi kriteria**:
   - Minimal 8 karakter
   - Harus ada huruf besar (A-Z)
   - Harus ada huruf kecil (a-z)
   - Harus ada angka (0-9)

### Log Files:
- `logs/email_notifications.log` - Log email yang dikirim
- `logs/database_errors.log` - Log error database
- Tabel `activity_logs` - Log aktivitas pengguna

## Maintenance

### Cleanup Token Expired:
```sql
-- Hapus token yang sudah kadaluarsa dan digunakan
DELETE FROM password_reset_tokens 
WHERE expires_at < NOW() AND is_used = 1;
```

### Monitor Usage:
```sql
-- Lihat statistik reset password
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_requests,
    SUM(is_used) as successful_resets
FROM password_reset_tokens 
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

## Testing

### Test Cases:
1. **Valid Request**: NIK dan email yang benar
2. **Invalid NIK**: NIK yang tidak terdaftar
3. **Invalid Email**: Email yang tidak cocok dengan NIK
4. **Expired Token**: Token yang sudah kadaluarsa
5. **Used Token**: Token yang sudah digunakan
6. **Weak Password**: Password yang tidak memenuhi kriteria

### Test Data:
- NIK: PUN001, Email: ahmad.fauzi@puninar.com
- NIK: ADMIN001, Email: admin@puninar.com

## Update Database

Jika database sudah ada, jalankan script berikut:
```bash
mysql -u root -p simpanpinjam_puninar < database/add_password_reset_table.sql
```

## Support

Untuk masalah teknis, hubungi:
- **IT Support**: it@puninar.com
- **HRD**: hrd@puninar.com
- **Phone**: 021-xxxx-xxxx

---
**Dibuat oleh**: System Developer  
**Tanggal**: 2024  
**Versi**: 1.0
