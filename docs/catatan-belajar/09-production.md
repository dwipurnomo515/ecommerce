# Tahap 9: Production

**Tujuan:** membuat aplikasi siap dijalankan di server sungguhan: image Docker yang ramping dan
aman, stack production yang menyala dengan urutan benar, halaman yang terbaca mesin pencari
(SSR), dan panduan yang bisa diikuti saat VPS sudah ada.

**Hasil akhir:** 371 automated test, semuanya lolos. Stack production berjalan dan teruji di
laptop. Deploy ke VPS menunggu servernya ada.

---

## Peta perubahan

| Bagian                    | File utama                                                             |
| ------------------------- | ---------------------------------------------------------------------- |
| Image production          | `Dockerfile` (target `production` dan `ssr`), `.dockerignore`          |
| Stack production          | `compose.production.yaml`, `.env.production.example`, `EnvExampleTest` |
| Proxy HTTPS               | `config/trustedproxy.php`, `TrustedProxiesTest`                        |
| Log Midtrans              | `Midtrans::logCall()`                                                  |
| SSR                       | `resources/js/ssr.tsx`, `inertia-options.tsx`, `config/inertia.php`    |
| _Circuit breaker_ SSR     | `AppServiceProvider::configureSsr()`, `SsrCircuitBreakerTest`          |
| Ringkasan per peran       | `DashboardController`, `pages/dashboard.tsx`, `needsAttention()` scope |
| Audit dependensi otomatis | `.github/workflows/audit.yml`                                          |
| Panduan deploy            | `docs/deploy.md`                                                       |

---

## 1. Image production: kirim hasilnya, bukan bengkelnya

Image dev berisi semua alat kerja (Node, Composer, paket dev, kode test). Image production hanya
berisi yang dibutuhkan untuk **menjalankan** aplikasi.

- **Multi-stage build:** tahap `vendor` memasang paket PHP tanpa paket dev, tahap `assets`
  mem-build React dengan Node, lalu tahap `production` hanya **menyalin hasilnya**. Node dan
  `node_modules` tidak ikut sama sekali.
- **Non-root:** berjalan sebagai `www-data`. Kalau ada celah di aplikasi, penyerang tidak
  langsung menjadi root di container.
- **Tidak ada `.env` di dalam image.** Rahasia masuk dari lingkungan saat container berjalan,
  jadi image boleh disimpan di registry tanpa membocorkan apa pun.
- **OPcache tanpa cek timestamp:** kode di image tidak pernah berubah, jadi PHP tidak perlu
  memeriksa file di setiap request.
- Cache config, route, dan view dibuat otomatis saat container menyala.

## 2. Stack production: urutan menyala itu penting

```
pgsql + redis sehat ──▶ migrate (sekali jalan) ──▶ web, queue, scheduler
```

- **`migrate` adalah service sekali jalan.** Service lain menunggu sampai migrasi **selesai
  dengan sukses** (`service_completed_successfully`). Migrasi yang gagal membuat container lama
  tetap melayani, bukan kode baru yang berjalan di atas database setengah jadi.
- Kenapa tidak `migrate` di setiap container `web`? Kalau ada 2 container `web`, keduanya akan
  bermigrasi **bersamaan**.
- **Database dan Redis tidak membuka port ke luar.** Redis wajib password.
- **Foto ada di _named volume_**, bukan di image. Rilis baru (image baru) tidak menghapusnya.
- **`${VAR:?pesan}`** di file compose: variabel wajib yang kosong membuat Compose berhenti dengan
  pesan jelas, bukan menyala dengan password kosong.
- **`--env-file .env.production` wajib.** Tanpanya, Compose mengisi `${...}` dari `.env` dev.
  Ini mudah terlewat karena tetap "berjalan", hanya dengan nilai yang salah.

Pemeriksaan di laptop: `/.env` mengembalikan 403, debug OFF, Redis menolak koneksi tanpa
password, antrian memproses job, scheduler punya 3 tugas, dan foto selamat setelah container
dibuat ulang.

## 3. Kunci yang bocor ke git

Saat menyiapkan `.env.production.example`, ketahuan bahwa **API key RajaOngkir pernah ditempel
ke `.env.example`** dan ikut di-push di Tahap 5.

- Menghapusnya dari file **tidak cukup**: kunci itu tetap ada di riwayat git selamanya. Satu-satunya
  perbaikan nyata adalah **mengganti kuncinya** di penyedia layanan (_rotate_).
- Pencegahannya sekarang berupa test: `EnvExampleTest` gagal kalau variabel bernama
  `KEY`/`SECRET`/`PASSWORD`/`TOKEN` di **kedua** file contoh punya isi.
