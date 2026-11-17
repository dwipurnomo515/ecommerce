# Tahap 4: Keranjang, Alamat, Ongkir, dan Pesanan

**Tujuan:** pembeli bisa memasukkan barang ke keranjang, memilih alamat dan kurir, lalu membuat
pesanan, **tanpa** bisa mengakali harga dan **tanpa** stok terjual dua kali.

**Hasil akhir:** alur belanja lengkap sampai "Menunggu pembayaran". Ada 232 automated test dan
semuanya lolos. Pembayaran sungguhan (Midtrans) dikerjakan di Tahap 5.

```
Produk → Tambah ke keranjang → Keranjang (per toko) → Checkout
  (alamat + kurir per toko, ongkir dari RajaOngkir) → Buat pesanan
  → stok diambil, harga dibekukan → bayar dalam 24 jam, atau dibatalkan otomatis
```

---

## Peta fitur

| Bagian           | File utama                                                        |
| ---------------- | ----------------------------------------------------------------- |
| Keranjang        | `AddToCart`, `ChangeCartQuantity`, `CartView`, `shop/cart.tsx`    |
| Wilayah & ongkir | `ShippingProvider`, `RajaOngkir`, `Destination`, `ShippingOption` |
| Alamat pembeli   | `AddressBook`, `AddressRequest`, `destination-picker.tsx`         |
| Asal kirim toko  | `Store::setShippingOrigin()`, `ReadsDestinationToken`             |
| Checkout         | `CheckoutPreview`, `PlaceOrder`, `shop/checkout.tsx`              |
| Kedaluwarsa      | `ExpireCheckout`, `orders:expire`, service `scheduler`            |
| Pesanan pembeli  | `Shop/OrderController`, `shop/orders.tsx`                         |

---

## 1. Keranjang

- **Disimpan per user di database** (pilihan A: pembeli wajib login). Keranjangnya sama di HP
  dan laptop.
- **Keranjang tidak menahan stok.** Lima orang boleh menaruh barang terakhir di keranjang. Stok
  baru diambil saat pesanan dibuat. Keranjang hanya mencegah hal yang mustahil, misalnya menambah
  10 dari stok 3, dan yang dicek adalah **total** isi keranjang, bukan hanya jumlah yang baru
  ditambah.
- **Harga tidak disimpan untuk menagih.** Keranjang selalu memakai harga hari ini. `seen_price`
  hanya dipakai untuk memberi tahu "harga naik dari Rp …".
- **`CartView` adalah satu-satunya sumber kebenaran** untuk status barang (ok, stok kurang,
  habis, tidak dijual). Checkout memakai aturan yang sama.

## 2. Pihak ketiga: RajaOngkir (Komerce)

- **Di belakang interface `ShippingProvider`.** Kode lain tidak tahu RajaOngkir ada, jadi
  penyedianya bisa diganti dengan satu baris di `AppServiceProvider`.
- **Cache:** wilayah selama 1 hari, ongkir selama 1 jam. **Kegagalan tidak pernah di-cache.**
- **Batas waktu** 3 detik untuk koneksi dan 8 detik total, supaya API yang lambat tidak menahan
  request pembeli.
- **Bedakan "tidak ada jawaban" dari "rusak".** 404 atau 400 dari API berarti daftar kosong,
  sedangkan 500 atau timeout dilempar sebagai `ShippingUnavailable`.
- **Jangan percaya datanya begitu saja.** Baris dengan ongkir Rp 0 dibuang, supaya data API yang
  rusak tidak menghasilkan ongkir gratis.
- **Hanya untuk yang login, dan dibatasi** 30 per menit, karena kuotanya berbayar.
- **Test tidak pernah memanggil internet.** `Http::preventStrayRequests()` dipasang di
  `TestCase`.

> 🔎 **Temuan tak terduga:** begitu pengaman di atas dipasang, beberapa test lama gagal. Ternyata
> **SSR Inertia menyala tanpa pernah disiapkan**, sehingga setiap halaman diam-diam mencoba
> server yang tidak ada. Sekarang SSR mati secara default (`INERTIA_SSR_ENABLED`).

## 3. Data yang melewati browser harus dikunci

API ongkir tidak bisa ditanya "ID 17473 itu wilayah apa?". Karena itu, hasil pencarian wilayah
dikirim ke browser sebagai **token terenkripsi** (`encrypt()` = AES + MAC). Form mengirim token
itu kembali, dan token yang diubah sedikit saja akan ditolak. Kolom asal pengiriman toko juga
**tidak fillable**: isinya hanya bisa datang dari token.

## 4. Alamat

- **Partial unique index** `ON addresses (user_id) WHERE is_primary`: database menjamin
  _maksimal satu_ alamat utama, dan `AddressBook` menjamin _minimal satu_.
- **Urutan penting:** lepas alamat utama yang lama **dulu**, baru set yang baru. Kalau
  terbalik, selama sesaat ada dua alamat utama dan database menolak.
