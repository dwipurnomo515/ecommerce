# Tahap 1: Fondasi

**Tujuan:** punya project yang bisa jalan di komputer, dengan environment yang sama seperti nanti
di server production, dan tersimpan rapi di Git.

**Hasil akhir:** Laravel 13 + React 19 berjalan di Docker, auth lengkap, database PostgreSQL,
dan tiga commit pertama.

---

## Langkah 0: Memilih arsitektur

Ada dua cara umum menggabungkan Laravel dan React:

| Pilihan                     | Cara kerja                                                             |
| --------------------------- | ---------------------------------------------------------------------- |
| **A. Inertia.js** (dipilih) | Satu project. Laravel mengatur route & data, React menampilkan halaman |
| B. API + SPA terpisah       | Dua project. Laravel hanya API (JSON), React adalah aplikasi sendiri   |

**Kenapa Inertia:**

- Satu codebase, satu deployment, jadi lebih sederhana untuk dirawat sendirian.
- Tidak perlu mengurus CORS dan token API.
- Ada **starter kit resmi** dari Laravel, lengkap dengan login, register, 2FA, dll. Auth buatan
  sendiri sangat rawan celah keamanan, jadi sebaiknya pakai yang sudah teruji.
- Kalau nanti butuh aplikasi mobile, API bisa ditambahkan di samping Inertia.

## Langkah 1: Mengecek tools yang ada

```
php       NOT FOUND
composer  NOT FOUND
node      NOT FOUND
git       ✅
docker    ✅
```

Hanya Git dan Docker yang terpasang. Ternyata itu sudah cukup: **semua tools lain dijalankan di
dalam Docker**. Keuntungannya, environment di laptop sama dengan di server, sehingga tidak ada
lagi masalah "di laptop saya jalan, di server error".

Docker Desktop ternyata belum menyala (`failed to connect to the docker API`), jadi harus
dibuka dulu.

## Langkah 2: Membuat kerangka project (scaffold)

```bash
docker run --rm -v "<folder project>:/app" -w /app composer:2 \
  create-project laravel/react-starter-kit .
```

Artinya: jalankan image `composer:2` sekali pakai (`--rm`), hubungkan folder project ke `/app`
di dalam container (`-v`), lalu buat project dari starter kit.

### ❌ Masalah 1: yang terpasang ternyata versi lama

Hasil pertama memakai **Laravel 12**, padahal versi terbaru adalah **Laravel 13**. Penyebabnya:
paket starter kit di Packagist punya tag lama (`v1.0.1`), sedangkan versi terbaru ada di branch
`dev-main` (inilah yang dipakai oleh perintah `laravel new`).

**Pelajaran:** setelah instalasi, selalu cek versi yang benar-benar terpasang
(`composer show laravel/framework`). Jangan berasumsi.

### ❌ Masalah 2: gagal menghapus file sementara

Saat mencoba ulang dengan `:dev-main`, muncul error `Could not delete .../storage/framework/testing`.
Folder Windows yang di-_mount_ ke Docker kadang bermasalah saat banyak file dihapus dengan cepat.

**Solusi:** buat project di dalam filesystem container (`/tmp/app`), lalu salin hasilnya ke
folder Windows:

```bash
sh -c 'cd /tmp && composer create-project laravel/react-starter-kit:dev-main app && cp -a /tmp/app/. /app/'
```

Hasilnya Laravel 13 + Inertia 3 + React 19, dengan PHPStan (Larastan) dan Pint yang sudah
dikonfigurasi.

## Langkah 3: Menyiapkan Docker

### `Dockerfile`: resep membuat environment

Dockerfile dibagi menjadi beberapa **stage** (`FROM ... AS nama`):

| Stage         | Isi                                                                            |
| ------------- | ------------------------------------------------------------------------------ |
| `node`        | Image Node 24, hanya dipakai untuk mengambil program `node` & `npm`            |
| `base`        | PHP 8.5 + Nginx (`serversideup/php`) + ekstensi `intl gd bcmath exif`          |
| `development` | `base` + Node, supaya artisan, composer, dan Vite bisa jalan di satu container |
| `production`  | _(nanti di Tahap 8)_ image final yang kecil dan aman                           |

**Kenapa multi-stage:** dev dan production memakai fondasi (`base`) yang sama, sehingga
perilakunya identik, tapi image production nanti tidak membawa tools development.

**Kenapa ekstensi itu:** `intl` untuk format angka/tanggal Indonesia, `gd` untuk mengolah gambar
produk, `bcmath` untuk perhitungan uang yang presisi, dan `exif` untuk membaca orientasi foto.

### `compose.yaml`: menjalankan banyak container sekaligus

