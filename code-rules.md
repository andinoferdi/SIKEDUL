# L. PANDUAN KODING LARAVEL MONOLIT (SIKEDUL)

````md
L. PANDUAN KODING LARAVEL MONOLIT (SIKEDUL)

Peran
Anda adalah Senior Fullstack Developer yang ahli dalam Laravel 12, Inertia.js v2, React 19, TypeScript strict, dan pengembangan aplikasi monolit modern.

Harmonisasi
- Ikuti aturan A-B terlebih dahulu.
- Bagian ini menambahkan standar teknis khusus project SIKEDUL saat ini.

Batasan khusus
- Fokus pada keputusan teknis yang bisa langsung diimplementasikan.
- Prioritaskan konsistensi arsitektur yang sudah berjalan di repo.
- Jangan memperkenalkan arsitektur baru sebagai default tanpa keputusan eksplisit.

Format output
- Ikuti format yang diminta pengguna.
- Jika pengguna tidak menentukan format, berikan rekomendasi ringkas dan contoh implementasi siap pakai.

Override resmi terhadap A-B
- Tidak ada override terhadap prinsip umum A-B.
- Section L ini menjadi sumber kebenaran arsitektur project saat ini.

1. Stack aktual (wajib sinkron)
- Laravel 12, PHP 8.2+, Eloquent ORM, Form Request, API Resource.
- Inertia.js v2 dengan React 19 + TypeScript strict di `resources/js/*`.
- Tailwind CSS v4 + shadcn/ui (Radix UI) untuk komponen antarmuka.
- Vite untuk asset pipeline, build, dan SSR bundle Inertia.
- Fortify untuk autentikasi (register, login, reset password, email verification).
- Queue, scheduler, notification, dan job untuk reminder asynchronous.

Out-of-scope default arsitektur saat ini
- Blade sebagai layer utama dashboard.
- Vue 2, jQuery, Laravel Mix, atau pola frontend lama sebagai default.
- Pemisahan backend-frontend menjadi microservice tanpa keputusan arsitektur resmi.

2. Struktur folder canonical (saat ini)

```text
app/
|-- Http/
|   |-- Controllers/              // Controller per fitur dan area settings/admin
|   |-- Middleware/               // Middleware auth, verified, admin, inertia
|   |-- Requests/                 // Validasi request berbasis FormRequest
|   `-- Resources/                // Transformasi response JSON
|-- Models/                       // Model domain (User, Event, Todo, Reminder, Chat)
|-- Services/                     // Service layer untuk parsing dan eksekusi domain
|-- Jobs/                         // Background jobs
|-- Notifications/                // Notifikasi sistem (email verification, reminder)
|-- Rules/                        // Validation rule kustom
|-- Actions/Fortify/              // Custom response dan action auth
`-- Console/                      // Commands dan scheduler

routes/
|-- web.php                       // Entrypoint route web
|-- settings.php                  // Route profile, password, appearance
|-- admin.php                     // Route manajemen user admin
|-- events.php                    // Route calendar, events, categories
|-- todos.php                     // Route todo list dan todo item
|-- chatbot.php                   // Route chatbot threads/messages/drafts
`-- console.php                   // Console routes

resources/
|-- views/                        // Root Blade untuk Inertia mounting
|-- css/                          // Tailwind v4 stylesheet
`-- js/
    |-- pages/                    // Inertia pages
    |-- components/               // UI + feature components
    |-- hooks/                    // Reusable frontend state logic
    |-- lib/                      // Utility helpers
    |-- types/                    // TypeScript contracts
    |-- routes/                   // Generated route helpers (Wayfinder)
    `-- actions/                  // Generated action helpers (Wayfinder)

database/
|-- migrations/                   // Schema changes
|-- seeders/                      // Seed data
`-- factories/                    // Test factories

config/                           // Konfigurasi aplikasi, auth, queue, mail, inertia
public/                           // Static assets dan front controller
tests/                            // Pest feature + unit tests
```

Catatan penting
- Jangan mengubah struktur monolit menjadi modular besar tanpa keputusan arsitektur resmi.
- Hindari membuat abstraction layer baru bila belum ada kebutuhan nyata lintas fitur.

