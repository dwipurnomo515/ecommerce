# Ecommerce

Aplikasi web e-commerce berbasis **Laravel 13** + **React 19** (TypeScript) dengan **Inertia.js**.

## Tech stack

| Bagian       | Teknologi                                                  |
| ------------ | ---------------------------------------------------------- |
| Backend      | Laravel 13, PHP 8.5                                        |
| Frontend     | React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui |
| Auth         | Laravel Fortify (registrasi, verifikasi email, 2FA, passkeys) |
| Database     | PostgreSQL 18                                              |
| Cache/Queue  | Valkey (kompatibel Redis)                                  |
| Email (dev)  | Mailpit                                                    |
| Runtime      | Docker (`serversideup/php` FPM + Nginx)                    |

## Prasyarat

Cukup **Git** dan **Docker Desktop**. PHP, Composer, dan Node **tidak perlu** dipasang di komputer
karena semuanya berjalan di dalam container.

## Menjalankan pertama kali

```bash
# 1. Clone & siapkan konfigurasi
git clone <url-repo> ecommerce && cd ecommerce
cp .env.example .env

# 2. Build image & install dependency (PHP + JS)
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app npm install

# 3. Buat APP_KEY & izinkan Laravel menulis ke storage
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm --no-deps app chmod -R ugo+rwX storage bootstrap/cache

# 4. Nyalakan semua service & buat tabel database
docker compose up -d
docker compose exec app php artisan migrate
```

Setelah itu buka:

| URL                     | Isi                                   |
| ----------------------- | ------------------------------------- |
| http://localhost:8000   | Aplikasi                              |
| http://localhost:8025   | Mailpit, kotak masuk email saat dev   |
| `localhost:5432`        | PostgreSQL (user/pass: lihat `.env`)  |

## Perintah sehari-hari

Semua perintah PHP/Node dijalankan **di dalam container** `app`:

```bash
docker compose up -d                         # nyalakan
docker compose down                          # matikan (data DB tetap aman)
docker compose logs -f app vite queue        # lihat log

docker compose exec app php artisan migrate  # jalankan migration
docker compose exec app php artisan tinker   # REPL Laravel
docker compose exec app composer test        # lint + static analysis + test
docker compose exec app npm run check        # lint & format frontend
docker compose exec app bash                 # masuk shell container
```

## Struktur service (`compose.yaml`)

- **app**: PHP-FPM + Nginx, melayani request web di port 8000
- **vite**: dev server frontend dengan hot reload (port 5173)
- **queue**: worker untuk job di background (kirim email, dll.)
- **pgsql**: database utama, ditambah database `testing` untuk automated test
- **redis**: cache, session, dan antrian job
- **mailpit**: menangkap semua email keluar saat development

## Catatan untuk Windows

Project ini berjalan dari folder Windows (bind mount), sehingga ada dua konsekuensi:

1. **Permission**: container dev berjalan sebagai `root`, dan `storage/` serta `bootstrap/cache/`
   perlu di-`chmod` (langkah 3 di atas). Ini **hanya untuk development**. Image production
   tetap berjalan sebagai user non-root `www-data`.
2. **Performa**: akses file dari Docker ke folder Windows lambat, jadi tiap halaman butuh beberapa
   detik. Untuk development yang jauh lebih cepat, simpan project di filesystem WSL
   (mis. `~/ecommerce` di Ubuntu) dan buka lewat ekstensi VS Code **WSL**.

## Troubleshooting

| Gejala                                         | Solusi                                                         |
| ---------------------------------------------- | -------------------------------------------------------------- |
| Error 500 `laravel.log ... Permission denied`  | Jalankan ulang perintah `chmod` di langkah 3                   |
| Port 8000/5432/5173 sudah dipakai              | Ganti `APP_PORT` / `FORWARD_DB_PORT` / `VITE_PORT` di `.env`   |
| Perubahan React tidak muncul                   | `docker compose logs vite` lalu `docker compose restart vite`  |
| Halaman blank, asset 404                       | Pastikan service `vite` berjalan (`docker compose ps`)         |
