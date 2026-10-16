# Admin Dashboard - PT. Puninar Jaya Logistik

## Overview
Dashboard admin yang profesional dan lengkap untuk mengelola sistem simpan pinjam PT. Puninar Jaya Logistik. Dashboard ini dirancang dengan antarmuka yang modern, responsif, dan mudah digunakan.

## Fitur Utama

### 1. Dashboard Overview
- **Statistik Real-time**: Menampilkan data penting seperti pengajuan menunggu, pinjaman aktif, total outstanding, dan tunggakan
- **Quick Stats**: 6 kartu statistik dengan gradient background yang menarik
- **Welcome Section**: Sambutan personal untuk admin dengan informasi waktu

### 2. Manajemen Pengajuan Pinjaman
- **Pending Applications**: Tabel pengajuan pinjaman yang menunggu persetujuan
- **Quick Actions**: Tombol untuk melihat detail dan menyetujui pengajuan
- **Status Tracking**: Badge status yang jelas untuk setiap pengajuan

### 3. Monitoring Pembayaran
- **Overdue Payments**: Daftar pembayaran yang terlambat
- **Reminder System**: Fitur untuk mengirim pengingat pembayaran otomatis
- **Payment Tracking**: Monitoring status pembayaran real-time

### 4. Analisis dan Laporan
- **Transaction Charts**: Grafik transaksi 12 bulan terakhir menggunakan Chart.js
- **Top Borrowers**: Ranking 10 peminjam terbesar
- **Recent Activities**: Aktivitas terbaru dalam sistem

### 5. Manajemen User
- **Recent Users**: Daftar nasabah baru yang mendaftar
- **User Statistics**: Statistik pengguna aktif

## File Structure

```
admin-dashboard.php          # File utama dashboard admin
assets/css/admin-style.css   # Styling khusus untuk admin
ajax/
├── send-payment-reminder.php    # Handler untuk kirim pengingat
└── mark-notification-read.php   # Handler untuk mark notifikasi
includes/
└── admin-functions.php          # Helper functions untuk admin
```

## Teknologi yang Digunakan

### Frontend
- **Bootstrap 5.3.2**: Framework CSS untuk responsive design
- **Font Awesome 6.4.0**: Icon library
- **Chart.js**: Library untuk grafik dan charts
- **DataTables**: Plugin untuk tabel interaktif

### Backend
- **PHP 7.4+**: Server-side scripting
- **MySQL**: Database management
- **PDO**: Database abstraction layer

## Fitur Keamanan

### 1. Authentication & Authorization
- Sistem autentikasi multi-level (admin, super_admin)
- Session management yang aman
- Role-based access control

### 2. Data Protection
- Prepared statements untuk mencegah SQL injection
- Input validation dan sanitization
- CSRF protection

### 3. Activity Logging
- Log semua aktivitas admin
- Tracking perubahan data
- Audit trail lengkap

## Responsive Design

### Desktop (1200px+)
- Sidebar tetap dengan navigasi lengkap
- Layout 3 kolom untuk statistik
- Tabel dengan fitur lengkap

### Tablet (768px - 1199px)
- Sidebar collapsible
- Layout 2 kolom untuk statistik
- Tabel responsif dengan scroll horizontal

### Mobile (< 768px)
- Sidebar overlay
- Layout 1 kolom untuk statistik
- Tabel dengan scroll horizontal
- Touch-friendly interface

## Color Scheme

### Primary Colors
- **Primary**: #667eea (Blue gradient)
- **Secondary**: #764ba2 (Purple gradient)
- **Success**: #56ab2f (Green gradient)
- **Warning**: #f093fb (Pink gradient)
- **Danger**: #ff416c (Red gradient)
- **Info**: #4facfe (Cyan gradient)

### Status Colors
- **Pending**: #fff3cd (Light yellow)
- **Approved**: #d4edda (Light green)
- **Rejected**: #f8d7da (Light red)
- **Overdue**: #f8d7da (Light red with animation)
- **Active**: #d1ecf1 (Light blue)

## Performance Optimization

### 1. Database Optimization
- Efficient queries dengan proper indexing
- Pagination untuk data besar
- Caching untuk statistik

### 2. Frontend Optimization
- Minified CSS dan JavaScript
- Lazy loading untuk charts
- Optimized images

### 3. Caching Strategy
- Browser caching untuk static assets
- Session-based caching untuk user data
- Database query optimization

## Accessibility Features