- Aturan untuk production: setiap kunci **baru**. Kunci yang pernah ditempel di chat, tiket, atau
  screenshot dianggap sudah bocor.

## 4. Di belakang proxy, semua orang punya IP yang sama

Di production, HTTPS ditangani _reverse proxy_ (Caddy). Dari sudut pandang Laravel, **setiap**
request datang dari IP proxy.

- Akibatnya: batas percobaan login berlaku untuk **semua pengunjung sekaligus**. Satu orang yang
  salah password 5 kali mengunci semua orang.
- Proxy menaruh IP asli di header `X-Forwarded-For`. Tapi header itu bisa dikirim **siapa saja**,
  jadi Laravel hanya memercayainya dari alamat di `TRUSTED_PROXIES`.
- Kosong = tidak percaya siapa pun. `*` = siapa pun bisa mengaku ber-IP apa pun dan lolos dari
  throttle. Karena itu, port app hanya dibuka di `127.0.0.1`.
- **`env()` hanya boleh di folder `config/`.** Setelah `config:cache`, `env()` di tempat lain
  mengembalikan `null`. PHPStan (Larastan) menangkap ini sebelum sempat jadi bug production.

## 5. Log Midtrans: cukup untuk menyelidiki, tanpa rahasia

Satu baris log per panggilan ke Midtrans: nomor pesanan, kode HTTP, durasi, dan alasan penolakan.
Isi request dan response **sengaja tidak** dicatat, karena berisi server key dan data pribadi
pembeli. Log biasanya dibaca lebih banyak orang dan disimpan lebih lama daripada database.

**Kejadian nyata: webhook diam-diam ditolak.** Setelah server key sandbox di-_generate_ ulang,
semua webhook ditolak dengan `bad_signature`. Pengecekan status tetap jalan dengan kunci lama,
jadi gejalanya tidak terlihat. Awalnya kesimpulannya "webhook berfungsi" hanya karena melihat
kolom `source`. Kolom `outcome` dan `signature_valid` yang menunjukkan kebenarannya.

## 6. SSR (Server-Side Rendering)

Tanpa SSR, server mengirim halaman kosong dan React yang mengisinya di browser. Mesin pencari dan
pratinjau link WhatsApp sering hanya melihat halaman kosong itu.

Dengan SSR, **halaman pertama** setiap kunjungan dikirim sebagai HTML lengkap (judul, nama
produk, harga, teks alt foto). Setelah JavaScript dimuat, React "mengambil alih" HTML itu
(_hydration_) dan navigasi berikutnya berjalan seperti biasa.

- **`app.tsx` dan `ssr.tsx` memakai opsi yang sama** (`inertia-options.tsx`). Kalau tidak, HTML
  dari server berbeda dengan hasil render browser, dan React mengeluh soal _hydration mismatch_.
- **Dev:** container `app` meminta render ke service `vite`. **Production:** container `ssr`
  terpisah berisi Node + satu file bundel, tanpa `node_modules`, user `node`, filesystem
  read-only (258 MB, dibanding image app 969 MB).
- **Test mematikan SSR** (`phpunit.xml`), supaya test tidak bergantung pada server Node.

### Circuit breaker: jangan semua orang menunggu yang sudah pasti gagal

Kalau container `ssr` mati, halaman tetap tampil lewat render browser. Tapi Docker butuh
**±3 detik** untuk menyimpulkan nama `ssr` tidak ada, dan itu terjadi di **setiap halaman**.

Solusinya pola _circuit breaker_ (seperti sekering listrik): setelah satu kegagalan **koneksi**,
SSR dilewati selama 30 detik, lalu dicoba lagi. Hanya satu pengunjung yang menanggung 3 detik
itu. Halaman berikutnya kembali 166–332 ms. Error **render** di satu halaman (bug React) tidak
memutus sekering, karena halaman lain tetap sehat.

## 7. Ringkasan per peran

Halaman Dashboard bawaan starter kit hanya berisi kotak kosong. Sekarang halaman itu menjadi
**"Ringkasan"**, yaitu kotak masuk pekerjaan sesuai peran:

| Peran   | Yang tampil                                                                     |
| ------- | ------------------------------------------------------------------------------- |
| Pembeli | Belum dibayar (tombol Bayar), sedang diproses, dalam pengiriman (dengan resi)   |
| Penjual | Perlu diproses, siap dikirim, ulasan belum dibalas, stok ≤ 5, saldo             |
| Admin   | Toko menunggu, ulasan bintang 1–2 minggu ini, pencairan/refund macet atau gagal |

- Bagian admin dijaga **Gate yang sama** dengan route admin. Pengguna biasa tidak menerima
  datanya sama sekali, bukan hanya tidak melihatnya.
