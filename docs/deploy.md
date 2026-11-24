# Deploy ke Server (Production)

Panduan menjalankan Zetago di **satu server Linux** (VPS) dengan Docker. Semua perintah di
sini sudah diuji di stack production lokal (`compose.production.yaml` di laptop), kecuali
bagian yang ditandai **(belum diuji di VPS)**.

## Gambaran

```
Internet ──HTTPS──▶ Caddy (di host, port 80/443, sertifikat otomatis)
                      │ http://127.0.0.1:8080
                      ▼
              ┌─────── web (PHP-FPM + Nginx) ──── ssr (Node, render HTML awal)
   Docker     │         │
   (zetago)   │       pgsql (data)    redis (cache, sesi, antrian)
              │         ▲                ▲
              └── queue (job latar) ─ scheduler (tugas terjadwal)
                  migrate (sekali jalan setiap `up`, lalu berhenti)
```

| Service     | Image               | Tugas                                                                   |
| ----------- | ------------------- | ----------------------------------------------------------------------- |
| `migrate`   | `zetago:latest`     | `migrate --force`, lalu selesai. Service lain menunggu ini **berhasil** |
| `web`       | `zetago:latest`     | Melayani halaman di port 8080 (hanya `127.0.0.1`, diakses lewat Caddy)  |
| `queue`     | `zetago:latest`     | Email, pencairan dana, refund, dsb.                                     |
| `scheduler` | `zetago:latest`     | `orders:expire`, `payments:sync`, dsb. (`routes/console.php`)           |
| `ssr`       | `zetago-ssr:latest` | Merender HTML halaman pertama (SEO, tampil lebih cepat)                 |
| `pgsql`     | `postgres:18`       | Database. Tidak membuka port ke luar                                    |
| `redis`     | `valkey:9`          | Cache, sesi, antrian. Pakai password, tidak membuka port ke luar        |

Data yang harus selamat antarrilis ada di **volume** Docker: `zetago_pgsql-data`,
`zetago_redis-data`, dan `zetago_storage-app` (foto produk, logo toko).

## 1. Siapkan server

- VPS Linux (Ubuntu 24.04 atau sejenis), **minimal 2 GB RAM**. Dengan 1 GB, build image
  bisa kehabisan memori. Tambahkan swap 2 GB kalau RAM pas-pasan.
- Docker Engine + plugin Compose: <https://docs.docker.com/engine/install/ubuntu/>
- Domain dengan **A record** mengarah ke IP server (mis. `zetago.example.com`).
- Firewall: buka hanya **22** (SSH), **80**, dan **443**.

## 2. Konfigurasi `.env.production`

```bash
git clone <url-repo> zetago && cd zetago
cp .env.production.example .env.production
chmod 600 .env.production        # hanya pemilik yang bisa membaca
```

Isi nilai yang kosong. Setiap kunci rahasia **baru khusus production**: jangan pakai ulang
kunci sandbox atau kunci yang pernah ditempel di chat, tiket, atau screenshot.

| Variabel                                     | Cara mengisi                                                                                      |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `APP_KEY`                                    | Setelah image dibuat (langkah 3): `docker run --rm zetago:latest php artisan key:generate --show` |
| `APP_URL`                                    | `https://domain-anda`                                                                             |
| `DB_PASSWORD`, `REDIS_PASSWORD`              | `openssl rand -base64 32` (masing-masing berbeda)                                                 |
| `TRUSTED_PROXIES`                            | `172.16.0.0/12` bila Caddy di host yang sama (lihat langkah 4)                                    |
| `MAIL_*`                                     | SMTP dari penyedia email (verifikasi akun dan notifikasi butuh email sungguhan)                   |
| `RAJAONGKIR_API_KEY`                         | Kunci **baru** dari collaborator.komerce.id                                                       |
| `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY` | Dashboard Midtrans **production**: Settings → Access Keys                                         |

`.env.production` tidak pernah masuk git (`.gitignore`) dan tidak masuk image (`.dockerignore`).
Isinya dibaca container saat berjalan.

> **Hati-hati dengan `APP_KEY`.** Kunci ini mengenkripsi sesi, rahasia 2FA, dan data
> terenkripsi lain di database. Menggantinya setelah ada pengguna membuat semua itu tidak
> bisa dibaca. Simpan salinannya di password manager.

## 3. Build dan jalankan

```bash
docker build --target production -t zetago:latest .
docker build --target ssr -t zetago-ssr:latest .

docker compose -f compose.production.yaml --env-file .env.production up -d
```

`--env-file .env.production` **wajib**. Tanpanya Compose mengisi `${...}` di file compose dari
`.env` dev. Variabel wajib yang kosong (mis. `DB_PASSWORD`) membuat perintah langsung berhenti
dengan pesan jelas, bukan jalan dengan password kosong.