- **Normalisasi sebelum validasi:** `+62 812-3456-7890` disimpan sebagai `081234567890`.
- **Autocomplete yang benar:** pakai _debounce_, batalkan request lama dengan `AbortController`
  supaya jawaban lama tidak menimpa yang baru, dan pastikan bisa dipakai dengan keyboard.

## 5. Checkout: browser hanya memilih, server menghitung

Browser hanya mengirim **pilihan**: `address_id`, `{"12": "jne:REG"}`, dan total yang sedang
dilihat pembeli. Server:

1. Membangun ulang `CheckoutPreview`, termasuk ongkir. Panggilan API ini terjadi **di luar**
   transaction, supaya baris database tidak terkunci selama menunggu jaringan.
2. Mencari kurir pilihan **di daftar milik server**. Kurir karangan (`"jne:GRATIS"`) otomatis
   tidak ditemukan.
3. Membandingkan totalnya dengan `expected_total`. Kalau berbeda, pesanan ditolak dengan pesan
   yang jelas. Pembeli tidak pernah ditagih diam-diam.

Satu checkout berarti **satu pembayaran**, dengan **satu pesanan per toko** (setiap toko mengirim
paketnya sendiri).

## 6. Membuat pesanan tanpa overselling

Di dalam **satu transaction**:

1. Kunci varian (`lockForUpdate`) **diurutkan berdasarkan ID**. Kalau dua checkout mengunci varian
   A dan B dalam urutan terbalik, keduanya saling menunggu selamanya (_deadlock_).
2. Cek ulang visibilitas, harga, dan stok pada baris yang sudah dikunci.
3. Kurangi stok, buat pesanan beserta itemnya, dan hapus baris keranjang.

Lapisan pengamannya:

| Lapis                         | Menangkap                                      |
| ----------------------------- | ---------------------------------------------- |
| `CartView` di keranjang       | Stok jelas kurang, tampil sebagai peringatan   |
| Perbandingan `expected_total` | Harga atau stok berubah sejak halaman dibuka   |
| Cek di dalam kunci            | Pembeli lain membeli **di antara** dua langkah |
| `CHECK (stock >= 0)`          | Bug di semua lapis di atas: database menolak   |

Test race-nya **disimulasikan**: `CheckoutPreview` diganti dengan versi yang menurunkan stok tepat
setelah pratinjau. Mutation check membuktikan kedua lapis terakhir sama-sama bekerja.

**Snapshot:** nama, varian, SKU, harga, berat, dan **alamat** disalin ke pesanan. Mengubah produk
atau menghapus alamat tidak mengubah pesanan lama.

**Idempotensi:** setiap kunjungan ke halaman checkout membuat satu UUID. Klik ganda atau
pengiriman ulang dengan kunci yang sama mengembalikan pesanan yang sama, dan indeks unik
`(user_id, idempotency_key)` menahan dua request yang benar-benar bersamaan.

**Kode publik** `ZTG-7K3M9QX2`: tidak membocorkan jumlah pesanan, tidak bisa ditebak, tanpa huruf
yang mudah tertukar (0/O, 1/I), dan dibuat dengan `random_int`.

## 7. Batas waktu bayar

`orders:expire` berjalan setiap 5 menit (service `scheduler` di Docker). Untuk setiap checkout
yang lewat 24 jam:

- Baris checkout **dikunci lalu dicek ulang**, jadi menjalankannya dua kali tidak mengembalikan
  stok dua kali (sudah diuji). Di Tahap 5, webhook pembayaran mengambil kunci yang sama, sehingga
  "dibayar pukul 23:59:59" dan "kedaluwarsa" tidak bisa sama-sama menang.
- Setiap checkout diproses dalam transaction kecilnya sendiri, supaya satu masalah tidak
  membatalkan semuanya.

---

## Kesalahan yang terjadi (dan pelajarannya)

| Masalah                                            | Penyebab → Perbaikan                                        |
| -------------------------------------------------- | ----------------------------------------------------------- |
| Helper test `put()` → _fatal error_                | Bentrok dengan helper HTTP Laravel → diganti `addItem()`    |
| `->tap()` pada model menerima _query builder_      | Diteruskan ke builder → helper ditulis biasa                |
| Test lama tiba-tiba "Not a valid Inertia response" | SSR menyala tanpa disiapkan, tertangkap pengaman HTTP       |
| Test "2 toko = 2 panggilan API" gagal              | Cache bekerja benar (rute sama) → test memakai asal berbeda |
| PHPStan: `collect()` tidak bisa menebak tipe       | Diganti loop biasa dengan tipe yang jelas                   |
| `sed` gagal menambah `use` di Git Bash             | Backslash di namespace → diedit manual                      |

## Yang sengaja ditunda

| Hal                                          | Tahap |
| -------------------------------------------- | ----- |
| Pembayaran Midtrans + webhook                | 5     |
| Penjual memproses dan mengirim pesanan, resi | 6     |
| Keranjang tamu (pilihan B)                   | nanti |
| SSR untuk SEO halaman produk                 | 9     |
