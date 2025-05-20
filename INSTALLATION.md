# Panduan Instalasi PostgreSQL Backup Manager

Dokumen ini berisi panduan lengkap untuk menginstal dan mengkonfigurasi PostgreSQL Backup Manager di berbagai lingkungan.

## Daftar Isi
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi di Linux (Ubuntu/Debian)](#instalasi-di-linux-ubuntudebian)
- [Instalasi di Windows](#instalasi-di-windows)
- [Instalasi di macOS](#instalasi-di-macos)
- [Konfigurasi Web Server](#konfigurasi-web-server)
- [Konfigurasi Database](#konfigurasi-database)
- [Konfigurasi Aplikasi](#konfigurasi-aplikasi)
- [Setup Cron Job](#setup-cron-job)
- [Pemecahan Masalah](#pemecahan-masalah)

## Persyaratan Sistem

### Server
- Sistem Operasi: Linux, Windows, atau macOS
- Web Server: Apache 2.4+ atau Nginx 1.18+
- PHP 7.4 atau lebih baru dengan ekstensi berikut:
  - pdo_pgsql
  - pgsql
  - json
  - mbstring
  - zip (untuk kompresi)
  - gd (untuk captcha, opsional)
- PostgreSQL 10 atau lebih baru
- RAM: Minimal 512MB (direkomendasikan 1GB+)
- Ruang Disk: Tergantung ukuran database yang akan dibackup

### Klien
- Browser web modern (Chrome, Firefox, Safari, Edge)
- Koneksi internet (untuk update dan dependensi)

## Instalasi di Linux (Ubuntu/Debian)

### 1. Update Sistem
```bash
sudo apt update
sudo apt upgrade -y
```

### 2. Instal Dependensi
```bash
# Instal Apache dan PHP
sudo apt install -y apache2 php libapache2-mod-php php-pgsql php-zip php-mbstring php-gd

# Atau untuk Nginx
# sudo apt install -y nginx php-fpm php-pgsql php-zip php-mbstring php-gd

# Instal PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Instal Git dan Tools
sudo apt install -y git unzip
```

### 3. Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/username/backup_postgre.git
sudo chown -R www-data:www-data backup_postgre/
cd backup_postgre
```

### 4. Setel Izin
```bash
sudo chmod -R 775 backups/
sudo chmod -R 775 logs/
sudo chmod -R 775 tmp/
sudo chmod +x scripts/*.php
```

### 5. Konfigurasi Web Server

#### Untuk Apache:
```bash
sudo cp config/apache.conf /etc/apache2/sites-available/backup_postgre.conf
sudo a2ensite backup_postgre.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Untuk Nginx:
```bash
sudo cp config/nginx.conf /etc/nginx/sites-available/backup_postgre
sudo ln -s /etc/nginx/sites-available/backup_postgre /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Instalasi di Windows

### 1. Instal XAMPP/WAMP
1. Download dan instal XAMPP dari https://www.apachefriends.org/
2. Pastikan memilih komponen:
   - Apache
   - PHP
   - phpPgAdmin
   - PostgreSQL

### 2. Instal Git
1. Download dan instal Git dari https://git-scm.com/
2. Gunakan Git Bash untuk perintah berikut

### 3. Clone Repository
```bash
cd C:/xampp/htdocs/
git clone https://github.com/username/backup_postgre.git
```

### 4. Konfigurasi PHP
1. Buka `php.ini` di `C:/xampp/php/php.ini`
2. Aktifkan ekstensi yang diperlukan dengan menghapus tanda `;` di depan:
   ```
   extension=pdo_pgsql
   extension=pgsql
   extension=zip
   extension=mbstring
   extension=gd
   ```
3. Restart Apache melalui XAMPP Control Panel

## Instalasi di macOS

### 1. Instal Homebrew
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

### 2. Instal Dependensi
```bash
# Instal PHP dan ekstensi
brew install php@7.4
brew install php-pdo-pgsql
brew install composer

# Instal PostgreSQL
brew install postgresql@14
brew services start postgresql@14

# Instal Web Server (opsional, bisa menggunakan built-in PHP server)
brew install nginx
```

### 3. Clone Repository
```bash
mkdir -p ~/Sites
cd ~/Sites
git clone https://github.com/username/backup_postgre.git
cd backup_postgre
```

## Konfigurasi Database

1. Buat database dan user PostgreSQL:
```sql
CREATE DATABASE backup_manager;
CREATE USER backup_user WITH PASSWORD 'password_aman';
GRANT ALL PRIVILEGES ON DATABASE backup_manager TO backup_user;
\c backup_manager
\i schema.sql
```

2. Atau gunakan `psql` dari command line:
```bash
psql -U postgres -c "CREATE DATABASE backup_manager;"
psql -U postgres -d backup_manager -f schema.sql
```

## Konfigurasi Aplikasi

1. Salin file konfigurasi contoh:
```bash
cp .env.example .env
```

2. Edit file `.env` dan sesuaikan dengan konfigurasi Anda.

## Setup Cron Job

### Linux/macOS
```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut (sesuaikan path)
* * * * * /usr/bin/php /path/ke/backup_postgre/scripts/cron_backup.php > /dev/null 2>&1
```

### Windows
1. Buka Task Scheduler
2. Buat task baru
3. Atur trigger untuk berjalan setiap menit
4. Action: `C:\xampp\php\php.exe C:\xampp\htdocs\backup_postgre\scripts\cron_backup.php`

## Pemecahan Masalah

### Error Koneksi Database
- Pastikan layanan PostgreSQL berjalan
- Periksa kredensial di file `.env`
- Pastikan user memiliki hak akses yang cukup

### Error Izin
- Pastikan web server memiliki akses tulis ke folder `backups/`, `logs/`, dan `tmp/`
- Di Linux, gunakan `chown` dan `chmod` yang sesuai

### Error Cron Job
- Periksa log cron di `/var/log/syslog` atau `/var/log/cron`
- Pastikan path ke PHP CLI benar
- Tambahkan logging untuk debugging

## Dukungan

Untuk bantuan lebih lanjut, silakan buat issue di [GitHub Issues](https://github.com/username/backup_postgre/issues).
