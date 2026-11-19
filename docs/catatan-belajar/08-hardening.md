# Tahap 8: Hardening Keamanan

**Tujuan:** menutup celah yang sengaja ditunda di tahap-tahap sebelumnya, menambah lapisan
pertahanan di browser dan server, dan memastikan kesalahan di masa depan (route baru yang lupa
dilindungi) **tertangkap otomatis**.

**Hasil akhir:** 349 automated test, semuanya lolos.

---

## Peta perubahan

| Bagian                   | File utama                                                           |
| ------------------------ | -------------------------------------------------------------------- |
| Foto bebas EXIF/GPS      | `ImageProcessor`, `ProductImage::storeProcessed`, `images:reprocess` |
| Header keamanan & CSP    | `SecurityHeaders`, `config/security.php`, nonce di `app.blade.php`   |
| Audit log                | `AuditLog` (append-only), halaman Admin → Audit                      |
| Keuangan admin           | `FinanceController`: komisi, escrow, payout/refund macet             |
| Pencarian salah ketik    | `pg_trgm` + indeks GIN, `CatalogController::matching()`              |
| Moderasi ulasan          | `ModerateReview`, Admin → Ulasan                                     |
| Pagar route & rate limit | `RouteSecurityTest`, `ThrottleWrites`, `RequireAdminSecondFactor`    |

---

## 1. Foto: encode ulang, jangan "hapus tag"

Foto HP menyimpan **koordinat GPS**, dan untuk penjual rumahan itu sering berarti alamat rumahnya.

- Foto **di-encode ulang** ke WebP. Hanya piksel yang disalin, jadi EXIF, GPS, nomor seri kamera,
  dan _payload_ tersembunyi ikut hilang. Ini lebih aman daripada mencoba membuang tag satu per
  satu.
- **Rotasi EXIF diterapkan ke piksel dulu.** Tanpa langkah ini, foto potret dari HP tampil miring
  setelah metadatanya dibuang.
- **File asli tidak pernah disimpan**, karena diproses langsung dari file sementara upload. Itu
  sebabnya prosesnya sinkron, bukan lewat antrean.
- Test memakai JPEG yang dibuat **byte demi byte** dengan segmen EXIF sungguhan.

## 2. Header keamanan dan CSP

| Header                                      | Melindungi dari                         |
| ------------------------------------------- | --------------------------------------- |
| `Content-Security-Policy` (+ nonce)         | Script penyerang (XSS)                  |
| `frame-ancestors 'none'`, `X-Frame-Options` | _Clickjacking_                          |
| `X-Content-Type-Options: nosniff`           | Browser "menebak" file sebagai script   |
| `Referrer-Policy`, `Permissions-Policy`     | Kebocoran URL, fitur yang tidak dipakai |
| HSTS (production)                           | Koneksi diturunkan ke HTTP              |

- **Nonce:** angka acak per response. Script inline tanpa nonce yang cocok ditolak browser.
- **Kompromi sadar:** `style-src` mengizinkan `'unsafe-inline'` (komponen UI memakai
  `style=""`), sedangkan `script-src` tidak pernah.
- **Mode report-only** (`CSP_REPORT_ONLY`) untuk mencoba kebijakan baru tanpa merusak situs.
- Cookie `appearance` yang dicetak ke script inline sekarang di-_whitelist_. Sebelumnya aman
  hanya karena kebetulan.

## 3. Audit log

- _Append-only_, dan ditulis **di dalam transaction** aksinya. Aksi yang batal tidak meninggalkan
  catatan.
- Mencatat: moderasi produk dan ulasan, persetujuan toko, pembatalan pesanan, **perubahan
  rekening** (disamarkan), penarikan, percobaan ulang, dan hak admin.
- Data sensitif tidak disimpan utuh, supaya log sendiri tidak menjadi sumber kebocoran.

## 4. Pencarian toleran salah ketik

- `pg_trgm` memecah teks menjadi potongan 3 huruf dan membandingkan berapa banyak yang sama.
- Ambang batas **0,4 dipilih dari pengukuran**: "kaoss polso" mendapat 0,54, sedangkan produk
  yang tidak terkait sekitar 0,08. Ambang bawaan PostgreSQL (0,6) akan membuang salah ketik nyata.
