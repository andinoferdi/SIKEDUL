# SIKEDUL

Sistem Informasi Penjadwalan Cerdas berbasis Laravel 12, Inertia.js v2, React 19, dan TypeScript.

## Persyaratan Sistem

- PHP 8.2 atau lebih baru
- Composer 2.x
- Node.js 20.x atau lebih baru
- npm 10.x atau lebih baru

## Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/andinoferdi/SIKEDUL.git
cd SIKEDUL
```

### 2. Install Dependensi PHP

```bash
composer install
```

### 3. Install Dependensi JavaScript

```bash
npm install
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Setup Environment

Salin file environment dan sesuaikan konfigurasi sesuai kebutuhan Anda.

Proyek ini menggunakan SQLite sebagai database default. File database akan dibuat otomatis saat menjalankan migrasi.

Jika Anda ingin menggunakan MySQL atau PostgreSQL, ubah konfigurasi `DB_CONNECTION` dan variabel database lainnya di file `.env`.

#### Setup Email Verification dengan Gmail SMTP

Untuk mengirim email verification ke Gmail, Anda perlu konfigurasi SMTP dengan App Password:

**Langkah 1: Aktifkan 2-Step Verification**

1. Buka https://myaccount.google.com/security
2. Aktifkan **"2-Step Verification"** atau **"Verifikasi 2 Langkah"**

**Langkah 2: Buat Google App Password**

1. Buka langsung: https://myaccount.google.com/apppasswords
2. Login ulang jika diminta
3. Ketik nama aplikasi: `SIKEDUL Email Verification`
4. Klik **"Buat"** atau **"Create"**
5. Salin password 16 karakter yang muncul (contoh: `abcd efgh ijkl mnop`)
6. Hapus spasi: `abcdefghijklmnop`

**Langkah 3: Update File `.env`**

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=email-anda@gmail.com
MAIL_PASSWORD=password-app-16-karakter
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="email-anda@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**Langkah 4: Clear Cache**

```bash
php artisan config:clear
php artisan cache:clear
```

Setelah konfigurasi, email verification akan dikirim ke inbox Gmail.

### 6. Jalankan Migrasi dan Seeder

```bash
php artisan migrate:fresh --seed
```

Perintah ini akan membuat tabel database dan mengisi data awal termasuk akun demo.

### 7. Jalankan Development Server

```bash
npm run serve
```

Perintah ini akan menjalankan Laravel development server dan Vite secara bersamaan. Akses aplikasi di `http://localhost:8000`.

Alternatif lain, Anda bisa menggunakan perintah Composer yang menjalankan server, queue, dan Vite sekaligus:

```bash
composer run dev
```

## Akun Demo

Setelah menjalankan seeder, Anda bisa login menggunakan akun berikut:

| Role  | Email             | Password |
| ----- | ----------------- | -------- |
| Admin | admin@example.com | password |
| User  | user@example.com  | password |

Akun admin memiliki akses ke dashboard. Akun user reguler belum terverifikasi email.

## Teknologi

- Laravel 12
- Inertia.js v2
- React 19
- TypeScript (strict mode)
- Tailwind CSS v4
- Laravel Wayfinder (type-safe routing)
- Laravel Fortify (authentication)
- Vite

## Lisensi

MIT License
