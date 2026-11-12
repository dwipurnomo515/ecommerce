# Design Brief: Premium Marketplace UI/UX

Acuan tampilan untuk seluruh halaman marketplace. Setiap halaman baru harus lolos
**checklist review** di bagian 8 sebelum dianggap selesai.

## 1. Design direction

Karakter desain:

- Modern, clean, minimal, elegant, dan professional.
- Premium e-commerce experience.
- User-friendly dan conversion-oriented.
- Memiliki karakter visual yang kuat tetapi tidak berlebihan.
- Natural, tidak terlihat seperti template AI.
- Fokus pada pengalaman browsing dan shopping yang nyaman.

Referensi kualitas (bukan untuk ditiru langsung):

- **Shopify**: struktur e-commerce dan pengalaman belanja.
- **Airbnb**: visual hierarchy, whitespace, dan kesederhanaan.
- **Linear**: konsistensi komponen, typography, dan detail UI.

## 2. Visual design

**Color palette**

- Warna netral sebagai fondasi desain.
- Satu warna primary yang menjadi identitas marketplace.
- Warna aksen dipakai konsisten untuk CTA dan elemen penting.
- Warna status hanya dipakai sesuai fungsinya.
- Hindari gradient berlebihan dan warna yang terlalu mencolok.

**Typography**

- Modern dan mudah dibaca.
- Hierarchy yang jelas antara nama produk, harga, deskripsi, kategori, dan informasi tambahan.
- Harga dan nama produk harus mudah ditemukan.
- Ukuran dan font weight dipakai secara konsisten.

**Layout & spacing**

- Whitespace yang proporsional, layout rapi dan tidak terlalu padat.
- Grid produk yang konsisten. Gambar produk mendapat ruang visual yang cukup.
- Jangan menjadikan seluruh section sebagai card.
- Separator dan border hanya jika diperlukan.

## 3. Marketplace experience

**Homepage**: navigasi sederhana, search bar mudah ditemukan, navigasi kategori jelas,
hero/banner tidak berlebihan, featured products & rekomendasi, section promosi terstruktur,
product card konsisten.

**Product listing**: grid rapi, filter & sorting mudah dipakai, harga jelas, rating/review dan
info toko proporsional, pagination atau infinite scroll yang nyaman.

**Product detail**: galeri menarik, hierarchy nama & harga jelas, pilihan variasi mudah, deskripsi
mudah dibaca, info seller & reputasi toko mudah ditemukan, CTA _Add to Cart_ dan _Buy Now_ jelas,
info pengiriman dan stok mudah dipahami.

**Cart & checkout**: alur sederhana, ringkasan pesanan mudah dipahami, biaya produk, ongkir,
diskon, dan total transparan, form tidak membingungkan, feedback jelas di setiap proses.

**Seller experience**: navigasi dashboard jelas, kelola produk & pesanan mudah, statistik
penjualan sederhana, status pesanan mudah dipantau.

## 4. Hindari desain "generik AI"

- Gradient berlebihan, glassmorphism yang tidak perlu.
- Terlalu banyak rounded card, shadow tebal di semua komponen.
- Badge warna-warni berlebihan, dekorasi berlebihan.
- Icon dan emoji yang tidak konsisten.
- Product card yang terlalu ramai.
- Layout yang terlihat seperti template e-commerce AI.
- Banner promosi yang terlalu mendominasi.
- Placeholder gambar produk jika data produk asli tersedia.

Setiap elemen harus memiliki tujuan yang jelas.

## 5. Konsistensi komponen

Design system yang konsisten untuk: header & navigation, search bar, category navigation,
product card, product gallery, price display, rating & review, filter & sorting, button & CTA,
form & input, cart & checkout, seller information, modal & dropdown, notification & status
indicator.

## 6. Responsive

Optimal di desktop, tablet, dan mobile. **Jangan hanya mengecilkan tampilan desktop.** Sesuaikan
layout, navigasi, product grid, filter, dan checkout untuk tiap perangkat.

## 7. Aturan implementasi

Sebelum mengubah kode: pelajari struktur project, komponen & design system yang ada, user flow,
dan business logic yang sudah berjalan.

Saat implementasi:

- Pertahankan business logic, dan jangan menghilangkan fitur yang sudah ada.
- Reuse komponen dan hindari duplikasi. Kode harus maintainable.
- Gunakan data produk asli jika tersedia. **Jangan membuat data dummy seolah-olah data asli.**
  (Data contoh untuk development harus jelas ditandai sebagai data contoh.)
- Pastikan seluruh interaksi berfungsi.

## 8. Checklist review (wajib setiap halaman)

- [ ] Terlihat seperti produk profesional?
- [ ] Tidak terlihat generik seperti hasil AI?
- [ ] Produk menjadi fokus utama?
- [ ] Harga mudah ditemukan?
- [ ] Pengguna mudah mencari produk?
- [ ] Alur pembelian sederhana?
- [ ] Desain membangun kepercayaan?
- [ ] Mobile experience optimal?
- [ ] Tidak ada dekorasi yang tidak perlu?

## Prinsip utama

> **Less decoration, better product presentation, clear pricing, intuitive navigation,
> and frictionless shopping experience.**

Karakter: Clean, Modern, Premium, Trustworthy, Intuitive, Conversion-oriented, Consistent.