Agar tidak mengetik panjang, buat alias di `~/.bashrc`:

```bash
alias zp='docker compose -f compose.production.yaml --env-file .env.production'
# zp ps, zp logs -f web, zp exec web php artisan about, ...
```

Urutan saat `up`: `pgsql` + `redis` sehat → `migrate` selesai → `web`, `queue`, `scheduler`
menyala. Kalau migrasi gagal, container lama tetap melayani dan kode baru tidak dijalankan
di atas database setengah jadi.

## 4. HTTPS dengan Caddy (belum diuji di VPS)

Caddy mengurus sertifikat Let's Encrypt otomatis dan memperpanjangnya sendiri.

```bash
sudo apt install caddy     # lihat caddyserver.com/docs/install untuk repo resminya
```

`/etc/caddy/Caddyfile`:

```
zetago.example.com {
    reverse_proxy 127.0.0.1:8080
    encode zstd gzip
}
```

```bash
sudo systemctl reload caddy
```

**Kenapa `TRUSTED_PROXIES=172.16.0.0/12`?** Dari sudut pandang container, request dari Caddy
datang dari gateway jaringan Docker (alamat `172.x.x.x`), bukan dari IP pengunjung. Caddy
menaruh IP asli di header `X-Forwarded-For`. Laravel hanya memercayai header itu bila
pengirimnya ada di `TRUSTED_PROXIES`. Port 8080 hanya terbuka di `127.0.0.1`, jadi satu-satunya
pengirim dari rentang itu adalah Caddy.

**Cara memeriksa:** login sebagai admin, setujui atau tangguhkan sebuah toko, lalu buka
**Admin → Audit**. IP yang tercatat harus IP internet Anda, **bukan** `172.x.x.x`. Kalau masih
`172.x`, `TRUSTED_PROXIES` belum cocok. Akibatnya, semua pengunjung dianggap satu IP dan saling
berbagi batas percobaan login.

## 5. Setelah pertama kali jalan

1. **Akun admin.** Daftar lewat halaman `/register`, verifikasi email, lalu:
    ```bash
    zp exec web php artisan app:make-admin email@anda.com
    ```
    Halaman admin mewajibkan **2FA atau passkey** (`ADMIN_REQUIRE_2FA=true`). Aktifkan di
    Settings → Security sebelum membuka menu Admin.
2. **Webhook Midtrans.** Dashboard production: _Settings → Payment → Notification URL_ =
   `https://domain-anda/webhooks/midtrans`.
3. **Kategori.** Buat kategori di Admin → Kategori sebelum penjual menambah produk.
4. **Jangan jalankan `DemoSeeder`** di production. Seeder itu sengaja hanya berjalan di
   lingkungan `local`.

## 6. Memeriksa kesehatan

```bash
zp ps -a                            # semua "healthy", migrate "Exited (0)"
curl -fsS http://127.0.0.1:8080/up  # health check Laravel
zp logs -f web queue scheduler      # log aplikasi (LOG_CHANNEL=stderr)
zp logs --tail=50 ssr
zp exec web php artisan about       # ringkasan konfigurasi (debug harus OFF)
```

Tanda yang perlu ditindaklanjuti:

| Di log                                           | Artinya                                                          |
| ------------------------------------------------ | ---------------------------------------------------------------- |
| `Midtrans snap` / `Midtrans status` `warning`    | Midtrans tidak terjangkau atau error 5xx                         |
| `SSR server unreachable, skipping SSR for 30 s`  | Container `ssr` mati. Halaman tetap tampil (dirender di browser) |
| `bad_signature` di tabel `payment_notifications` | Server key di `.env.production` beda dengan dashboard Midtrans   |
| Halaman **Admin → Keuangan** ada baris "Macet"   | Pencairan/refund tertahan: cek `zp ps queue` dan log `queue`     |

## 7. Merilis versi baru

```bash
git pull

# Simpan image yang sedang jalan untuk rollback
docker tag zetago:latest zetago:previous
docker tag zetago-ssr:latest zetago-ssr:previous

docker build --target production -t zetago:latest .
docker build --target ssr -t zetago-ssr:latest .
zp up -d          # migrate dulu, lalu container baru menggantikan yang lama
```

Ada jeda beberapa detik saat `web` diganti. Untuk toko kecil ini bisa diterima.

**Rollback** (rilis baru bermasalah):

```bash
docker tag zetago:previous zetago:latest
docker tag zetago-ssr:previous zetago-ssr:latest
zp up -d
```