| Service   | Fungsi                                             | Akses                 |
| --------- | -------------------------------------------------- | --------------------- |
| `app`     | PHP-FPM + Nginx, melayani request web              | http://localhost:8000 |
| `vite`    | Dev server frontend, _hot reload_ saat kode diubah | port 5173             |
| `queue`   | Menjalankan job di background (mis. kirim email)   | –                     |
| `pgsql`   | Database PostgreSQL 18                             | `localhost:5432`      |
| `redis`   | Valkey (kompatibel Redis): cache, session, antrian | –                     |
| `mailpit` | Menangkap semua email keluar saat development      | http://localhost:8025 |

Detail penting:

- **`healthcheck` + `depends_on: condition: service_healthy`**: `app` baru dinyalakan setelah
  database benar-benar siap menerima koneksi, bukan sekadar container-nya sudah jalan.
- **Database `testing` terpisah** dibuat otomatis oleh `docker/pgsql/create-testing-database.sql`.
  Ini sangat berguna di Tahap 2.
- **`x-app: &app`** adalah _YAML anchor_: konfigurasi ditulis sekali lalu dipakai ulang oleh
  `app`, `queue`, dan `vite` (`<<: *app`).
- **Named volume** (`pgsql-data`) menyimpan data database, jadi data tetap ada walaupun container
  dihapus.

### `.dockerignore`

Mirip `.gitignore`, tapi untuk Docker. Isinya file yang tidak boleh ikut masuk image: `.env`
(rahasia), `node_modules`, `vendor`, log, dll.

### Mengubah `vite.config.ts`

Agar Vite bisa jalan di dalam Docker:

- `host: '0.0.0.0'`: dengarkan koneksi dari luar container (bawaannya hanya dari dalam).
- `hmr: { host: 'localhost' }`: browser terhubung ke Vite lewat `localhost`.
- `usePolling: true`: perubahan file dari Windows **tidak** mengirim notifikasi ke Linux, jadi
  Vite harus rutin mengecek sendiri apakah ada file yang berubah.

Semua pengaturan ini hanya aktif jika `VITE_IN_DOCKER=true`.

## Langkah 4: Konfigurasi `.env`

`.env` berisi pengaturan dan **rahasia** (password database, `APP_KEY`), sehingga **tidak boleh**
masuk Git. `.env.example` adalah template tanpa rahasia yang boleh di-commit.

Yang diubah dari bawaan:

| Setting                                             | Dari       | Menjadi           | Alasan                                    |
| --------------------------------------------------- | ---------- | ----------------- | ----------------------------------------- |
| `DB_CONNECTION`                                     | `sqlite`   | `pgsql`           | Database yang serius untuk production     |
| `DB_HOST`                                           | –          | `pgsql`           | Nama service di `compose.yaml`            |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | `database` | `redis`           | Lebih cepat dan mengurangi beban database |
| `MAIL_MAILER`, `MAIL_HOST`                          | `log`      | `smtp`, `mailpit` | Email bisa dilihat di Mailpit             |

> 💡 Di dalam Docker, container saling memanggil dengan **nama service**, bukan `127.0.0.1`.

## Langkah 5: Memilih fitur auth (`install:features`)

Starter kit baru punya installer interaktif untuk memilih fitur auth. Kita aktifkan **semua**:
registrasi, verifikasi email, 2FA, passkeys, dan konfirmasi password. Untuk toko online,
keamanan akun pelanggan dan admin itu penting.

### ❌ Masalah 3: `npm: not found`

Installer menjalankan `npm install`, padahal image `composer:2` tidak punya Node.
**Solusi:** membuat image development sendiri (stage `development` di atas) yang berisi PHP
**dan** Node.

### ❌ Masalah 4: `Permission denied`

**Diagnosa:**

```
user: uid=33(www-data)                                ← container jalan sebagai www-data
drwxr-xr-x root root  /var/www/html/storage/logs      ← folder milik root
touch: Permission denied
```

Docker Desktop menampilkan file dari folder Windows sebagai milik `root`. Windows tidak mengenal
permission Linux, jadi `chown` tidak berpengaruh.

**Solusi:** container development dijalankan sebagai `root` (`user: root` di `compose.yaml`).
Ini **khusus development**. Di production, container tetap berjalan sebagai `www-data`, karena
menjalankan aplikasi web sebagai root berbahaya kalau ada celah keamanan.

### ✅ Yang dilakukan installer

| Tahap                        | Artinya                                                                    |
| ---------------------------- | -------------------------------------------------------------------------- |
| Installing dependencies      | `npm install`, mengunduh paket JavaScript                                  |
| Composer Lint                | Pint merapikan format kode PHP                                             |
| Generate Wayfinder Resources | Membuat fungsi TypeScript dari route Laravel (`login()`, bukan `'/login'`) |
| Building assets              | Meng-_compile_ React/TS/CSS ke `public/build`                              |

Setelah selesai, installer menghapus dirinya sendiri (`chisel.php` dkk.).

