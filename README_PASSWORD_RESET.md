# 🔐 Fitur Reset Password - Panduan Lengkap

## 📋 Ringkasan
Fitur reset password telah berhasil ditambahkan ke sistem Simpan Pinjam PT. Puninar Jaya. Pengguna sekarang dapat mereset password mereka sendiri tanpa perlu menghubungi administrator.

## 🚀 Fitur yang Ditambahkan

### 1. Halaman Lupa Password (`forgot-password.php`)
- Form untuk memasukkan NIK dan email
- Validasi data pengguna
- Pengiriman link reset via email

### 2. Halaman Reset Password (`reset-password.php`)
- Form untuk memasukkan password baru
- Validasi kekuatan password
- Konfirmasi password

### 3. Sistem Email
- Template email yang profesional
- Log email untuk development
- Konfigurasi SMTP untuk production

### 4. Keamanan
- Token dengan masa berlaku 1 jam
- Token hanya bisa digunakan sekali
- Validasi email dan NIK
- Password strength validation

## 🔧 Instalasi

### 1. Update Database
Jalankan script SQL untuk menambahkan tabel password reset:
```bash
mysql -u root -p simpanpinjam_puninar < database/add_password_reset_table.sql
```

### 2. Set Permissions
Pastikan folder `logs/` dapat ditulis:
```bash
chmod 755 logs/
```

### 3. Konfigurasi Email (Optional)
Untuk production, update `config/config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

## 📱 Cara Penggunaan

### Untuk Pengguna:
1. **Buka halaman login** → Klik "Lupa Password?"
2. **Masukkan NIK dan email** yang terdaftar
3. **Cek email** untuk link reset password
4. **Klik link** di email (berlaku 1 jam)
5. **Masukkan password baru** dengan kriteria:
   - Minimal 8 karakter
   - Huruf besar (A-Z)
   - Huruf kecil (a-z) 
   - Angka (0-9)
6. **Login** dengan password baru

### Untuk Administrator:
- Monitor log di `logs/email_notifications.log`
- Cek aktivitas di tabel `activity_logs`
- Token expired otomatis dibersihkan

## 🔒 Keamanan

### Fitur Keamanan:
- ✅ Token expiration (1 jam)
- ✅ Single use token
- ✅ Email verification
- ✅ Password strength validation
- ✅ Activity logging
- ✅ CSRF protection

### Validasi Input:
- NIK: 16 digit numerik
- Email: Format valid dan terdaftar
- Password: Kriteria keamanan ketat

## 📊 Database Schema

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

## 🐛 Troubleshooting

### Masalah Umum:

**❌ Email tidak terkirim**
- Cek konfigurasi SMTP
- Lihat log di `logs/email_notifications.log`
- Pastikan server support mail()

**❌ Token tidak valid**
- Token kadaluarsa (1 jam)
- Token sudah digunakan
- Cek database

**❌ Password tidak diterima**
- Pastikan memenuhi kriteria:
  - 8+ karakter
  - Huruf besar + kecil + angka

## 📈 Monitoring

### Log Files:
- `logs/email_notifications.log` - Email yang dikirim
- `logs/database_errors.log` - Error database
- Tabel `activity_logs` - Aktivitas pengguna

### Query Monitoring:
```sql
-- Statistik reset password
SELECT 
    DATE(created_at) as tanggal,
    COUNT(*) as total_request,
    SUM(is_used) as berhasil_reset
FROM password_reset_tokens 
GROUP BY DATE(created_at)
ORDER BY tanggal DESC;
```

## 🧪 Testing

### Test Cases:
1. ✅ Valid request (NIK + email benar)
2. ✅ Invalid NIK (tidak terdaftar)
3. ✅ Invalid email (tidak cocok)
4. ✅ Expired token (kadaluarsa)
5. ✅ Used token (sudah dipakai)
6. ✅ Weak password (tidak memenuhi kriteria)

### Test Data:
- **Nasabah**: NIK: PUN001, Email: ahmad.fauzi@puninar.com
- **Admin**: NIK: ADMIN001, Email: admin@puninar.com

## 🔄 Maintenance

### Cleanup Token:
```sql
-- Hapus token expired
DELETE FROM password_reset_tokens 
WHERE expires_at < NOW() AND is_used = 1;
```

### Monitor Usage:
```sql
-- Lihat penggunaan reset password
SELECT 
    u.full_name,
    prt.created_at,
    prt.is_used
FROM password_reset_tokens prt
JOIN users u ON prt.user_id = u.id
ORDER BY prt.created_at DESC;
```

## 📞 Support

**IT Support**: it@puninar.com  
**HRD**: hrd@puninar.com  
**Phone**: 021-xxxx-xxxx

---

## 🎯 Ringkasan Implementasi

✅ **Halaman Lupa Password** - `forgot-password.php`  
✅ **Halaman Reset Password** - `reset-password.php`  
✅ **Sistem Email** - `classes/EmailHelper.php`  
✅ **Database Schema** - Tabel `password_reset_tokens`  
✅ **Keamanan** - Token, validasi, logging  
✅ **Dokumentasi** - Panduan lengkap  

**Status**: ✅ **SELESAI** - Fitur reset password siap digunakan!
