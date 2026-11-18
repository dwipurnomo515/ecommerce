# Catatan Belajar: Membangun E-commerce dari Nol

Catatan pribadi proses membangun project ini, langkah demi langkah: **apa** yang dikerjakan,
**kenapa** dikerjakan begitu, dan **pelajaran** dari setiap error yang muncul.

> README utama di root project menjelaskan _cara menjalankan_ project.
> Catatan ini menjelaskan _cara project ini dibuat_ dan _alasan di balik setiap keputusan_.

## Daftar isi

| Tahap | Topik                                                   | Status     |
| ----- | ------------------------------------------------------- | ---------- |
| 1     | [Fondasi](01-fondasi.md)                                | ✅ Selesai |
| 2     | [Kualitas kode](02-kualitas-kode.md)                    | ✅ Selesai |
| 3     | [Katalog produk](03-katalog.md)                         | ✅ Selesai |
| 4     | [Keranjang, checkout, ongkir](04-keranjang-checkout.md) | ✅ Selesai |
| 5     | [Pembayaran (Midtrans)](05-pembayaran.md)               | ✅ Selesai |
| 6     | Pesanan & ulasan                                        | ⏳         |
| 7     | Saldo penjual & pencairan dana                          | ⏳         |
| 8     | Hardening keamanan                                      | ⏳         |
| 9     | Production (Docker image, deploy)                       | ⏳         |

Istilah yang belum familiar? Lihat [Glosarium](glosarium.md).

## Riwayat commit sejauh ini

Tahap 5:

```
07890c9 feat(payments): reconcile payments when a webhook is missed
417101a feat(dev): demo data seeder and sample courier for local testing
8c81377 feat(payments): Midtrans payment webhook
f0b6116 feat(payments): pay with Midtrans Snap
```

Tahap 4 (`git log --oneline 3453f8f..`):

```
238cc85 feat(orders): expire unpaid checkouts and buyer order list
b606171 feat(checkout): place orders with frozen prices and safe stock
70a15a0 feat(checkout): checkout preview with per-store shipping quotes
4cdc7cb feat(shipping): store shipping origin and RajaOngkir cost quotes
64db41e feat(addresses): buyer address book with region autocomplete
ca1c751 feat(shipping): RajaOngkir destination search behind a ShippingProvider
0f8696e feat(cart): cart page grouped per store with change warnings
2eecd54 feat(cart): add to cart with server-side stock checks
```

Tahap 3 terdiri dari 24 commit (`git log --oneline 7158f66..`). Sorotannya:

```
1677cf1 feat(shop): public store page and links between storefront pages
cfbbedc feat(shop): home, category and search pages
2112434 feat(shop): public product detail page with variant picker
27866b9 feat(admin): ban and unban products with a reason for the seller
0925c6d feat(seller): edit products with variant upsert and archiving
9f2e668 feat(seller): upload, reorder and delete product photos
4bc1e7d feat(seller): add product creation backend with variants
dbcd104 feat(admin): review, approve and suspend stores
553aefb feat(admin): add is_admin flag, admin gate and app:make-admin command
43b905e feat(catalog): add products, product variants and product images tables
```

Tahap 1–2:

```
7158f66 ci: align GitHub Actions with the Docker stack
17c1c12 style: format README with project formatter
cbd4c3e fix(tests): isolate test suite from the development database
c99f081 docs: add README with Docker setup and daily commands
93c8a51 chore: scaffold Laravel 13 + React starter kit with Docker dev stack
```

Lihat detailnya dengan `git log --stat`.

## Pelajaran terpenting sejauh ini

1. **Diagnosa dulu, baru perbaiki.** Hampir setiap error di Tahap 1–2 punya penyebab yang
   berbeda dari dugaan pertama. Membaca log dan mengukur selalu membawa ke akar masalah.
2. **Buktikan perbaikan, jangan diasumsikan.** Saat menguji pengaman database dengan sengaja,
   justru ketahuan ada bug kedua yang tersembunyi.
3. **Test tidak boleh menyentuh data development atau production.** Kita sempat kehilangan
   isi database dev karena hal ini (lihat [Tahap 2](02-kualitas-kode.md#langkah-1-menjalankan-composer-test-pertama-kali)).
4. **Tulis alasannya, bukan cuma caranya.** Komentar di `compose.yaml`, `phpunit.xml`,
   dan `tests/TestCase.php` menjelaskan _kenapa_ sebuah baris ada di sana.
5. **Setiap kompromi harus disadari.** Contohnya: memilih tetap di folder Windows berarti
   menerima halaman yang lambat, dan keputusan itu tercatat beserta angkanya.
6. **Test hijau belum tentu berguna. Rusak kodenya untuk membuktikan.** _Mutation check_ di
   Tahap 3 menemukan aturan yang tidak teruji dan satu pengaman yang diam-diam tidak bekerja
   (lihat [Tahap 3](03-katalog.md#7-cara-kita-membuktikan-test-itu-berguna-mutation-check)).
7. **Satu aturan, satu tempat.** "Boleh dilihat pembeli" hanya ditulis di `visibleToBuyers()`,
   dan "bentuk kartu produk" hanya di `ProductCards`. Aturan yang disalin ke banyak tempat
   cepat atau lambat akan berbeda satu sama lain.
8. **Jangan percaya apa pun dari browser:** isi form, nama file, angka di URL, `?sort=`. Validasi
   di klien hanya untuk kenyamanan, dan server selalu memeriksa ulang.
9. **Browser memilih, server menghitung.** Di checkout, browser hanya mengirim pilihan, sedangkan
   setiap rupiah dihitung ulang di server (lihat [Tahap 4](04-keranjang-checkout.md)).
10. **Pertahanan berlapis untuk uang dan stok:** cek di kode, kunci baris, dan constraint database.
    Mutation check membuktikan bahwa lapis terakhir tetap menahan walaupun lapis di atasnya hilang.
11. **Percaya bukti, bukan klaim.** Status "lunas" hanya dari notifikasi bertanda tangan sah atau
    dari jawaban API yang kita minta sendiri, tidak pernah dari browser (lihat
    [Tahap 5](05-pembayaran.md)).
12. **Pesan bisa hilang.** Webhook yang tidak sampai tetap tersusul lewat rekonsiliasi. Kejadian
    nyatanya terjadi di tahap ini, dan ditangani dengan fitur, bukan dengan berharap.
