# Glosarium

Istilah-istilah yang muncul selama membangun project, diurutkan berdasarkan abjad.

| Istilah                 | Arti singkat                                                                                                        |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------- |
| **APP_KEY**             | Kunci rahasia Laravel untuk enkripsi (cookie, session). Jangan sampai bocor.                                        |
| **Bind mount**          | Menghubungkan folder di komputer ke dalam container (`.:/var/www/html`). Perubahan langsung terlihat di kedua sisi. |
| **CI**                  | _Continuous Integration_: pemeriksaan otomatis setiap kali kode di-push.                                            |
| **Composer**            | Package manager untuk PHP (seperti npm untuk JavaScript).                                                           |
| **Container**           | "Komputer mini" terisolasi yang menjalankan satu layanan (PHP, database, dll.).                                     |
| **CSRF**                | Serangan yang memalsukan form dari situs lain. Laravel menolaknya dengan error 419.                                 |
| **Defense in depth**    | Pengamanan berlapis: kalau satu lapis gagal, lapis lain masih menahan.                                              |
| **Docker Compose**      | Tool untuk menjalankan banyak container sekaligus dari satu file (`compose.yaml`).                                  |
| **Dockerfile**          | "Resep" untuk membuat image Docker.                                                                                 |
| **Exit code**           | Angka hasil program: `0` berarti berhasil, selain `0` berarti gagal.                                                |
| **Healthcheck**         | Pengecekan berkala apakah sebuah service benar-benar siap, bukan sekadar sudah menyala.                             |
| **HMR**                 | _Hot Module Replacement_: perubahan kode React langsung tampil tanpa reload halaman.                                |
| **Image**               | Template read-only untuk membuat container (hasil build Dockerfile).                                                |
| **Inertia.js**          | Penghubung Laravel dan React: route & data di Laravel, tampilan di React, tanpa API terpisah.                       |
| **Lint**                | Analisis kode untuk menemukan pola yang rawan bug atau melanggar aturan.                                            |
| **Lock file**           | `composer.lock` / `package-lock.json`: mencatat versi persis setiap library.                                        |
| **Migration**           | File yang mendeskripsikan struktur tabel database, tersimpan di Git.                                                |
| **Multi-stage build**   | Dockerfile dengan beberapa tahap (`FROM ... AS ...`) untuk image dev & production.                                  |
| **Named volume**        | Penyimpanan data yang dikelola Docker. Data tetap ada walaupun container dihapus.                                   |
| **OPcache**             | Cache di memori untuk hasil compile file PHP, membuat PHP lebih cepat.                                              |
| **PHP-FPM**             | Proses yang menjalankan kode PHP untuk setiap request web (di belakang Nginx).                                      |
| **PHPStan / Larastan**  | Static analysis: mencari bug PHP tanpa menjalankan kode. Larastan = versi khusus Laravel.                           |
| **Pint**                | Formatter kode PHP resmi Laravel.                                                                                   |
| **RefreshDatabase**     | Trait test Laravel yang membuat ulang semua tabel. Berbahaya kalau diarahkan ke database yang salah.                |
| **Scaffold**            | Membuat kerangka awal project dari template.                                                                        |
| **Starter kit**         | Template project resmi Laravel, lengkap dengan auth dan frontend.                                                   |
| **Supply-chain attack** | Serangan lewat library/tools pihak ketiga yang dipakai project.                                                     |
| **Valkey**              | Database in-memory yang kompatibel dengan Redis, dipakai untuk cache, session, dan antrian.                         |
| **Vite**                | Build tool frontend: dev server cepat + bundling untuk production.                                                  |
| **Wayfinder**           | Membuat fungsi TypeScript dari route Laravel, sehingga URL yang salah ketahuan saat compile.                        |
| **WSL**                 | _Windows Subsystem for Linux_: menjalankan Linux di dalam Windows.                                                  |
| **www-data**            | User Linux non-root untuk menjalankan web server. Lebih aman daripada root.                                         |