Rollback image **tidak** membatalkan migrasi. Karena itu, tulis migrasi agar kode versi
sebelumnya tetap bisa berjalan di atasnya: menambah kolom atau tabel aman, sedangkan menghapus
atau mengganti nama kolom sebaiknya dilakukan di rilis berikutnya, setelah kode lama tidak
memakainya lagi.

## 8. Backup dan restore

Yang perlu dibackup: **database** dan **volume storage** (foto). Redis berisi cache, sesi,
dan antrian job. Bila hilang, pengguna perlu login ulang dan job yang belum diproses ikut
hilang. Pembayaran tetap tersusul oleh `payments:sync`, dan pencairan atau refund yang
tertahan terlihat sebagai "Macet" di Admin → Keuangan untuk dicoba ulang.

```bash
mkdir -p ~/backup
# Database (format custom, sudah terkompresi)
zp exec -T pgsql pg_dump -U zetago -d zetago -Fc > ~/backup/db-$(date +%F).dump
# Foto produk dan logo toko
docker run --rm -v zetago_storage-app:/data:ro -v ~/backup:/backup alpine \
  tar czf /backup/storage-$(date +%F).tgz -C /data .
```

Sesuaikan `-U` dan `-d` dengan `DB_USERNAME` dan `DB_DATABASE`. Jadwalkan harian lewat
`crontab -e`, dan **salin ke luar server** (mis. object storage). Backup di disk yang sama
ikut hilang bila server rusak.

**Uji restore** secara berkala ke database sementara, karena backup yang belum pernah dicoba
dikembalikan belum terbukti berguna:

```bash
zp exec -T pgsql createdb -U zetago restore_test
zp exec -T pgsql pg_restore -U zetago -d restore_test --no-owner < ~/backup/db-2026-01-01.dump
zp exec -T pgsql psql -U zetago -d restore_test -c "select count(*) from users"
zp exec -T pgsql dropdb -U zetago restore_test
```

Restore sungguhan (setelah bencana): matikan `web`, `queue`, `scheduler`, kosongkan database,
`pg_restore` ke database `zetago`, ekstrak `storage-*.tgz` ke volume, lalu `zp up -d`.

## 9. SSR (Server-Side Rendering)

Halaman pertama setiap kunjungan dirender jadi HTML lengkap oleh container `ssr`. Hasilnya,
mesin pencari dan pratinjau link (WhatsApp dll.) melihat isi halaman, dan pengunjung melihat
konten sebelum JavaScript selesai dimuat. Navigasi berikutnya ditangani React di browser.

- `web` menghubungi `http://ssr:13714` (diatur di `compose.production.yaml`).
- Bila `ssr` tidak terjangkau, halaman **tetap tampil** dengan render di browser. Supaya
  pengunjung tidak menunggu koneksi gagal di setiap halaman, SSR dilewati selama **30 detik**
  setelah kegagalan koneksi (_circuit breaker_), lalu dicoba lagi.
- Error di satu halaman (bug React) tidak mematikan SSR untuk halaman lain.
- Image `zetago-ssr` hanya berisi Node + satu file bundel. Tidak ada `node_modules`,
  berjalan sebagai user `node`, filesystem read-only.

## Mencoba di laptop

Stack yang sama bisa dijalankan di laptop (http://127.0.0.1:8080), berdampingan dengan dev.
Bedanya di `.env.production`:

```
APP_URL=http://localhost:8080
SESSION_SECURE_COOKIE=false   # tanpa HTTPS, cookie "secure" tidak dikirim browser
MAIL_MAILER=log               # email ditulis ke log, tidak dikirim
MIDTRANS_PRODUCTION=false     # kunci sandbox
```

Matikan dengan `zp down`. Volume (data) tetap ada. `zp down -v` menghapusnya juga.

## Troubleshooting

| Gejala                                          | Penyebab dan solusi                                                                                              |
| ----------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `required variable ... is missing a value`      | Variabel wajib kosong di `.env.production`, atau lupa `--env-file`                                               |
| `web` tidak menyala, `migrate` `Exited (1)`     | Migrasi gagal: `zp logs migrate`                                                                                 |
| Error 500, log: `No application encryption key` | `APP_KEY` kosong                                                                                                 |
| Tidak bisa login / form "Page Expired" di HTTP  | `SESSION_SECURE_COOKIE=true` tanpa HTTPS. Pakai HTTPS, atau `false` di laptop                                    |
| Semua pengunjung tercatat IP `172.x.x.x`        | `TRUSTED_PROXIES` belum diisi (langkah 4)                                                                        |
| Pembayaran tidak berubah status                 | Notification URL Midtrans salah, atau server key beda. Status tetap tersusul lewat `payments:sync` tiap 10 menit |
| Foto produk hilang setelah rilis                | Volume `zetago_storage-app` terhapus (`down -v`). Restore dari backup                                            |
