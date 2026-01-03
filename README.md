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
