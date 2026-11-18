<!-- Catatan belajar Tahap 7. Ditulis untuk diri sendiri, Bahasa Indonesia. -->

# Tahap 7: Saldo Penjual, Pencairan, dan Refund

**Tujuan:** uang yang dibayar pembeli sampai ke tempat yang benar: ke **penjual** setelah barang
diterima (dikurangi komisi Zetago), atau kembali ke **pembeli** kalau pesanan batal. Setiap rupiah
harus bisa dijelaskan.

**Hasil akhir:** 305 automated test, semuanya lolos. Transfer bank dan refund masih **simulasi**
(otomatis, tanpa uang sungguhan), dengan _interface_ yang siap diganti layanan nyata.

```
Pembeli bayar ─▶ (escrow) ─▶ pesanan Selesai ─▶ saldo penjual + (harga+ongkir) − komisi
                                │                       └─▶ tarik dana ─▶ job ─▶ transfer (simulasi)
                                └─ penjual batalkan sebelum kirim ─▶ stok kembali + refund pembeli
```

---

## Peta fitur

| Bagian                 | File utama                                                         |
| ---------------------- | ------------------------------------------------------------------ |
| Komisi                 | `config/marketplace.php`, `Order::commission()`                    |
| Buku besar             | `store_ledger_entries`, `LedgerEntry`, `Ledger::post()`            |
| Kredit pesanan selesai | `FulfilOrder::complete()` → `Ledger::creditCompletedOrder()`       |
| Pencairan              | `RequestPayout`, `ProcessPayout` (job), `PayoutGateway`            |
| Pembatalan + refund    | `CancelPaidOrder`, `ReturnStock`, `RequestRefund`, `ProcessRefund` |
| Halaman                | `seller/balance` (Saldo), tombol Batalkan di Pesanan masuk         |

---

## 1. Uang dihitung tanpa _float_

- Komisi dalam **basis poin**: `500` = 5%. Tetap bilangan bulat, dan tarif seperti 2,5% (`250`)
  tetap bisa dipakai.
- `intdiv(harga × bps, 10000)`, **dibulatkan ke bawah**. Pecahan rupiah menjadi milik penjual.
  Mutation `round()` menggagalkan 5 test karena selisih **Rp 1**.
- **Tarif dibekukan di pesanan** saat dibuat, jadi menaikkan komisi besok tidak mengubah pesanan
  hari ini.
- Komisi hanya dari **harga barang**. Ongkir adalah hak penjual untuk membayar kurir.

## 2. Buku besar _append-only_

| Aturan                                   | Penjaganya                                                                 |
| ---------------------------------------- | -------------------------------------------------------------------------- |
| Baris tidak pernah diubah/dihapus        | Model `LedgerEntry` melempar exception saat update/delete                  |
| Saldo = baris terakhir, selalu sinkron   | `Ledger::post()`: kunci toko → tulis baris → geser saldo, satu transaction |
| Satu pesanan dikreditkan sekali          | Indeks unik `(order_id, type)`                                             |
| Satu payout dipotong/dikembalikan sekali | Indeks unik `(payout_id, type)`                                            |
| Saldo tidak pernah minus                 | `CHECK (balance >= 0)`                                                     |

Kesalahan dikoreksi dengan **baris baru yang berlawanan**. Pembayaran yang gagal ditransfer
tercatat sebagai dua baris: "− Penarikan" lalu "+ Penarikan gagal, dana dikembalikan".

## 3. _Escrow_: uang ditahan sampai barang diterima

Saldo penjual baru bertambah saat pesanan **Selesai** (dikonfirmasi pembeli atau otomatis setelah
7 hari), bukan saat dibayar. Manfaatnya langsung terasa di refund: pesanan yang dibatalkan sebelum
dikirim **tidak perlu menarik kembali uang dari penjual**, karena memang belum pernah diberikan.

## 4. Pencairan: potong dulu, transfer di latar belakang

1. `RequestPayout` mengunci toko, mengecek saldo, membuat payout, dan **langsung memotong saldo**
   dalam satu transaction. Dana yang sama tidak bisa diajukan dua kali.
2. **Satu penarikan aktif per toko** (_partial unique index_ `WHERE status = 'pending'`).
3. `ProcessPayout` adalah **job antrean** (`afterCommit`, jeda 5 detik). Gateway dipanggil di luar
   kunci database.

| Hasil gateway      | Tindakan                                                                                                                           |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| Berhasil           | Status "Berhasil" + referensi                                                                                                      |
| Gagal permanen     | Status "Gagal" + baris ledger pengembalian                                                                                         |
| Gangguan sementara | Job dilempar ulang, dicoba lagi (30 dtk, 1, 2, 5 menit)                                                                            |
| Percobaan habis    | **Tidak** dikembalikan otomatis, karena dengan layanan nyata kita tidak tahu apakah uangnya sudah terkirim. Dicatat untuk manusia. |

**Simulasi yang jujur:** rekening berakhiran `0000` gagal, `9999` meniru gangguan, dan sisanya
berhasil. Halaman menampilkan "Mode simulasi". Binding gateway **menolak dibuat di production**,
supaya simulasi tidak pernah berpura-pura memindahkan uang sungguhan.

## 5. Pembatalan setelah dibayar → refund

- _State machine_ diperluas: **Dibayar/Dikemas → Dibatalkan** boleh, tetapi **Dikirim → Dibatalkan**
  tidak (itu sengketa, bukan pembatalan).
- `CancelPaidOrder` menjalankan semuanya dalam **satu transaction**: status + alasan, stok kembali
  (`ReturnStock`, dipakai bersama dengan job kedaluwarsa), dan refund dibuat.
- Refund sekali per pesanan dan sekali per checkout (dua _partial unique index_).
- Checkout **"Perlu refund"** (uang datang setelah dibatalkan) sekarang otomatis dibuatkan refund
  oleh webhook atau rekonsiliasi.

---

## Kesalahan dan temuan

| Masalah                                              | Penyebab → Perbaikan                                                              |
| ---------------------------------------------------- | --------------------------------------------------------------------------------- |
| Test saldo: request kedua masih melihat saldo lama   | `actingAs()` memakai objek user yang sama, relasi `store` ter-cache → `->fresh()` |
| `autoStart` polling tidak menyala setelah tarik dana | Opsi hanya dibaca sekali → `start()`/`stop()` dari `useEffect`                    |
| Worker antrean ternyata mati di dev                  | Dinyalakan (`docker compose up -d queue`)                                         |
| Mutation: cek saldo dihapus                          | Bukan celah: `CHECK (balance >= 0)` tetap menolak (lapis terakhir)                |

## Yang sengaja ditunda

| Hal                                                     | Tahap |
| ------------------------------------------------------- | ----- |
| Penyedia transfer & refund sungguhan (Iris/Xendit/Flip) | 9     |
| Halaman admin: laporan komisi, payout/refund yang macet | 8     |
| Sengketa setelah dikirim ("barang tidak sampai/rusak")  | nanti |
| Verifikasi nama pemilik rekening (_account inquiry_)    | 9     |
