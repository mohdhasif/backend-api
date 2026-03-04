# Docker Setup untuk Backend API

Setup Docker untuk menjalankan aplikasi backend-api dengan MySQL dan cron service.

## Prerequisites

- Docker dan Docker Compose terinstall
- Port 8080 dan 3306 tersedia

## Cara Menggunakan

### 1. Build dan Start Services

```bash
docker-compose up -d --build
```

Ini akan:
- Build image PHP/Apache
- Start MySQL database
- Start aplikasi PHP di port 8080
- Start cron service untuk prayer_cron.php

### 2. Check Status

```bash
docker-compose ps
```

### 3. View Logs

```bash
# Semua services
docker-compose logs -f

# App saja
docker-compose logs -f app

# MySQL saja
docker-compose logs -f mysql

# Cron saja
docker-compose logs -f cron
```

### 4. Stop Services

```bash
docker-compose down
```

### 5. Stop dan Hapus Data (Volume)

```bash
docker-compose down -v
```

## Akses Aplikasi

- **API**: http://localhost:8080
- **MySQL**: localhost:3306
  - User: `finiteapp_user`
  - Password: `finiteapp_pass`
  - Database: `finiteapp`

## Environment Variables

Anda boleh ubah konfigurasi di `docker-compose.yml` atau buat file `.env`:

```env
DB_HOST=mysql
DB_USER=finiteapp_user
DB_PASS=finiteapp_pass
DB_NAME=finiteapp
TOKEN_EXPIRES_IN_DAYS=30
```

## Services

1. **mysql**: MySQL 8.0 database
   - Auto-create database dari `schema.sql`
   - Data disimpan di volume `mysql_data`

2. **app**: PHP 8.2 dengan Apache
   - Port 8080
   - Uploads directory di-mount dari host

3. **cron**: Cron service untuk prayer_cron.php
   - Run setiap minit
   - Log di `prayer_cron.log`

## Troubleshooting

### Database tidak connect
- Pastikan MySQL sudah healthy: `docker-compose ps`
- Check logs: `docker-compose logs mysql`

### Upload tidak berfungsi
- Pastikan directory `uploads` ada dan writable
- Check permissions: `docker-compose exec app ls -la uploads`

### Cron tidak jalan
- Check logs: `docker-compose logs cron`
- Check cron job: `docker-compose exec cron crontab -l`

### Rebuild setelah perubahan code
```bash
docker-compose up -d --build
```

## Development

Untuk development, file di host akan sync dengan container melalui volume mounts. 
Tidak perlu rebuild setiap kali ubah code, hanya perlu restart service:

```bash
docker-compose restart app
```
