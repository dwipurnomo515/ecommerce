# Tahap 3: Katalog Produk

**Tujuan:** penjual bisa membuka toko dan menjual produk, admin bisa mengawasi, dan pembeli bisa
menemukan produk.

**Hasil akhir:** marketplace **Zetago** dengan alur lengkap dari toko dibuka sampai produk tampil
ke pembeli. Ada 159 automated test dan semuanya lolos.

```
Penjual buka toko → admin menyetujui → penjual menambah produk + varian + foto → produk tampil
  di beranda, kategori, pencarian, halaman toko, dan halaman detail
Admin: mengelola kategori, menyetujui/menangguhkan toko, memblokir produk (dengan alasan)
```

---

## Peta fitur

| Area        | Halaman                           | File utama                                      |
| ----------- | --------------------------------- | ----------------------------------------------- |
| **Admin**   | Kategori (pohon maksimal 3 level) | `Admin/CategoryController`, `CategoryRequest`   |
|             | Persetujuan toko                  | `Admin/StoreController`, `ChangeStoreStatus`    |
|             | Moderasi produk                   | `Admin/ProductController`, `ModerateProduct`    |
| **Penjual** | Buka toko & profil                | `Seller/StoreController`, `StoreProfileRequest` |
|             | Produk: daftar, buat, edit, arsip | `Seller/ProductController`, `ProductRequest`    |
|             | Foto produk                       | `Seller/ProductImageController`                 |
| **Pembeli** | Beranda, kategori, cari           | `Shop/CatalogController`, `ProductCards`        |
|             | Detail produk                     | `Shop/ProductController`, `variant-picker.ts`   |
|             | Halaman toko                      | `Shop/StoreController`                          |

---

## 1. Desain data

### Produk punya varian, harga ada di varian

```
products          (nama, deskripsi, status, options)
 └─ product_variants  (options {"Warna":"Hitam","Ukuran":"L"}, harga, stok, berat, sku, is_active)
 └─ product_images    (path, sort_order)
```

- **Setiap produk punya minimal satu varian.** Produk "polos" punya satu varian dengan
  `options = {}`. Dengan begitu, kode keranjang dan pesanan nanti cukup mengenal varian saja.
- **Uang disimpan sebagai integer Rupiah**, tidak pernah float (`0.1 + 0.2 ≠ 0.3`).
- **Aturan bisnis juga dijaga database** lewat `CHECK (stock >= 0)` dan
  `CHECK (compare_at_price > price)`. Validasi di form bisa terlewati, tapi constraint database
  tidak.

### Status sebagai enum, transisi sebagai aturan

`StoreStatus` (pending → active ⇄ suspended) dan `ProductStatus` (draft, active, archived,
banned). Kolom `status` **tidak fillable**, jadi hanya bisa diubah oleh kode yang memang
bertugas mengubahnya. Form tidak bisa menyelundupkan `status=banned` atau `is_admin=true`
(_mass assignment_).

### Pelajaran: jsonb mengurutkan ulang key

PostgreSQL menyimpan `{"Warna": .., "Ukuran": ..}` sebagai `{"Ukuran": .., "Warna": ..}`
(diurutkan berdasarkan panjang lalu isi key). Urutan pilihan yang diketik penjual jadi hilang.
Solusinya ada dua:

1. Daftar pilihan disimpan di kolom terpisah `products.options` sebagai **list**, yang urutannya
   pasti.
2. Untuk mencocokkan varian, key diurutkan dulu (`ProductVariant::optionsKey()`), jadi urutan
   tidak berpengaruh.

---

## 2. Siapa boleh apa: otorisasi berlapis

| Lapis                    | Contoh                                                           |
| ------------------------ | ---------------------------------------------------------------- |
| Middleware route         | `can:admin` untuk semua route `/admin`                           |
| Policy                   | `ProductPolicy::update`: pemilik, dan produk tidak diblokir      |
| Form Request `authorize` | `ProductRequest`: buat → `create`, edit → `update`               |
| Scope binding            | `/products/1/images/99` → foto 99 **harus** milik produk 1       |
| Query scope              | `visibleToBuyers()`: satu-satunya definisi "boleh dilihat"       |
| Database                 | `unique(store_id, slug)`, `unique(user_id)` = satu toko per user |

