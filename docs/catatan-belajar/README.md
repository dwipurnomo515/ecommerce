# Catatan Belajar: Membangun E-commerce dari Nol

Catatan pribadi proses membangun project ini, langkah demi langkah: **apa** yang dikerjakan,
**kenapa** dikerjakan begitu, dan **pelajaran** dari setiap error yang muncul.

> README utama di root project menjelaskan _cara menjalankan_ project.
> Catatan ini menjelaskan _cara project ini dibuat_ dan _alasan di balik setiap keputusan_.

## Daftar isi

| Tahap | Topik                                | Status     |
| ----- | ------------------------------------ | ---------- |
| 1     | [Fondasi](01-fondasi.md)             | ✅ Selesai |
| 2     | [Kualitas kode](02-kualitas-kode.md) | ✅ Selesai |
| 3     | Katalog produk                       | ⏳         |
| 4     | Keranjang & checkout                 | ⏳         |
| 5     | Pembayaran (Midtrans)                | ⏳         |
| 6     | Manajemen order                      | ⏳         |
| 7     | Hardening keamanan                   | ⏳         |
| 8     | Production (Docker image, deploy)    | ⏳         |

Istilah yang belum familiar? Lihat [Glosarium](glosarium.md).

## Riwayat commit sejauh ini

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
