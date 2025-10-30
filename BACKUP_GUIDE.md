# Tenant Database Backup & Restore

Sistem backup automation untuk database tenant dengan fitur lengkap.

## 📋 Fitur

-   ✅ Backup manual & otomatis
-   ✅ Backup terkompresi (gzip)
-   ✅ Restore database dari backup
-   ✅ Cleanup backup lama otomatis
-   ✅ REST API untuk manajemen backup
-   ✅ Password database terenkripsi
-   ✅ Struktur penyimpanan terorganisir

## 🚀 Cara Penggunaan

### Command Line (Artisan)

#### 1. Backup Tenant

```bash
# Backup semua tenant
php artisan tenant:backup

# Backup semua tenant dengan kompresi
php artisan tenant:backup --compress

# Backup tenant tertentu
php artisan tenant:backup warung-123

# Backup dengan custom retention (hapus backup > 60 hari)
php artisan tenant:backup --keep-days=60 --compress
```

#### 2. List Backups

```bash
# Lihat semua backup
php artisan tenant:backups

# Lihat backup tenant tertentu
php artisan tenant:backups warung-123
```

#### 3. Restore Database

```bash
# Restore dengan backup terbaru
php artisan tenant:restore warung-123

# Restore dengan backup tertentu
php artisan tenant:restore warung-123 backups/warung-123/2025/10/warung-123_2025-10-30_140530.sql.gz
```

### REST API

**Base URL:** `http://localhost/api/central`

**Header Required:**

```
X-Master-API-Key: your-master-key
```

#### 1. Create Backup

```bash
POST /tenants/{tenant_id}/backup

# Body (optional):
{
  "compress": true
}

# Response:
{
  "message": "Backup created successfully",
  "tenant_id": "warung-123",
  "tenant_name": "Warung Makan Bu Joko",
  "compressed": true
}
```

#### 2. List Backups

```bash
GET /tenants/{tenant_id}/backups

# Response:
{
  "data": [
    {
      "filename": "warung-123_2025-10-30_140530.sql.gz",
      "path": "backups/warung-123/2025/10/warung-123_2025-10-30_140530.sql.gz",
      "date": "2025-10-30T14:05:30.000Z",
      "age": "2 hours ago",
      "size": 1024000,
      "size_formatted": "1.00 MB",
      "compressed": true
    }
  ],
  "total": 1
}
```

#### 3. Download Backup

```bash
GET /tenants/{tenant_id}/backups/download?file={filename}

# Example:
GET /tenants/warung-123/backups/download?file=warung-123_2025-10-30_140530.sql.gz
```

#### 4. Delete Backup

```bash
DELETE /tenants/{tenant_id}/backups

# Body:
{
  "file": "warung-123_2025-10-30_140530.sql.gz"
}

# Response:
{
  "message": "Backup deleted successfully",
  "file": "warung-123_2025-10-30_140530.sql.gz"
}
```

#### 5. Restore Database

```bash
POST /tenants/{tenant_id}/restore

# Body (optional):
{
  "file": "warung-123_2025-10-30_140530.sql.gz"
}
# Jika file tidak disebutkan, akan restore dari backup terbaru

# Response:
{
  "message": "Database restored successfully",
  "tenant_id": "warung-123",
  "backup_file": "warung-123_2025-10-30_140530.sql.gz"
}
```

#### 6. Backup Statistics

```bash
GET /backups/stats

# Response:
{
  "data": [
    {
      "tenant_id": "warung-123",
      "tenant_name": "Warung Makan Bu Joko",
      "backup_count": 5,
      "total_size": 5242880,
      "total_size_formatted": "5.00 MB",
      "latest_backup": "warung-123_2025-10-30_140530.sql.gz",
      "latest_backup_date": "2025-10-30T14:05:30.000Z",
      "latest_backup_age": "2 hours ago"
    }
  ],
  "total_tenants": 1
}
```

## ⏰ Backup Otomatis (Scheduled)

Backup otomatis sudah dikonfigurasi di `app/Console/Kernel.php`:

### Daily Backup

-   **Waktu:** Setiap hari jam 02:00 WIB
-   **Retention:** 30 hari
-   **Kompresi:** Ya (gzip)
-   **Email Alert:** Kirim email jika gagal

### Weekly Backup

-   **Waktu:** Setiap Minggu jam 03:00 WIB
-   **Retention:** 90 hari (3 bulan)
-   **Kompresi:** Ya (gzip)
-   **Email Alert:** Kirim email jika gagal

### Setup Email Alert

Tambahkan di `.env`:

```env
BACKUP_ALERT_EMAIL=admin@example.com
```

### Jalankan Scheduler

**Development (Manual):**

```bash
php artisan schedule:run
```

**Production (Cron Job):**

Tambahkan di crontab:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 📁 Struktur Penyimpanan

```
storage/app/backups/
├── tenant-1/
│   ├── 2025/
│   │   ├── 10/
│   │   │   ├── tenant-1_2025-10-30_020000.sql.gz
│   │   │   ├── tenant-1_2025-10-30_140530.sql.gz
│   │   │   └── ...
│   │   └── 11/
│   │       └── ...
│   └── ...
└── tenant-2/
    └── ...
```

## 🔒 Keamanan

-   ✅ Password database dienkripsi saat create/update tenant
-   ✅ Password didekripsi otomatis saat backup/restore
-   ✅ Backup API protected dengan Master API Key
-   ✅ Backup file disimpan di `storage/app` (tidak accessible via web)

## ⚙️ Konfigurasi

### Ubah Timezone

Edit `app/Console/Kernel.php`:

```php
->timezone('Asia/Jakarta') // Ubah sesuai timezone Anda
```

### Ubah Retention Period

```bash
# Via command
php artisan tenant:backup --keep-days=60

# Via schedule (edit Kernel.php)
$schedule->command('tenant:backup --keep-days=60')
```

### Ubah Waktu Backup

Edit `app/Console/Kernel.php`:

```php
->dailyAt('02:00')  // Ubah jam backup
```

## 📊 Monitoring

### Check Backup Status

```bash
php artisan tenant:backups
```

### Check Logs

```bash
tail -f storage/logs/laravel.log | grep -i backup
```

## 🆘 Troubleshooting

### Error: mysqldump not found

**Windows:**
Tambahkan MySQL bin ke PATH atau gunakan full path:

```bash
# Edit BackupTenantDatabase.php
$command = 'C:\\xampp\\mysql\\bin\\mysqldump ...';
```

**Linux:**

```bash
sudo apt-get install mysql-client
```

### Error: Permission denied

```bash
chmod -R 775 storage/app/backups
```

### Backup Gagal

1. Check database credentials
2. Check disk space: `df -h`
3. Check logs: `storage/logs/laravel.log`

## 📝 Best Practices

1. **Test restore secara berkala** - pastikan backup bisa direstore
2. **Monitor disk space** - backup bisa memenuhi storage
3. **Backup ke cloud** - simpan copy backup di S3/GCS
4. **Email alerts** - setup notifikasi jika backup gagal
5. **Retention policy** - jangan simpan backup terlalu lama

## 🎯 Next Steps

-   [ ] Backup ke cloud storage (S3, Google Cloud Storage)
-   [ ] Backup encryption at rest
-   [ ] Backup verification (restore test)
-   [ ] Webhook notifications
-   [ ] Backup diff/incremental