### IDOR (_Insecure Direct Object Reference_)

Penjual A mengganti angka di URL menjadi ID produk penjual B. Ini dicegah dengan cara berikut:

- Policy membandingkan `store_id` produk dengan toko milik user yang login.
- `store_id` tidak pernah diambil dari form. Produk dibuat lewat `$store->products()->create()`.
- `scopeBindings()` untuk route bersarang.

Setiap aturan punya test "penyusup", misalnya `test_sellers_cannot_edit_another_stores_product`.

### 404, bukan 403, untuk hal yang tersembunyi

Pembeli yang membuka produk draf atau diblokir, atau toko yang belum disetujui, mendapat
**404, sama seperti URL yang memang tidak ada**. Jawaban 403 berarti "ada, tapi kamu tidak boleh
melihatnya", dan itu membocorkan informasi.

---

## 3. Race condition dan transaction

### Dua admin klik bersamaan

`ChangeStoreStatus` dan `ModerateProduct` membaca ulang baris **dengan kunci**
(`lockForUpdate()` di dalam `DB::transaction`). Admin kedua menunggu, lalu melihat status
terbaru, alih-alih bertindak berdasarkan data lama.

### TOCTOU: _time-of-check vs time-of-use_

```
Penjual: cek policy ✓ ─────────────────────── simpan status=active   ← menimpa blokir!
Admin:                  blokir ✓
```

Policy dicek di **awal** request, sedangkan penyimpanan terjadi sedikit **kemudian**.
`UpdateProduct` mengecek ulang status di baris yang dikunci, tepat sebelum menyimpan.

> 🐛 **Bug yang ditangkap test:** pengecekan ulang itu awalnya membandingkan
> `->value('status') === 'banned'`. Di Eloquent, `value()` mengembalikan atribut yang sudah
> di-_cast_, yaitu **enum**, bukan string. Perbandingannya selalu bernilai salah, sehingga
> pengamannya diam-diam tidak bekerja. PHPStan tidak menangkapnya (tipe `mixed`). Test yang
> mensimulasikan race-lah yang menangkapnya.

### Semua atau tidak sama sekali

`CreateProduct` dan `UpdateProduct` berjalan di dalam `DB::transaction`. Kalau varian ke-3 gagal
disimpan, produk dan varian 1–2 ikut dibatalkan. Test-nya sengaja membuat database menolak
(stok negatif) lalu memastikan tidak ada yang tersisa.

---

## 4. Edit varian: pola _upsert_

Varian dikenali dari **kombinasi pilihannya**, bukan dihapus lalu dibuat ulang:

| Kondisi                  | Tindakan                                            |
| ------------------------ | --------------------------------------------------- |
| Ada di form dan database | Update baris yang sama (**ID tetap**)               |
| Hanya ada di form        | Insert, atau aktifkan lagi varian yang dulu dihapus |
| Hanya ada di database    | `is_active = false`, barisnya tetap disimpan        |

**Kenapa:** mulai Tahap 4, keranjang dan pesanan menyimpan **ID varian**. Delete-lalu-insert
akan merusaknya setiap kali penjual mengedit harga.

Dua jebakan yang ditemukan:

1. **Constraint unik dicek per baris.** Coba tukar SKU A dan B: saat A di-update menjadi "B",
   baris B masih memegang "B", sehingga error. Solusinya: kosongkan dulu semua SKU yang akan
   dipakai, baru isi ulang.
2. **_Dirty checking_ Eloquent.** Eloquent hanya menyimpan kolom yang berubah dibanding saat
   data **dimuat**. Kalau varian dimuat _sebelum_ SKU dikosongkan, Eloquent mengira "KAOS-HM →
   KAOS-HM" tidak berubah, sehingga SKU-nya hilang. Karena itu urutan kodenya penting, dan ada
   komentar yang menjelaskannya.

---

## 5. File upload yang aman

