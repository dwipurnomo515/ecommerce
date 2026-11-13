import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import { Price } from '@/components/shop/price';
import { ProductCard } from '@/components/shop/product-card';
import type { ProductCardData } from '@/components/shop/product-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/*
 * Living style guide (local development only, see routes/web.php).
 * Everything below is SAMPLE data, labelled "Contoh" on purpose.
 */

const sampleProducts: ProductCardData[] = [
    {
        name: 'Tas Kanvas Contoh Ukuran Sedang, Warna Natural',
        href: '#',
        imageUrl: null,
        price: 249_000,
        compareAtPrice: 299_000,
        inStock: true,
        store: { name: 'Toko Contoh', city: 'Bandung' },
        rating: { average: 4.8, count: 120 },
    },
    {
        name: 'Sepatu Contoh Low-top Kulit Sintetis',
        href: '#',
        imageUrl: null,
        price: 489_000,
        inStock: true,
        store: { name: 'Toko Contoh', city: 'Surabaya' },
        rating: null,
    },
    {
        name: 'Mug Keramik Contoh 350 ml',
        href: '#',
        imageUrl: null,
        price: 65_000,
        inStock: false,
        store: { name: 'Toko Contoh', city: 'Yogyakarta' },
        rating: { average: 4.9, count: 37 },
    },
    {
        name: 'Nama Produk Contoh yang Sangat Panjang Sekali untuk Menguji Pemotongan Teks pada Dua Baris',
        href: '#',
        imageUrl: null,
        price: 1_250_000,
        compareAtPrice: 1_500_000,
        inStock: true,
        store: { name: 'Toko Contoh dengan Nama Panjang', city: 'Makassar' },
    },
];

const colorTokens = [
    { name: 'primary', className: 'bg-primary', use: 'CTA, link aktif' },
    {
        name: 'primary-soft',
        className: 'bg-primary-soft',
        use: 'Kategori aktif',
    },
    { name: 'foreground', className: 'bg-foreground', use: 'Teks utama' },
    { name: 'muted', className: 'bg-muted', use: 'Latar gambar, hover' },
    { name: 'border', className: 'bg-border', use: 'Garis pemisah' },
    { name: 'sale', className: 'bg-sale', use: 'Harga diskon' },
    { name: 'success', className: 'bg-success', use: 'Status berhasil' },
    { name: 'warning', className: 'bg-warning', use: 'Status menunggu' },
];

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-5 border-t border-border pt-8">
            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                {title}
            </h2>
            {children}
        </section>
    );
}

export default function DesignSystem() {
    return (
        <>
            <Head title="Design system" />
            <div className="mx-auto grid max-w-5xl gap-10 px-4 py-10 sm:px-6">
                <header className="grid gap-3">
                    <div className="flex items-center gap-1">
                        <AppLogo />
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight text-balance">
                        Design system
                    </h1>
                    <p className="max-w-prose text-muted-foreground">
                        Semua komponen dasar Zetago di satu halaman. Halaman ini
                        hanya ada di development. Semua produk, toko, harga, dan
                        rating di bawah adalah{' '}
                        <strong className="text-foreground">data contoh</strong>
                        .
                    </p>
                </header>

                <Section title="Logo">
                    <div className="flex flex-wrap items-center gap-8">
                        <AppLogoIcon className="size-12 fill-current text-foreground" />
                        <div className="flex items-center gap-1">
                            <AppLogo />
                        </div>
                        <div className="rounded-lg bg-foreground p-3">
                            <AppLogoIcon className="size-10 fill-current text-background" />
                        </div>
                    </div>
                </Section>

                <Section title="Warna">
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {colorTokens.map((token) => (
                            <div key={token.name} className="grid gap-2">
                                <div
                                    className={`h-14 rounded-lg border border-border ${token.className}`}
                                />
                                <div className="text-sm">
                                    <div className="font-semibold">
                                        {token.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {token.use}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Section>

                <Section title="Tipografi · Plus Jakarta Sans">
                    <div className="grid gap-3">
                        <p className="text-3xl font-bold tracking-tight">
                            Judul halaman · 30/700
                        </p>
                        <p className="text-xl font-semibold">
                            Judul section · 20/600
                        </p>
                        <p className="text-base">
                            Teks isi, deskripsi produk · 16/400
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Teks pendukung, nama toko · 14/400
                        </p>
                        <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Label · 12/600
                        </p>
                    </div>
                </Section>

                <Section title="Tombol">
                    <div className="flex flex-wrap gap-3">
                        <Button>Beli sekarang</Button>
                        <Button variant="outline">Tambah ke keranjang</Button>
                        <Button variant="secondary">Sekunder</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="link">Tautan</Button>
                        <Button variant="destructive">Hapus</Button>
                        <Button disabled>Nonaktif</Button>
                    </div>
                </Section>

                <Section title="Input">
                    <div className="grid max-w-md gap-3">
                        <Input
                            id="ds-search"
                            placeholder="Cari produk, toko, atau kategori"
                        />
                    </div>
                </Section>

                <Section title="Harga">
                    <div className="flex flex-wrap items-end gap-x-12 gap-y-6">
                        <div className="grid gap-1">
                            <span className="text-xs text-muted-foreground">
                                sm · normal
                            </span>
                            <Price amount={489_000} />
                        </div>
                        <div className="grid gap-1">
                            <span className="text-xs text-muted-foreground">
                                sm · diskon
                            </span>
                            <Price amount={249_000} compareAt={299_000} />
                        </div>
                        <div className="grid gap-1">
                            <span className="text-xs text-muted-foreground">
                                lg · halaman produk
                            </span>
                            <Price
                                amount={249_000}
                                compareAt={299_000}
                                size="lg"
                            />
                        </div>
                    </div>
                </Section>

                <Section title="Product card">
                    <p className="-mt-2 text-sm text-muted-foreground">
                        Kasus yang diuji: diskon + rating, tanpa rating, stok
                        habis, dan nama/toko yang sangat panjang. Kecilkan
                        jendela browser untuk melihat grid mobile.
                    </p>
                    <div className="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">
                        {sampleProducts.map((product) => (
                            <ProductCard key={product.name} product={product} />
                        ))}
                    </div>
                </Section>
            </div>
        </>
    );
}