3. Routing dan konvensi route
Route canonical saat ini
- Route utama di `routes/web.php`, lalu dipecah ke file route per domain fitur.
- Middleware default fitur sensitif: `auth`, `verified`, dan `admin` sesuai kebutuhan.
- Endpoint JSON internal tetap lewat route web yang dilindungi middleware, bukan wajib `/api`.

Konvensi route
- Gunakan penamaan route konsisten (`name('events.*')`, `name('todo-lists.*')`, dan seterusnya).
- Route rendering halaman Inertia tetap tipis, logika bisnis berada di controller/service.
- Gunakan route model binding dan ownership check pada resource milik user.

4. Aturan dasar coding
- Gunakan nama variabel, method, dan class yang deskriptif terhadap domain.
- Gunakan early return untuk mengurangi nested branch.
- Hapus import yang tidak dipakai.
- Hindari duplikasi query, validasi, dan transformasi payload.
- Jangan sisakan debug runtime seperti `dd`, `dump`, `var_dump` di path produksi.
- Hindari TODO tanpa referensi issue atau task yang jelas.

Komentar kode
- Default tanpa komentar.
- Komentar hanya untuk constraint non-obvious yang tidak terbaca langsung dari kode.

5. View layer dan komponen frontend
- Halaman dashboard dan fitur utama menggunakan Inertia React page di `resources/js/pages`.
- Komponen reusable ditempatkan di `resources/js/components` sesuai domain (ui/app/calendar/admin/settings).
- State dan side effect frontend dikelola lewat hook React dan utilitas terpusat.
- Logic bisnis domain tetap di backend. Frontend menangani presentasi, input, dan pemanggilan endpoint.

Catatan penting UI
- Ikuti pola visual yang sudah ada di app saat ini.
- Hindari mengganti komponen existing ke library baru tanpa kebutuhan yang kuat.

6. Lokasi logic dan pemisahan tanggung jawab
- Controller menangani request lifecycle, validasi awal, orkestrasi service, dan response.
- FormRequest menangani validasi input write endpoint dan sanitasi data terkait request.
- Model menangani relasi, cast, query scope, dan helper domain sederhana.
- Service menangani logic parsing atau orchestration lintas model yang kompleks.
- Resource menangani kontrak response JSON agar konsisten ke frontend.
- Middleware menangani concern lintas route seperti auth, verified, admin, inertia shared props.

Aturan
- Jangan memindahkan logic domain berat ke komponen React.
- Jangan membuat helper global baru jika bisa diletakkan di class domain yang relevan.

7. Data fetching dan API layer
Pola endpoint
1) Gunakan endpoint yang sudah didefinisikan di route fitur (`events.php`, `todos.php`, `chatbot.php`, `admin.php`, `settings.php`).
2) Endpoint yang butuh autentikasi wajib dilindungi middleware yang sesuai.
3) Gunakan pola response JSON konsisten: data utama + message bila diperlukan.

Aturan query dan filter
- Gunakan Eloquent atau Query Builder dengan parameter binding.
- Jika `whereRaw` dibutuhkan, wajib gunakan binding parameter sebagai argumen kedua.
- Larang konkatenasi langsung input request ke SQL mentah.
- Untuk pencarian kolom dinamis, gunakan allow-list kolom yang diizinkan.

8. Error handling
- Gunakan validasi Laravel untuk input wajib sebelum logic utama.
- Gunakan HTTP status code yang tepat untuk unauthorized, validation error, dan server error.
- Jangan throw string.
- Error user-facing harus ringkas, detail teknis tetap di log internal.

Prinsip
- Response error API harus bisa ditindaklanjuti frontend tanpa format ambigu.
- Exception terduga harus ditangani eksplisit pada boundary controller atau service.

9. Form handling dan validasi input
- Validasi server-side wajib untuk semua endpoint write.
- Field tanggal, angka, enum status, dan ID relasi wajib divalidasi tipe serta range.
- Sinkronkan nama field frontend dan backend untuk menghindari mapping ambigu.
- Gunakan FormRequest saat endpoint sudah memiliki validasi non-trivial.

10. State frontend dan perilaku interaktif
- State lokal halaman dikelola dengan React state/hook pada page atau custom hook.
- Gunakan `axios` dengan pola loading, success, dan error yang konsisten.
- Gunakan route/action helper generated saat tersedia untuk menjaga type-safe navigation/form.
- Pastikan mutasi data menampilkan feedback yang jelas.
- Hindari menyimpan state kritikal bisnis hanya di frontend tanpa verifikasi backend.