| Ancaman                              | Pertahanan                                                   |
| ------------------------------------ | ------------------------------------------------------------ |
| File berbahaya berekstensi `.jpg`    | `File::image()` membaca _magic bytes_ (isi file), bukan nama |
| SVG berisi `<script>` (XSS)          | Hanya JPG/PNG/WebP yang diterima                             |
| Nama `../../.env` (_path traversal_) | Nama asli tidak pernah dipakai, `store()` membuat nama acak  |
| File raksasa / foto 20.000 px        | Maksimal 5 MB, dimensi 400–6000 px                           |
| Foto produk toko lain                | `scopeBindings()` + policy                                   |

- **Disk yang bisa diganti.** `MEDIA_DISK=public` saat dev, nanti `s3` di production, tanpa
  mengubah kode. `Storage::fake()` di test memanfaatkan hal yang sama.
- **Urutan hapus: baris database dulu, file kemudian.** Sisa file tidak berbahaya. Baris yang
  menunjuk file yang hilang akan menampilkan gambar rusak.
- **⏳ Belum:** metadata EXIF (termasuk **lokasi GPS** dari kamera HP) masih ikut tersimpan.
  Ini dijadwalkan di Tahap 8 (encode ulang + thumbnail).

---

## 6. Etalase pembeli

### Satu definisi visibilitas

```php
#[Scope]
protected function visibleToBuyers(Builder $query): void
{
    $query->where('status', ProductStatus::Active)            // dipublikasi & tidak diblokir
        ->whereHas('store', fn ($s) => $s->where('status', StoreStatus::Active))
        ->whereHas('activeVariants');                          // ada yang bisa dibeli
}
```

Beranda, kategori, pencarian, halaman toko, halaman detail, jumlah produk toko, dan tombol
"Lihat di toko" milik penjual semuanya memakai scope ini. Kalau aturannya berubah, cukup diubah di
satu tempat.

### `ProductCards`: satu sumber untuk semua grid

Isinya query, _eager load_, urutan, dan bentuk data kartu. Tiga hal penting:

- **N+1 query.** 24 produk × (foto + varian + toko) bisa menjadi 73 query. `with([...])`
  mengambil semuanya dalam beberapa query saja. Test-nya **menghitung query** untuk 1 dan 9
  produk, dan jumlahnya harus sama. `Model::shouldBeStrict()` juga melempar error setiap kali
  ada _lazy loading_.
- **Tie-breaker.** Urutan berdasarkan harga ditambah `orderByDesc('id')`. Tanpa kunci terakhir
  yang unik, produk berharga sama bisa muncul di dua halaman pagination.
- **Harga kartu = varian termurah yang masih ada stoknya**, sama dengan angka yang dipakai untuk
  urutan "Harga terendah".

### Input URL itu input user

| Input                 | Penanganan                                                  |
| --------------------- | ----------------------------------------------------------- |
| `?sort=DROP TABLE`    | _Whitelist_ (`ProductCards::SORTS`), selain itu → "terbaru" |
| `?q=100%`             | `Like::escape()`: `%` dan `_` dicari sebagai teks           |
| `?status=xyz` (admin) | `Enum::tryFrom()` → kembali ke default                      |

### Pemilih varian (`variant-picker.ts`)

Ditulis sebagai fungsi murni tanpa React, dan diuji dengan Node:

- Saat halaman dibuka, **varian pertama yang masih ada stoknya** langsung terpilih, supaya harga
  yang tampil langsung nyata.
- Varian habis tampil dicoret. Kombinasi yang tidak dijual tampil dengan border putus-putus.
- Memilih kombinasi yang tidak ada membuat pilihan lain **pindah** ke varian yang ada, jadi
  pembeli tidak menemui jalan buntu.

### Data yang dikirim ke browser = data publik

Props Inertia bisa dilihat siapa saja lewat _View Source_. Halaman pembeli **tidak** mengirim
SKU, berat, atau ID internal yang tidak perlu. Ada test yang memastikan hal ini
(`test_private_variant_fields_are_not_sent`).

---

## 7. Cara kita membuktikan test itu berguna: _mutation check_

Setelah test hijau, kodenya **sengaja dirusak** satu per satu. Kalau test tetap hijau, berarti
test-nya tidak benar-benar menjaga apa pun.

