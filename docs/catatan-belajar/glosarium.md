# Glosarium

Istilah-istilah yang muncul selama membangun project, diurutkan berdasarkan abjad.

| Istilah                      | Arti singkat                                                                                                               |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| **Action class**             | Kelas kecil berisi satu aturan bisnis (`CreateProduct`, `ModerateProduct`), bisa dipakai dari controller atau job.         |
| **Append-only**              | Data yang hanya boleh ditambah, tidak diubah/dihapus (buku besar saldo); koreksi = baris baru yang berlawanan.             |
| **APP_KEY**                  | Kunci rahasia Laravel untuk enkripsi (cookie, session). Jangan sampai bocor.                                               |
| **Atomic lock**              | Kunci di cache (Redis) yang hanya bisa dipegang satu proses (`Cache::lock`), tanpa mengunci baris database.                |
| **Backfill**                 | Mengisi kolom baru dari data lama saat migration (mis. total rating toko dari ulasan yang sudah ada).                      |
| **Backoff**                  | Jeda yang makin panjang antar percobaan ulang (30 dtk, 1, 2, 5 menit) saat layanan luar gangguan.                          |
| **Basis poin (bps)**         | Seperseratus persen: 500 bps = 5%. Membuat tarif tetap bilangan bulat.                                                     |
| **Bind mount**               | Menghubungkan folder di komputer ke dalam container (`.:/var/www/html`). Perubahan langsung terlihat di kedua sisi.        |
| **CHECK constraint**         | Aturan di database (`stock >= 0`) yang menolak data salah, walaupun validasi aplikasi terlewati.                           |
| **CI**                       | _Continuous Integration_: pemeriksaan otomatis setiap kali kode di-push.                                                   |
| **Composer**                 | Package manager untuk PHP (seperti npm untuk JavaScript).                                                                  |
| **Container**                | "Komputer mini" terisolasi yang menjalankan satu layanan (PHP, database, dll.).                                            |
| **CSRF**                     | Serangan yang memalsukan form dari situs lain. Laravel menolaknya dengan error 419.                                        |
| **Deadlock**                 | Dua transaksi saling menunggu kunci milik yang lain selamanya. Dicegah dengan selalu mengunci dalam urutan yang sama (id). |
| **Debounce**                 | Menunggu sampai user berhenti mengetik sebentar sebelum mengirim request (autocomplete).                                   |
| **Defense in depth**         | Pengamanan berlapis: kalau satu lapis gagal, lapis lain masih menahan.                                                     |
| **Denormalisasi**            | Menyimpan hasil hitungan (total rating) agar cepat dibaca; wajib diperbarui di tempat yang sama dengan datanya.            |
| **Dirty checking**           | Eloquent hanya menyimpan kolom yang berubah dibanding saat data dimuat.                                                    |
| **Docker Compose**           | Tool untuk menjalankan banyak container sekaligus dari satu file (`compose.yaml`).                                         |
| **Dockerfile**               | "Resep" untuk membuat image Docker.                                                                                        |
| **Eager loading**            | Mengambil relasi sekaligus (`with([...])`) supaya tidak terjadi N+1 query.                                                 |
| **Enum**                     | Tipe dengan pilihan nilai yang tetap (`ProductStatus::Active`), lebih aman daripada string bebas.                          |
| **Escrow**                   | Uang pembeli ditahan marketplace sampai barang diterima, baru diteruskan ke penjual.                                       |
| **EXIF**                     | Metadata di dalam foto (model kamera, waktu, **lokasi GPS**). Bisa membocorkan privasi.                                    |
| **Exit code**                | Angka hasil program: `0` berarti berhasil, selain `0` berarti gagal.                                                       |
| **Factory**                  | Pembuat data palsu untuk test (`Product::factory()->active()->create()`).                                                  |
| **Form Request**             | Kelas Laravel yang menampung otorisasi + aturan validasi sebuah form.                                                      |
| **Gate**                     | Aturan izin sederhana yang tidak terikat model, misalnya `admin`.                                                          |
| **Healthcheck**              | Pengecekan berkala apakah sebuah service benar-benar siap, bukan sekadar sudah menyala.                                    |
| **HMR**                      | _Hot Module Replacement_: perubahan kode React langsung tampil tanpa reload halaman.                                       |
| **Idempotensi**              | Mengulang permintaan yang sama hasilnya tetap satu kali (klik ganda = satu pesanan).                                       |
| **IDOR**                     | _Insecure Direct Object Reference_: mengakses data orang lain dengan mengganti ID di URL.                                  |
| **Image**                    | Template read-only untuk membuat container (hasil build Dockerfile).                                                       |
| **Inertia.js**               | Penghubung Laravel dan React: route & data di Laravel, tampilan di React, tanpa API terpisah.                              |
| **Interface**                | Kontrak "apa yang bisa dilakukan" tanpa "bagaimana" (`ShippingProvider`), sehingga implementasinya bisa diganti.           |
| **Job / queue**              | Pekerjaan lambat yang dikerjakan di latar belakang oleh worker (`ProcessPayout`), bukan saat request.                      |
| **jsonb**                    | Kolom JSON di PostgreSQL. Bisa di-query, tapi **mengurutkan ulang key** objek.                                             |
| **Lazy loading**             | Relasi diambil saat pertama diakses, sehingga satu query per baris. Dilarang di project ini lewat `shouldBeStrict`.        |
| **Ledger (buku besar)**      | Catatan setiap perubahan saldo beserta saldo sesudahnya.                                                                   |
| **Lint**                     | Analisis kode untuk menemukan pola yang rawan bug atau melanggar aturan.                                                   |
| **Lock file**                | `composer.lock` / `package-lock.json`: mencatat versi persis setiap library.                                               |
| **lockForUpdate**            | Mengunci baris database sampai transaction selesai, supaya proses lain menunggu giliran.                                   |
| **Lost update**              | Dua proses baca–hitung–tulis bersamaan; salah satu perubahan hilang. Dicegah dengan UPDATE atomik.                         |
| **Magic bytes**              | Beberapa byte pertama file yang menunjukkan jenis aslinya, apa pun ekstensinya.                                            |
| **Mass assignment**          | Mengisi banyak kolom sekaligus dari input. Kolom sensitif (`status`, `is_admin`) dibuat tidak fillable.                    |
| **Migration**                | File yang mendeskripsikan struktur tabel database, tersimpan di Git.                                                       |
| **Multi-stage build**        | Dockerfile dengan beberapa tahap (`FROM ... AS ...`) untuk image dev & production.                                         |
| **Mutation check**           | Sengaja merusak kode untuk membuktikan bahwa test-nya benar-benar gagal.                                                   |
| **N+1 query**                | 1 query untuk daftar + 1 query per baris. Lambat, dan dicegah dengan eager loading.                                        |
| **Named volume**             | Penyimpanan data yang dikelola Docker. Data tetap ada walaupun container dihapus.                                          |
| **OPcache**                  | Cache di memori untuk hasil compile file PHP, membuat PHP lebih cepat.                                                     |
| **Overselling**              | Menjual lebih banyak dari stok yang ada. Dicegah dengan kunci baris + `CHECK (stock >= 0)`.                                |
| **Partial index**            | Indeks yang hanya berlaku untuk sebagian baris (`... WHERE is_primary`).                                                   |
| **Path traversal**           | Nama file seperti `../../.env` untuk keluar dari folder yang diizinkan.                                                    |
| **Payout**                   | Pencairan saldo penjual ke rekening banknya.                                                                               |
| **PHP-FPM**                  | Proses yang menjalankan kode PHP untuk setiap request web (di belakang Nginx).                                             |
| **PHPStan / Larastan**       | Static analysis: mencari bug PHP tanpa menjalankan kode. Larastan = versi khusus Laravel.                                  |
| **Pint**                     | Formatter kode PHP resmi Laravel.                                                                                          |
| **Policy**                   | Kelas yang menjawab "boleh user ini melakukan X pada model ini?" (`ProductPolicy::update`).                                |
| **Query scope**              | Potongan query bernama yang bisa dipakai ulang (`Product::visibleToBuyers()`).                                             |
| **Race condition**           | Dua proses berjalan bersamaan dan hasilnya bergantung pada siapa yang lebih dulu.                                          |
| **Rate limit**               | Batas jumlah request per menit (`throttle:30,1`), melindungi kuota dan server.                                             |
| **RefreshDatabase**          | Trait test Laravel yang membuat ulang semua tabel. Berbahaya kalau diarahkan ke database yang salah.                       |
| **Refund**                   | Pengembalian dana ke pembeli.                                                                                              |
| **Rekonsiliasi**             | Mencocokkan status kita dengan sumber aslinya (bertanya ke Midtrans) untuk menyusul pesan yang hilang.                     |
| **Sandbox**                  | Lingkungan uji penyedia layanan (Midtrans) dengan uang tiruan.                                                             |
| **Scaffold**                 | Membuat kerangka awal project dari template.                                                                               |
| **Scheduler**                | Menjalankan perintah secara berkala (`orders:expire` tiap 5 menit) lewat `schedule:work`.                                  |
| **Scope binding**            | `scopeBindings()`: `/products/1/images/99` hanya valid kalau foto 99 milik produk 1.                                       |
| **Seeder**                   | Kelas pengisi data awal/contoh ke database (`db:seed --class=DemoSeeder`).                                                 |
| **Signature (tanda tangan)** | Hash dari isi pesan + kunci rahasia; membuktikan pesan berasal dari pemilik kunci dan tidak diubah.                        |
| **Slug**                     | Versi nama untuk URL: "Kaos Polos" → `kaos-polos`.                                                                         |
| **Snapshot**                 | Salinan data saat suatu kejadian (harga & alamat di pesanan), supaya perubahan nanti tidak mengubah masa lalu.             |
| **Soft delete**              | "Hapus" dengan mengisi `deleted_at`. Baris tetap ada, sehingga pesanan lama tidak rusak.                                   |
| **SSR**                      | _Server-side rendering_: HTML halaman dibuat di server. Belum dipakai (dimatikan) di project ini.                          |
| **Starter kit**              | Template project resmi Laravel, lengkap dengan auth dan frontend.                                                          |
| **State machine**            | Daftar status dan perpindahan yang sah (`canTransitionTo`), sehingga langkah tidak bisa dilompati/dibalik.                 |
| **Supply-chain attack**      | Serangan lewat library/tools pihak ketiga yang dipakai project.                                                            |
| **Tie-breaker**              | Kunci urutan terakhir yang unik (`id`) supaya pagination tidak menampilkan baris ganda.                                    |
| **Timing attack**            | Menebak rahasia dari perbedaan waktu pembandingan; dicegah dengan `hash_equals`.                                           |
| **TOCTOU**                   | _Time-of-check vs time-of-use_: kondisi berubah di antara saat dicek dan saat dipakai.                                     |
| **Transaction**              | Sekelompok perubahan database yang berhasil semua atau batal semua.                                                        |
| **Tunnel**                   | URL publik sementara yang meneruskan ke komputer lokal (ngrok, Cloudflare Tunnel).                                         |
| **Upsert**                   | Update yang sudah ada, insert yang baru. Dipakai untuk varian supaya ID-nya tetap.                                         |
| **Valkey**                   | Database in-memory yang kompatibel dengan Redis, dipakai untuk cache, session, dan antrian.                                |
| **Vite**                     | Build tool frontend: dev server cepat + bundling untuk production.                                                         |
| **Wayfinder**                | Membuat fungsi TypeScript dari route Laravel, sehingga URL yang salah ketahuan saat compile.                               |
| **Webhook**                  | Server pihak lain memanggil URL kita untuk memberi tahu suatu kejadian (mis. pembayaran lunas).                            |
| **Whitelist**                | Hanya menerima nilai dari daftar yang sudah ditentukan (`?sort=` hanya terbaru/termurah/termahal).                         |
| **WSL**                      | _Windows Subsystem for Linux_: menjalankan Linux di dalam Windows.                                                         |
| **www-data**                 | User Linux non-root untuk menjalankan web server. Lebih aman daripada root.                                                |
| **XSS**                      | _Cross-site scripting_: menyisipkan script ke halaman orang lain (misalnya lewat file SVG).                                |