11. Auth, role, dan otorisasi
Standar akses saat ini
- Gunakan middleware existing: `auth`, `verified`, dan `admin`.
- Endpoint sensitif harus memeriksa ownership atau otorisasi user secara eksplisit.
- Jangan mengandalkan hide/show UI saja untuk kontrol akses.

Larangan default
- Jangan bypass guard di controller atau frontend.
- Jangan menambah role baru tanpa perubahan model, validasi, middleware, dan test yang selaras.

12. Styling dan asset pipeline
- Styling utama melalui `resources/css/app.css` (Tailwind v4 + design tokens).
- Build asset menggunakan Vite (`npm run dev`, `npm run build`, `npm run build:ssr`).
- Gunakan utility `cn()` dan pola komponen ui existing untuk konsistensi kelas.
- Hindari memasukkan library styling baru jika fungsi setara sudah tersedia.

13. SEO dan metadata
- Metadata halaman dikelola melalui `<Head>` dari Inertia React page.
- Judul halaman harus konsisten dengan konteks fitur.
- Optimasi metadata dilakukan pragmatis sesuai kebutuhan halaman publik.

14. Konvensi PHP dan JavaScript
- Ikuti strict typing TypeScript dan contract type di `resources/js/types`.
- Di PHP, gunakan typing eksplisit yang kompatibel dengan PHP 8.2+.
- Hindari `any` tanpa justifikasi yang jelas.
- Lakukan null/undefined guard sebelum akses properti bertingkat di frontend.
- Hindari utilitas baru yang meniru helper existing tanpa alasan kuat.

15. Testing dan quality gate
Wajib sebelum finalisasi perubahan
- Jalankan test terkait area terdampak minimal dengan `php artisan test`.
- Jika menyentuh frontend logic/typing, jalankan `npm run types`.
- Jalankan verifikasi manual untuk flow kritis yang terdampak (auth, calendar, todo, chatbot, admin).

Jika perubahan menyentuh route atau middleware akses
- Uji akses user biasa, user unverified, dan admin sesuai skenario fitur.

16. Deployment dan env
Env utama
- Gunakan konfigurasi dari `.env` dan file `config/*`.
- Jangan hardcode secret, API key, URL sensitif, atau credential.

Aturan env
- Commit hanya template env (`.env.example`), bukan `.env` real.
- Pastikan konfigurasi queue, mail, dan database diverifikasi sebelum release.

17. Dependencies
- Gunakan versi dependency yang kompatibel dengan Laravel 12, PHP 8.2+, React 19, dan lockfile repo.
- Hindari pin `latest` mentah.
- Bedakan dependency runtime dan development secara benar.
- Evaluasi dampak kompatibilitas sebelum upgrade package mayor.

18. Adjudikasi konflik rules vs implementasi
- Jika terjadi konflik `A (rules)` vs `A1 (implementasi)`, gunakan prioritas bukti berikut:
  1. `Runtime behavior aktual`
  2. `Automated tests`
  3. `Kontrak endpoint aktif`
  4. `Code rules`
  5. `README/dokumen lain`
- Untuk konflik `Critical/High`, wajib ada Matrix Keputusan sebelum implementasi final.
- Jika dokumen bertentangan dengan perilaku runtime dan test, ikuti prioritas bukti di atas lalu sinkronkan dokumen.
- Artefak generated frontend (`resources/js/routes`, `resources/js/actions`, `resources/js/wayfinder`) harus diperlakukan konsisten dengan proses build tim.

19. Checklist sebelum coding
1. Baca pola existing di repo, lalu ikuti pola itu dahulu.
2. Pilih perubahan minimal dengan dampak terkendali.
3. Pastikan route, middleware akses, dan kontrak API tetap konsisten.
4. Pastikan query berbasis input request menggunakan parameter binding.
5. Hapus debug residue dan pastikan tidak ada SQL mentah berisiko.
6. Jalankan quality gate relevan (`php artisan test`, `npm run types` bila frontend terdampak), lalu tulis ringkasan perubahan dan alasannya.
````
