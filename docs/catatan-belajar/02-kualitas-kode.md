# Tahap 2: Kualitas Kode

**Tujuan:** memastikan mesin, bukan manusia, yang menangkap kesalahan sebelum kode sampai ke user.

**Hasil akhir:** semua pemeriksaan otomatis lolos, test terisolasi dari data development, dan CI
GitHub Actions sudah dikonfigurasi.

---

## Empat "penjaga kualitas"

|                                    | Backend (PHP)      | Frontend (React/TS)             |
| ---------------------------------- | ------------------ | ------------------------------- |
| **Format**: gaya kode seragam      | Pint               | `vp fmt` (via `npm run check`)  |
| **Lint**: pola kode yang rawan bug | PHPStan level 7    | `vp lint` (via `npm run check`) |
| **Type checking**                  | PHPStan / Larastan | `tsc` (`npm run types:check`)   |
| **Automated test**                 | PHPUnit            | belum ada                       |

**Kenapa penting di e-commerce:** bug di halaman checkout berarti kehilangan uang dan
kepercayaan pelanggan. Semakin banyak kesalahan yang tertangkap otomatis, semakin baik.

```bash
docker compose exec app composer test      # Pint + PHPStan + PHPUnit
docker compose exec app npm run check      # format + lint frontend
docker compose exec app composer ci:check  # SEMUANYA (sama persis dengan yang dijalankan CI)
```

---

## Langkah 1: Menjalankan `composer test` pertama kali

Hasil: Pint ✅, PHPStan ✅, tapi **20 test gagal**, kebanyakan dengan status **419**
(_CSRF token mismatch_). Padahal Laravel biasanya mematikan pengecekan CSRF saat test berjalan.

### Diagnosa

```
phpunit.xml        →  APP_ENV=testing,  DB_CONNECTION=sqlite (in-memory)
env di container   →  APP_ENV=local,    DB_CONNECTION=pgsql, DB_DATABASE=ecommerce
```

Nilai dari `phpunit.xml` **kalah** oleh nilai dari container. Artinya test berjalan dalam mode
`local`, dan **berjalan di database development**.

### 💥 Akibatnya: database development terhapus

Banyak test memakai `RefreshDatabase`, yang **menghapus lalu membuat ulang semua tabel** di
database yang aktif saat itu. Tabel `users` di database dev jadi kosong. Kejadiannya di awal
project, jadi belum ada data penting yang hilang. Di production, hal seperti ini bisa jadi bencana.

### Perbaikan berlapis (_defense in depth_)

**Lapis 1: `phpunit.xml`.** Tambahkan `force="true"` dan arahkan test ke database PostgreSQL
`testing`:

```xml
<env name="DB_CONNECTION" value="pgsql" force="true"/>
<env name="DB_DATABASE" value="testing" force="true"/>
```

Kenapa PostgreSQL, bukan SQLite? **Test sebaiknya memakai engine database yang sama dengan
production.** SQLite dan PostgreSQL punya perilaku yang berbeda (tipe data, pencarian teks,
dll.), sehingga test yang lolos di SQLite belum tentu benar di PostgreSQL.

**Lapis 2: pengaman di `tests/TestCase.php`.** Test langsung berhenti kalau database yang aktif
bukan `testing`:

```php
if ($database !== 'testing') {
    throw new RuntimeException("Tests must use the 'testing' database, got [...]");
}
```

Pengaman ini ditaruh di `createApplication()`, yang dipanggil **sebelum** `RefreshDatabase`
sempat menghapus apa pun.

### Membuktikan pengaman bekerja

Kita sengaja membuat konfigurasi yang salah (diarahkan ke database `ecommerce`):

```
RuntimeException: Tests must use the 'testing' database, got [pgsql:ecommerce]. ✅ ditolak
```

Lalu dengan konfigurasi yang benar... **semua 42 test ditolak**. Pengaman kita justru menemukan
**masalah kedua**: `force="true"` ternyata tidak cukup.

### Akar masalah sebenarnya

| Sumber nilai                       | Masuk ke                                             |
| ---------------------------------- | ---------------------------------------------------- |
| `env_file: .env` di `compose.yaml` | `$_SERVER` (environment asli)                        |
| `force="true"` di `phpunit.xml`    | `$_ENV` dan `getenv()` saja                          |
| Laravel membaca env dari...        | **`$_SERVER` lebih dulu** ← nilai Docker yang menang |

