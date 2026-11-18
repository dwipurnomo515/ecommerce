# Tahap 6: Pesanan untuk Penjual & Ulasan

**Tujuan:** pesanan yang sudah dibayar sampai ke tangan pembeli. Penjual memproses dan mengirim,
pembeli mengonfirmasi, lalu pembeli memberi ulasan yang membangun reputasi produk dan toko.

**Hasil akhir:** siklus belanja lengkap. Ada 281 automated test dan semuanya lolos.

```
Dibayar ─Proses─▶ Dikemas ─resi─▶ Dikirim ─"Pesanan diterima" / 7 hari─▶ Selesai ─▶ Ulasan ─▶ Balasan penjual
```

---

## Peta fitur

| Bagian                  | File utama                                                     |
| ----------------------- | -------------------------------------------------------------- |
| Alur status pesanan     | `OrderStatus::canTransitionTo()`, `FulfilOrder`, `OrderPolicy` |
| Pesanan masuk (penjual) | `Seller/OrderController`, `seller/orders/index.tsx`            |
| Konfirmasi & otomatis   | `Shop/OrderController::confirm`, `orders:auto-complete`        |
| Ulasan pembeli          | `WriteReview`, `ReviewController`, `review-dialog.tsx`         |
| Balasan & rating toko   | `ReplyToReview`, `ReviewPolicy`, `HasRatingTotals`             |

---

## 1. Mesin status (_state machine_)

`OrderStatus::canTransitionTo()` adalah **satu-satunya** aturan perpindahan status: tidak boleh
melompati langkah dan tidak boleh mundur. Pola ini sama dengan status toko di Tahap 3.

| Pertanyaan                           | Dijawab oleh                                |
| ------------------------------------ | ------------------------------------------- |
| **Siapa** yang boleh?                | Policy (toko penjual / pembeli)             |
| **Apakah** langkah ini sah sekarang? | `canTransitionTo()` pada baris yang dikunci |

Contoh: pembeli menekan "Pesanan diterima" tepat saat job otomatis berjalan. Baris pesanan
dikunci, jadi yang kedua melihat status "Selesai", lalu **diam saja** tanpa error.

**Satu data, dua sudut pandang.** Status `paid` bagi penjual berarti "Perlu diproses" (tugasnya),
dan bagi pembeli berarti "Dibayar, menunggu diproses". Karena itu ada `label()` dan
`sellerLabel()`.

**Jangan bergantung pada kebiasaan pengguna.** Banyak pembeli lupa menekan "diterima", jadi
pesanan otomatis selesai 7 hari setelah dikirim.

## 2. Ulasan: hanya pembeli sungguhan, satu suara per pembelian

- Ulasan menempel pada **baris barang yang dibeli** (`order_item_id` unik). Tidak mungkin
  mengulas tanpa membeli, dan tidak mungkin mengulas dua kali.
- Syaratnya: pesanan milik sendiri, status Selesai, paling lambat 60 hari, dan belum diulas.
  Semua syarat ada di **`WriteReview::refusal()`**, yang dipakai untuk **menampilkan tombol** dan
  untuk **menolak request**. Keduanya tidak mungkin berbeda.
- **Privasi:** nama tampil sebagai "Budi S.", dan `user_id` tidak dikirim ke halaman publik.
- **Tanpa rating palsu:** produk atau toko tanpa ulasan tidak menampilkan bintang sama sekali,
  bukan "★ 0".

## 3. Denormalisasi + UPDATE atomik

Rata-rata rating disimpan sebagai `rating_sum` dan `rating_count`, baik di produk **maupun di
toko**. Grid produk dan halaman toko cukup membaca dua kolom.

```php
Product::query()->whereKey($id)->incrementEach(['rating_count' => 1, 'rating_sum' => $rating]);
// SQL: SET rating_count = rating_count + 1, rating_sum = rating_sum + 4
```

**Kenapa tidak baca–hitung–tulis?** Dua ulasan yang datang bersamaan bisa sama-sama membaca
"10", sama-sama menulis "11", dan satu ulasan **hilang dari total** (_lost update_). UPDATE
atomik dihitung oleh database dalam satu langkah.

Konsekuensi denormalisasi: total **wajib** diperbarui di tempat yang sama dengan pembuatan ulasan.
Satu `WriteReview` memperbarui ulasan, total produk, dan total toko dalam satu transaction. Kolom
baru di toko diisi dari ulasan lama lewat migration (_backfill_).

## 4. Balasan penjual: sekali, tidak bisa diubah

Balasan dikunci setelah dikirim. Pembeli yang membaca halaman produk melihat apa yang benar-benar
ditulis penjual, bukan versi yang diam-diam diganti setelah ada perdebatan. Cek "sudah dibalas"
dilakukan pada baris yang dikunci, dan mutation check membuktikan test-nya menangkap kalau
pengecekan ini hilang.

---

## Kesalahan dan temuan

| Masalah                                                      | Penyebab → Perbaikan                                                   |
| ------------------------------------------------------------ | ---------------------------------------------------------------------- |
| Pembeli melihat "Perlu diproses"                             | Satu label untuk dua peran → `label()` + `sellerLabel()`               |
| PHPStan: closure "mengembalikan" `CarbonImmutable`           | `fn ($o) => $o->x = now()` mengembalikan nilai → closure `void`        |
| `--filter=ReviewTest` ikut menjalankan `CheckoutPreviewTest` | Filter mencocokkan bagian nama ("P**reviewTest**") → jalankan per file |
| Mutation "harus Selesai" tidak menggagalkan semua test       | Bukan bug: pengecekan batas waktu ikut menolak (lapis kedua)           |

## Yang sengaja ditunda

| Hal                                                                 | Tahap |
| ------------------------------------------------------------------- | ----- |
| Pembatalan/refund setelah dibayar, sengketa ("barang tidak sampai") | 7     |
| Saldo penjual cair setelah pesanan Selesai, komisi marketplace      | 7     |
| Moderasi ulasan oleh admin (spam, kata kasar)                       | 8     |
| Lacak resi otomatis lewat API kurir                                 | nanti |