- **Satu aturan, satu tempat (lagi):** "pencairan/refund bermasalah" dipindah dari
  `FinanceController` ke scope `needsAttention()` di model. Halaman Keuangan dan Ringkasan
  dijamin menghitung hal yang sama.
- Hanya menampilkan yang **bisa ditindaklanjuti**: setiap angka membuka halaman tempat
  urusannya dikerjakan. Ulasan rendah dibatasi 7 hari supaya yang sudah dilihat tidak terus
  muncul.

## 8. Audit dependensi otomatis

Peringatan `picomatch` dari Tahap 8 **hilang dengan sendirinya**, karena paket-paket yang
memakainya sudah naik versi. Hasil sekarang: `npm audit` 0, `composer audit` bersih.

Tapi audit sekali hanya memotret hari ini. Workflow `audit.yml` menjalankannya di setiap push,
setiap PR, dan **setiap Senin**, karena celah baru bisa diumumkan untuk paket yang tidak kita
ubah sama sekali. Workflow ini hanya membaca file lock, jadi selesai dalam hitungan detik.

## 9. Panduan deploy yang sudah diuji

`docs/deploy.md` berisi langkah dari VPS kosong sampai rilis, rollback, dan backup. Perintah
backup **dan restore** diuji di stack lokal. Backup yang belum pernah dicoba dikembalikan belum
terbukti berguna. Bagian yang belum bisa diuji (Caddy di VPS) ditandai jelas.

---

## Kesalahan dan temuan

| Masalah                                                         | Penyebab → Perbaikan                                                                                                                        |
| --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Webhook `bad_signature` setelah ganti kunci, dikira "berfungsi" | Server key berubah, kesimpulan diambil dari kolom yang salah → kunci diperbarui, cek `outcome`/`signature_valid`                            |
| PHPStan menolak `env()` di `bootstrap/app.php`                  | `null` setelah `config:cache` → pindah ke `config/trustedproxy.php`                                                                         |
| SSR tidak muncul pada percobaan pertama                         | Vite masih mengompilasi (> batas 3 detik), dan cara mengecek HTML-nya salah → periksa isi nyata (`h1`, form)                                |
| Bundel SSR butuh `node_modules`                                 | Dependensi tidak ikut dibundel → `ssr.noExternal: true`, image `ssr` cukup Node + 1 file                                                    |
| **96 test gagal, suite berjalan 50 menit**                      | `Inertia::disableSsr(callback)` **mengganti** pengecekan config, jadi test mencoba menghubungi `vite:5173` → callback ikut memeriksa config |
| Test circuit breaker tetap menghubungi Vite                     | `withoutVite()` mengabaikan file `hot` → ganti objek Vite dengan subclass kecil                                                             |
| SSR dev rusak: "module is not defined"                          | `noExternal` berlaku juga di dev server → plugin kecil yang hanya aktif saat `build --ssr`                                                  |
| Halaman lambat 3 detik saat `ssr` mati                          | DNS Docker untuk container yang mati → circuit breaker 30 detik                                                                             |
| Test Ringkasan: alamat utama ganda, angka "belum dibayar" salah | Constraint unik alamat utama; pesanan dipindah status tanpa checkout-nya dibayar → helper test diperbaiki                                   |
| Dua payout pending untuk satu toko ditolak database             | Constraint `payouts_one_pending_per_store` bekerja sebagaimana mestinya → test memakai 2 toko                                               |
| Penghapusan file starter kit masuk ke commit perbaikan SSR      | File sudah di-_stage_ saat commit lain → isi benar, pembagian commit kurang rapi (sudah di-push, dibiarkan)                                 |
| `composer audit` timeout                                        | Gangguan jaringan sesaat ke packagist → diulang, berhasil                                                                                   |

**Pelajaran dari 96 test yang gagal:** perubahan kecil (satu callback) di bagian yang dipakai
setiap halaman bisa merusak semuanya. Sejak itu **seluruh suite dijalankan sebelum commit**,
bukan hanya test di file yang diubah.

## Yang belum dikerjakan

| Hal                                              | Kenapa                                                     |
| ------------------------------------------------ | ---------------------------------------------------------- |
| Deploy ke VPS, HTTPS dengan Caddy                | Belum ada server. Panduannya sudah siap                    |
| Kunci Midtrans production, kunci RajaOngkir baru | Dibuat saat deploy, tidak pernah ditempel di chat          |
| Penyedia transfer/refund sungguhan               | Pencairan masih simulasi (ditandai jelas di halaman Saldo) |
| Dependabot untuk npm/Composer                    | Opsional. Audit mingguan dianggap cukup untuk sekarang     |
| Komplain/retur, promo, peran admin terpisah      | Fitur marketplace besar, di luar cakupan project belajar   |