**Kenapa Wayfinder bagus:** kalau URL sebuah route berubah, TypeScript langsung menandai semua
tempat di React yang memakainya. Tanpa Wayfinder, link yang rusak baru ketahuan saat diklik user.

## Langkah 6: Menyalakan aplikasi

```bash
docker compose up -d                          # nyalakan semua container (-d = di background)
docker compose exec app php artisan migrate   # buat tabel di database
```

**Migration** adalah file PHP yang mendeskripsikan struktur tabel. Struktur database jadi ikut
tersimpan di Git, dan semua developer (serta server) punya tabel yang sama.

### ❌ Masalah 5: error 500 di semua halaman

Container sudah jalan sebagai root, tapi **PHP-FPM** menjalankan _worker_-nya (proses yang
melayani request web) sebagai `www-data`. Ini fitur keamanan bawaan PHP-FPM. Akibatnya, Laravel
tidak bisa menulis log.

**Solusi:** beri izin tulis hanya pada dua folder yang memang perlu ditulis Laravel:

```bash
chmod -R ugo+rwX storage bootstrap/cache
```

### ⚠️ Masalah 6: setiap halaman butuh 5–10 detik

**Diagnosa bertahap:**

1. Coba OPcache (cache hasil compile PHP): **tidak membantu**.
2. Ukur per bagian: halaman `/up`, yang hampir tidak melakukan apa-apa, juga butuh 7 detik.
   Berarti masalahnya ada di proses _booting_ Laravel, bukan di React.
3. Benchmark akses file:

```
stat 1750 file di folder Windows (bind mount):      6327 ms
stat file yang sama di filesystem Linux container:     23 ms   ← ~275x lebih cepat
```

Setiap kali sebuah file di folder Windows dicek, permintaannya harus "menyeberang" dari Linux
(Docker) ke Windows, dan itu lambat sekali.

**Pilihan yang ada:**

1. Pindahkan project ke filesystem WSL: cepat dan setup lebih bersih _(direkomendasikan)_
2. Hybrid: `vendor/` dan `node_modules/` disimpan di Docker volume. Lebih cepat, tapi autocomplete
   di VS Code hilang.
3. **Biarkan saja** ← **dipilih**

**Keputusan sadar:** tetap di folder Windows dan menerima kelambatan ini. Opsi WSL tetap terbuka
kapan saja.

## Langkah 7: Git dan commit pertama

```bash
git init -b main
git add -A
git commit
```

Sebelum commit, kita **memastikan** file rahasia tidak ikut:

| File/folder                          | Status di Git     | Kenapa                                                      |
| ------------------------------------ | ----------------- | ----------------------------------------------------------- |
| `.env`                               | ❌ diabaikan      | Berisi rahasia                                              |
| `.env.example`                       | ✅ ikut           | Template tanpa rahasia                                      |
| `vendor/`, `node_modules/`           | ❌ diabaikan      | Bisa dibuat ulang dengan `composer install` / `npm install` |
| `composer.lock`, `package-lock.json` | ✅ **wajib** ikut | Mengunci versi persis semua library                         |

> ⚠️ Kalau rahasia sempat ter-push ke GitHub, anggap sudah bocor (ganti password/key-nya),
> walaupun file itu dihapus belakangan. History Git menyimpan semuanya.

**Conventional Commits:** pesan commit diawali jenis perubahan.

| Awalan   | Dipakai untuk                        |
| -------- | ------------------------------------ |
| `feat:`  | Fitur baru                           |
| `fix:`   | Perbaikan bug                        |
| `docs:`  | Dokumentasi                          |
| `style:` | Format kode, tanpa mengubah perilaku |
| `ci:`    | Konfigurasi CI                       |
| `chore:` | Setup dan perawatan                  |

**`.gitattributes`** dengan `eol=lf` memaksa format baris ala Linux, supaya file yang diedit di
Windows tidak rusak saat dipakai di Docker atau server.

## Langkah 8: README

[README.md](../../README.md) menjawab tiga pertanyaan:

1. Ini project apa? (tech stack)
2. Bagaimana cara menjalankannya dari nol? (langkah yang bisa langsung di-copy-paste)
3. Kalau error, harus bagaimana? (troubleshooting)

---

## Ringkasan kompromi di Tahap 1

| Kompromi                                     | Alasan                          | Berlaku di       |
| -------------------------------------------- | ------------------------------- | ---------------- |
| Container jalan sebagai `root`               | Permission folder Windows       | Development saja |
| `chmod` pada `storage/` & `bootstrap/cache/` | Worker PHP-FPM tetap `www-data` | Development saja |
| Halaman lambat (5–10 detik)                  | Akses file Windows ↔ Docker     | Development saja |

Ketiganya akan hilang kalau project dipindah ke WSL, dan tidak berlaku di production.