| Kode yang dirusak                    | Test yang (harus) gagal                     |
| ------------------------------------ | ------------------------------------------- |
| Cek loop di pohon kategori dihapus   | pesan error loop yang persis                |
| `scopeBindings()` dihapus            | foto milik produk lain → harus 404          |
| `releaseSkus()` dihapus              | tukar SKU, SKU varian yang dihapus          |
| Varian dimuat sebelum SKU dilepas    | SKU yang tidak berubah tetap ada            |
| `ksort` di `optionsKey` dihapus      | urutan pilihan tidak memengaruhi pencocokan |
| `Like::escape` dimatikan             | pencarian "100%"                            |
| Syarat "toko aktif" di scope dihapus | toko pending/ditangguhkan → 404             |
| Eager load foto dihapus              | hitung query (N+1)                          |

Pelajaran yang muncul berulang kali: **beberapa bug baru ketahuan karena hal ini.** Cek loop
kategori ternyata tidak teruji karena tertutup error lain, dan pengaman TOCTOU sempat tidak
berfungsi sama sekali.

---

## 8. Frontend

- **`<Form>` untuk form statis, `useForm` untuk form dinamis.** Form produk membuat baris varian
  dari daftar pilihan, jadi payload-nya disusun di kode (`transform`).
- **Satu komponen untuk buat dan edit** (`ProductForm`). Perbedaannya hanya dari ada tidaknya
  prop `product`.
- **Draf input ≠ data server.** `null` dari server menjadi `''` di input, lalu kembali menjadi
  `null` saat dikirim. Konversi ini hanya ada di `toDrafts()` dan `transform`.
- **Cek di browser hanya untuk kenyamanan.** Batas foto dan ukuran file dicek di klien supaya
  user tidak menunggu upload yang pasti gagal. Server tetap memeriksa semuanya.
- **Mobile sungguhan, bukan versi kecil desktop:**
    - Galeri bisa digeser dengan _scroll-snap_ (tanpa library).
    - Pencarian mendapat baris sendiri.
    - Kategori bisa digeser ke samping.
    - Tombol beli menempel di bawah layar.
- **Jujur pada pembeli.** Tidak ada rating palsu. Data contoh diberi label. Tombol keranjang
  tampil, tetapi nonaktif dengan keterangan "sedang disiapkan".

---

## Kesalahan yang terjadi (dan pelajarannya)

| Masalah                                                | Penyebab → Perbaikan                                               |
| ------------------------------------------------------ | ------------------------------------------------------------------ |
| `freezeTime` vs tanggal di database berbeda mikrodetik | Postgres menyimpan detik → `freezeSecond()`                        |
| PUT profil toko oleh user tanpa toko → error 500       | Mode buat/edit ditebak dari data → ditentukan dari **method** HTTP |
| Bug urutan label di `syncVariants`                     | Ketahuan lewat uji Node sebelum UI dibuat                          |
| `File::image()->dimensions(fn …)`                      | Butuh objek `Rule::dimensions()`, bukan closure                    |
| `assertSessionHasErrors` tidak punya argumen pesan     | Asersi pesan ditulis manual                                        |
| Label "L / Hitam" di peringatan hapus varian           | Memakai urutan key jsonb → disusun ulang sesuai `options`          |
| Pengaman TOCTOU tidak berfungsi                        | `value()` mengembalikan enum (lihat bagian 3)                      |
| Tinker macet saat menguji backfill                     | Tinker menunggu input → dijalankan sebagai skrip PHP biasa         |
| PHPStan: `Collection->all()` bukan `list`              | Ditulis ulang dengan loop biasa, sekaligus lebih mudah dibaca      |

---

## Yang sengaja ditunda

| Hal                                            | Tahap |
| ---------------------------------------------- | ----- |
| Keranjang, checkout per toko, ongkir           | 4     |
| Hapus EXIF/GPS + thumbnail foto                | 8     |
| Riwayat moderasi (_audit log_)                 | 8     |
| Pencarian yang toleran salah ketik (`pg_trgm`) | 8     |
| Upload logo toko                               | nanti |