- **Indeks GIN** melayani `ILIKE '%…%'` dan operator kemiripan `<%`.

## 5. Moderasi ulasan

Menyembunyikan ulasan **mengurangi total rating** produk dan toko secara atomik, dan menampilkannya
kembali memulihkan angkanya **persis**. Ditambah `CHECK (rating_count >= 0 AND rating_sum >= 0)`.

---

## 6. Tinjauan keamanan: temuan dan perbaikannya

| #   | Temuan                                                                   | Risiko | Tindakan                                            |
| --- | ------------------------------------------------------------------------ | ------ | --------------------------------------------------- |
| 1   | `forgot-password` tanpa batas, padahal setiap request **mengirim email** | Tinggi | 5 per 15 menit per IP                               |
| 2   | `register` tanpa batas: pembuatan akun palsu massal                      | Sedang | 10 per jam per IP                                   |
| 3   | `reset-password` tanpa batas: menebak token                              | Sedang | 10 per 15 menit per IP                              |
| 4   | Sebagian besar route tulis tanpa batas umum                              | Sedang | `ThrottleWrites`: 60 per menit per pengguna         |
| 5   | Admin cukup dengan password                                              | Tinggi | Wajib 2FA atau passkey                              |
| 6   | Route `storage/{path}` (GET/PUT) aktif padahal tidak dipakai             | Rendah | Dimatikan (`serve => false`)                        |
| 7   | Tidak ada jaminan route baru akan dilindungi                             | Sedang | `RouteSecurityTest` membaca tabel route             |
| 8   | `npm audit`: 8 "high"                                                    | Rendah | Diterima, lihat di bawah                            |
| –   | `composer audit`                                                         | –      | Bersih                                              |
| –   | Route publik (7 buah)                                                    | –      | Semua disengaja (etalase + webhook bertanda tangan) |

**Tentang `npm audit` (#8).** Kedelapan temuan berasal dari **satu** celah ReDoS di `picomatch`,
yang hanya dipakai alat build (Vite). Library ini tidak dikirim ke browser dan tidak berjalan di
server production. **Belum ada versi yang diperbaiki** (4.0.7 masih rentan), dan
`npm audit fix --force` justru akan menurunkan plugin ke versi mayor lama. Keputusan: diterima,
dicatat, dan diperiksa lagi di Tahap 9.

**Pagar otomatis (#7).** Test membaca tabel route dan memastikan:

- setiap route aplikasi butuh login, kecuali yang ada di **daftar publik eksplisit**;
- setiap route `admin/*` punya `can:admin` **dan** syarat faktor kedua;
- setiap route `seller/*` butuh email terverifikasi.

Mutation check: menambahkan route baru tanpa login langsung menggagalkan test **beserta nama
route-nya**.

---

## Kesalahan dan temuan

| Masalah                                                     | Penyebab → Perbaikan                                                                     |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Middleware CSP pertama menyebabkan **500** di seluruh situs | _Closure_ `string ...$x` menerima `null` → `?string`; ketahuan dari server dev sungguhan |
| nginx image Docker ikut mengirim `X-Frame-Options` dan HSTS | Header ganda; aman (CSP yang berlaku), dirapikan di Tahap 9                              |
| IP audit kosong di test                                     | `runningInConsole()` bernilai benar di PHPUnit → `request()->ip()`                       |
| jsonb mengurutkan ulang key (lagi)                          | `assertSame` → `assertEquals` untuk detail audit                                         |
| Strict mode: `store_id` tidak dimuat                        | Eager load `product:id,name,store_id`                                                    |
| Test route membandingkan nama kelas                         | `gatherRouteMiddleware()` mengembalikan **alias** (`auth`, `can:admin`)                  |
| `composer test` "gagal" setelah 300 detik                   | Batas waktu Composer → `disableProcessTimeout` (suite butuh ±320 dtk)                    |
| Output pemeriksaan latar belakang tidak lengkap             | Tidak dianggap lolos sebelum ringkasan lengkap terlihat                                  |

## Yang sengaja ditunda ke Tahap 9

| Hal                                                           |
| ------------------------------------------------------------- |
| Header ganda dari nginx image production                      |
| Cek ulang `npm audit` (picomatch)                             |
| Penyedia transfer/refund sungguhan, kunci Midtrans production |
| SSR untuk SEO halaman produk                                  |