### 1. Keyboard Navigation
- Tab navigation support
- Focus indicators
- Keyboard shortcuts

### 2. Screen Reader Support
- Semantic HTML structure
- ARIA labels
- Alt text for images

### 3. High Contrast Mode
- Support for high contrast preferences
- Color-blind friendly palette
- Sufficient color contrast ratios

## Browser Compatibility

### Supported Browsers
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Mobile Browsers
- Chrome Mobile 90+
- Safari Mobile 14+
- Samsung Internet 13+

## Installation & Setup

### 1. Prerequisites
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web server (Apache/Nginx)

### 2. File Placement
```
htdocs/
├── admin-dashboard.php
├── assets/css/admin-style.css
├── ajax/
│   ├── send-payment-reminder.php
│   └── mark-notification-read.php
└── includes/
    └── admin-functions.php
```

### 3. Database Setup
Pastikan tabel berikut ada dan terisi:
- `users` (dengan role admin/super_admin)
- `loan_applications`
- `active_loans`
- `loan_schedules`
- `transactions`
- `notifications`
- `activity_logs`

### 4. Configuration
Update konfigurasi di `config/config.php`:
- Database connection
- Application settings
- Security settings

## Usage Guide

### 1. Login as Admin
```
URL: http://localhost/admin-dashboard.php
Credentials: Use admin/super_admin account
```

### 2. Navigation
- **Dashboard**: Overview utama
- **Manajemen User**: Kelola pengguna
- **Pengajuan Pinjaman**: Review dan approve/reject
- **Pinjaman Aktif**: Monitor pinjaman berjalan
- **Akun Tabungan**: Kelola tabungan nasabah
- **Transaksi**: Riwayat transaksi
- **Laporan**: Generate laporan
- **Pengaturan**: Konfigurasi sistem

### 3. Quick Actions
- **Approve Loan**: Klik tombol hijau pada pengajuan
- **Reject Loan**: Klik tombol merah dengan alasan
- **Send Reminder**: Klik bell icon pada overdue
- **View Details**: Klik eye icon untuk detail

## Customization

### 1. Adding New Stats Cards
```php
// Di admin-dashboard.php, tambahkan kartu baru
<div class="col-md-2 mb-3">
    <div class="stats-card stats-gradient-[color] h-100">
        <!-- Content -->
    </div>
</div>
```

### 2. Adding New Menu Items
```php
// Di sidebar navigation
<li class="nav-item">
    <a class="nav-link" href="new-page.php">
        <i class="fas fa-icon-name"></i>
        <span>Menu Name</span>
    </a>
</li>
```

### 3. Customizing Colors
```css
/* Di admin-style.css */
.stats-gradient-custom {
    background: linear-gradient(135deg, #color1 0%, #color2 100%);
    color: white;
}
```

## Troubleshooting

### Common Issues

#### 1. Dashboard Tidak Load
- Periksa koneksi database
- Pastikan file includes/init.php ada
- Check PHP error logs

#### 2. Charts Tidak Muncul
- Pastikan Chart.js loaded
- Check browser console untuk errors
- Verify data format

#### 3. AJAX Calls Failed
- Periksa URL endpoint
- Check authentication status
- Verify JSON response format

### Debug Mode
Aktifkan debug mode di `config/config.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Future Enhancements

### Planned Features
1. **Real-time Notifications**: WebSocket integration
2. **Advanced Analytics**: More detailed charts and reports
3. **Export Functions**: PDF/Excel export capabilities
4. **Mobile App**: React Native companion app
5. **API Integration**: RESTful API for third-party integration

### Performance Improvements
1. **Redis Caching**: Implement Redis for better performance
2. **CDN Integration**: Static asset delivery optimization
3. **Database Sharding**: For large-scale deployment
4. **Microservices**: Break down into smaller services

## Support & Maintenance

### Regular Maintenance
- Database optimization (weekly)
- Log cleanup (monthly)
- Security updates (as needed)
- Performance monitoring (daily)

### Backup Strategy
- Database backup (daily)
- File backup (weekly)
- Configuration backup (before changes)

### Monitoring
- Error logging
- Performance metrics
- User activity tracking
- Security monitoring

## Contact Information

**Developer**: System Developer  
**Email**: developer@puninar.com  
**Version**: 1.0  
**Last Updated**: 2024  

---

*Dokumentasi ini akan terus diperbarui sesuai dengan perkembangan sistem.*
