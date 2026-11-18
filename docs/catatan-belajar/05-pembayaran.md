# Tahap 5: Pembayaran (Midtrans)

**Tujuan:** pembeli membayar pesanan lewat Midtrans, dan status pesanan berubah menjadi **Lunas**
hanya kalau pembayarannya **terbukti** datang dari Midtrans.

**Hasil akhir:** alur lengkap teruji di Midtrans **Sandbox** sungguhan. Ada 260 automated test dan
semuanya lolos.

```
Pesanan dibuat → "Bayar sekarang" → pop-up Snap (VA, QRIS, ...) → pembeli membayar
  → Midtrans memberi tahu server kita (webhook, ditandatangani)   ┐
  → atau server kita bertanya ke Midtrans (rekonsiliasi)          ┘→ Lunas, pesanan "Dibayar"
```

---

## Peta fitur

| Bagian            | File utama                                                    |
| ----------------- | ------------------------------------------------------------- |
| Kontrak gateway   | `PaymentGateway`, `PaymentNotification`, `PaymentUnavailable` |
| Implementasi      | `Midtrans` (Snap, verifikasi tanda tangan, status API)        |
| Mulai bayar       | `StartPayment`, `pay-button.tsx`                              |
| Webhook           | `MidtransWebhookController`, `HandlePaymentNotification`      |
| Rekonsiliasi      | `ReconcilePayment`, `payments:sync`                           |
| Jejak audit       | tabel `payment_notifications`                                 |
| Data contoh lokal | `DemoSeeder`, `FlatRateShipping`                              |

---

## 1. Kunci rahasia vs kunci publik

| Kunci          | Siapa yang memakai         | Boleh sampai ke browser?       |
| -------------- | -------------------------- | ------------------------------ |
| **Server Key** | Server kita → API Midtrans | ❌ Tidak pernah (ada test-nya) |
| **Client Key** | `snap.js` di browser       | ✅ Memang dibuat untuk itu     |

Kunci disimpan di `.env` (tidak ikut ke Git). Sebelum setiap commit di tahap ini, isi perubahan
dicek supaya server key tidak ikut tersimpan.

## 2. Membuat pembayaran (Snap)

- `order_id` = kode checkout kita (`ZTG-…`). Midtrans menerima satu `order_id` **hanya sekali**,
  jadi token Snap dibuat pada klik pertama, disimpan, lalu dipakai ulang.
- **Atomic lock di Redis** (`Cache::lock`) untuk dua tab yang mengklik bersamaan. Ini sengaja
  **bukan** kunci baris database, karena kuncinya akan tertahan selama menunggu Midtrans dan ikut
  menahan job kedaluwarsa.
- **Cek sendiri sebelum mengirim:** jumlah `item_details` harus sama persis dengan `gross_amount`.
  Kalau berbeda, itu bug di kode kita, jadi kita berhenti sebelum menagih jumlah yang salah.
- Batas waktu pop-up Midtrans = sisa waktu tahan stok kita.
- Token sampai ke halaman lewat **Inertia flash** (`Inertia::flash('payment', …)`), jadi tidak
  perlu endpoint JSON atau penanganan CSRF manual.

## 3. Webhook: siapa pun bisa mengirim, hanya Midtrans yang bisa menandatangani

```
signature_key = SHA512(order_id + status_code + gross_amount + ServerKey)
```

- Route webhook **tanpa login dan dikecualikan dari CSRF**. Perannya digantikan oleh tanda tangan.
- `gross_amount` ikut di-hash, jadi mengubah nominal juga merusak tanda tangan.
- Pakai **`hash_equals`**, bukan `===`, untuk mencegah _timing attack_.
- **Tanda tangan sah ≠ nominal benar.** Nominal tetap dicocokkan dengan tagihan sebelum pesanan
  ditandai lunas.
- Status Midtrans diterjemahkan **di dalam gateway** menjadi `paid` / `failed` / `waiting` /
  `other`. Logika toko tidak pernah mengenal kata `settlement` atau `capture`.

## 4. Notifikasi datang dua kali, terlambat, atau tidak berurutan

Setiap keputusan diambil dari **status checkout saat ini**, di bawah kunci baris yang sama dengan
job kedaluwarsa:

| Notifikasi ↓ / Checkout → | Menunggu               | Lunas              | Dibatalkan         |
| ------------------------- | ---------------------- | ------------------ | ------------------ |
| **berhasil**              | → Lunas ✅             | abaikan (duplikat) | → **Perlu refund** |
| **gagal**                 | → batal + stok kembali | abaikan (basi)     | abaikan            |
| **pending**               | abaikan                | abaikan            | abaikan            |

Kalau uang datang **setelah** pesanan dibatalkan, stoknya mungkin sudah terjual ke orang lain.
Pesanan tidak dikirim diam-diam. Statusnya menjadi "Perlu refund" dan dicatat sebagai error untuk
ditangani manusia.

Kode HTTP untuk Midtrans: tanda tangan palsu dijawab 403, selebihnya 200. Mengulang tidak akan
mengubah jawabannya.

## 5. Webhook bisa hilang: rekonsiliasi

> 🧪 **Kejadian nyata di tahap ini:** pembayaran sandbox berhasil, tetapi tunnel sudah tertutup,
> sehingga webhook tidak pernah sampai. Pesanan menggantung di "Menunggu pembayaran".

Solusinya: **kita yang bertanya** ke Midtrans (`GET /v2/{order_id}/status`). Jawabannya
diterapkan lewat `applyVerified()`, jalur yang **sama persis** dengan webhook. Tidak perlu
tanda tangan, karena kita sendiri yang membuat request HTTPS dengan server key kita.

Dipanggil di tiga tempat:

1. **Halaman pesanan** yang masih menunggu, maksimal sekali per 30 detik per checkout
   (`Cache::add` yang atomik, sehingga F5 berulang tidak membanjiri API).
2. **`payments:sync`** setiap 10 menit.
3. **Tepat sebelum `orders:expire` membatalkan**, supaya pesanan yang sebenarnya sudah dibayar
   tidak terlanjur dibatalkan (dan berujung refund).

Hanya checkout yang **menunggu dan punya token Snap** yang ditanyakan. Tanpa token, pembeli belum
pernah membuka pembayaran.

Pesanan yang menggantung tadi menjadi uji nyata pertamanya: `payments:sync` menandainya **Lunas**
tanpa webhook sama sekali.

## 6. Menguji integrasi pihak ketiga

| Cara                                             | Untuk                                                  |
| ------------------------------------------------ | ------------------------------------------------------ |
| `Http::fake()` + payload dari dokumentasi        | Semua test otomatis, tanpa internet                    |
| `Http::preventStrayRequests()`                   | Memastikan tidak ada test yang memanggil API sungguhan |
| Satu panggilan ke **sandbox** dengan data contoh | Membuktikan format request kita diterima Midtrans      |
| Tunnel (ngrok / Cloudflare) + simulator          | Uji ujung ke ujung, termasuk webhook sungguhan         |
| `DemoSeeder` + "Kurir Contoh"                    | Mengklik seluruh alur di lokal tanpa key RajaOngkir    |

---

## Kesalahan dan temuan (dan pelajarannya)

| Masalah                                            | Penyebab → Perbaikan                                                                                                    |
| -------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `Http::fake()` kedua tidak berpengaruh             | Ditambahkan di belakang, aturan pertama menang → respons tiruan berupa _closure_                                        |
| Helper test `status()` → _fatal error_             | Bentrok dengan method `final` PHPUnit → `paymentStatus()`                                                               |
| `<head.icon>` di JSX                               | Huruf kecil dianggap tag HTML → `const HeadIcon = head.icon`                                                            |
| "Test notification URL" di dashboard gagal         | Notifikasi uji tidak ditandatangani dengan server key. Dibuktikan lewat status API: server key milik merchant yang sama |
| Pesanan lunas di Midtrans, menggantung di aplikasi | Tunnel tertutup, webhook hilang → rekonsiliasi                                                                          |
| Database dev kosong, tidak bisa membuat pesanan    | Butuh data + ongkir → `DemoSeeder` + "Kurir Contoh" (hanya lokal)                                                       |

## Yang sengaja ditunda

| Hal                                                 | Tahap |
| --------------------------------------------------- | ----- |
| Penjual memproses pesanan, resi, pesanan selesai    | 6     |
| Proses refund (status "Perlu refund" baru ditandai) | 7     |
| Saldo penjual dan komisi marketplace                | 7     |
| Kunci Midtrans **production** + verifikasi merchant | 9     |