**Lapis 3: hapus `env_file: .env` dari `compose.yaml`.** Baris itu memang tidak diperlukan,
karena Laravel sudah membaca file `.env` sendiri. Tanpa baris itu, alurnya kembali ke cara standar
Laravel: PHPUnit memasang nilai test lebih dulu, dan `.env` tidak akan menimpanya.

### Hasil

```
Pint     PASS  54 files
PHPStan  [OK] No errors
Tests    42 passed (147 assertions)
```

Database dev dan database `testing` sekarang benar-benar terpisah.

### Pelajaran

- Test **tidak pernah** boleh memakai database development atau production.
- Lindungi berlapis: konfigurasi bisa saja salah suatu hari nanti, tapi pengaman tetap berjaga.
- **Uji pengaman Anda dengan sengaja.** Dari situlah bug kedua ketahuan.
- Hal yang "tidak diperlukan" (`env_file`) bisa menyebabkan bug yang sulit dilacak. Tambahkan
  konfigurasi hanya kalau Anda tahu alasannya.

---

## Langkah 2: Pemeriksaan frontend

```
npm run types:check   ✅ tidak ada error TypeScript
npm run check         ❌ README.md salah format
```

Yang salah format justru README yang ditulis di Tahap 1, karena kolom tabelnya tidak rata.
`vp check --fix` memperbaikinya otomatis. Pelajarannya: dokumentasi juga ikut dicek formatnya.

### Jebakan: exit code dan pipe

**Exit code** adalah cara program memberi tahu hasilnya: `0` berarti berhasil, selain `0` berarti
gagal. CI memakai angka ini untuk memutuskan lolos atau tidak.

```bash
npm run check | tail -15; echo $?    # ❌ yang tercetak adalah exit code milik `tail` (selalu 0)
npm run check > /dev/null; echo $?   # ✅ exit code milik `npm run check`
```

Pemeriksaan pertama sempat terbaca `EXIT=0` padahal sebenarnya gagal, karena jebakan ini.

---

## Langkah 3: CI dengan GitHub Actions

**Continuous Integration (CI):** setiap push atau Pull Request otomatis dicek oleh server
GitHub. Kalau ada pemeriksaan yang gagal, PR mendapat tanda ❌ dan tidak boleh di-_merge_.

File: [.github/workflows/tests.yml](../../.github/workflows/tests.yml)

### Yang diperbaiki dari bawaan starter kit

| Bawaan                     | Diubah menjadi                      | Kenapa                                       |
| -------------------------- | ----------------------------------- | -------------------------------------------- |
| PHP 8.3, Node 22           | PHP 8.5, Node 24                    | Harus sama dengan Docker/production          |
| Tanpa database             | `services: pgsql` (Postgres 18)     | Test butuh database `testing`                |
| Host `pgsql` (dari `.env`) | `DB_HOST: 127.0.0.1`                | Di GitHub tidak ada nama service Docker      |
| Ekstensi PHP default       | `intl, gd, bcmath, exif, pdo_pgsql` | Sama dengan Dockerfile                       |
| –                          | `concurrency`                       | Push beruntun → run lama dibatalkan          |
| –                          | `timeout-minutes: 20`               | Test yang macet tidak menggantung berjam-jam |

### Yang sudah bagus dan dipertahankan

- **Action di-_pin_ ke commit SHA** (`actions/checkout@3d3c42e...`), bukan sekadar `@v7`. Tag
  bisa dipindahkan oleh pemiliknya (atau penyerang), sedangkan SHA tidak bisa. Ini proteksi
  _supply-chain attack_.
- **`permissions: contents: read`**: CI hanya diberi izin membaca, sesuai prinsip _least privilege_.
- **Dependabot** (`.github/dependabot.yml`): otomatis membuat PR setiap minggu kalau ada versi
  action yang baru.

### Verifikasi

- ✅ `actionlint` (linter khusus GitHub Actions): tidak ada kesalahan
- ✅ `composer ci:check` lolos di container, dan ini perintah yang sama persis dengan yang
  dijalankan CI
- ⚠️ **Belum dijalankan di GitHub** karena project belum di-push. Hasil sebenarnya baru terlihat
  saat push pertama.
