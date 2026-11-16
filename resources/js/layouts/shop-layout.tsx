import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SearchForm } from '@/components/shop/search-form';
import { buttonVariants } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

/**
 * Public storefront frame: a quiet header so the product stays the focus.
 * Search sits in the header on desktop and on its own full-width row on
 * phones, where it is the main way to find things.
 */
export default function ShopLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const { url } = usePage();
    // The layout stays mounted between pages; re-mount the search box when
    // the query in the URL changes so it shows the current search.
    const searchKey = new URL(url, 'http://x').searchParams.get('q') ?? '';

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <header className="border-b border-border">
                <div className="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-4 px-4 md:gap-8 md:px-6">
                    <Link
                        href={home()}
                        className="flex shrink-0 items-center gap-1.5"
                        aria-label="Zetago, ke beranda"
                    >
                        <AppLogoIcon className="size-7 fill-current" />
                        <span className="text-lg font-bold tracking-tight">
                            zetago
                        </span>
                    </Link>

                    <SearchForm
                        key={`desktop-${searchKey}`}
                        className="hidden max-w-xl flex-1 md:block"
                    />

                    <nav className="flex shrink-0 items-center gap-1">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className={buttonVariants({
                                    variant: 'ghost',
                                    size: 'sm',
                                })}
                            >
                                Akun saya
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className={buttonVariants({
                                        variant: 'ghost',
                                        size: 'sm',
                                    })}
                                >
                                    Masuk
                                </Link>
                                <Link
                                    href={register()}
                                    className={buttonVariants({
                                        variant: 'outline',
                                        size: 'sm',
                                    })}
                                >
                                    Daftar
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
                <SearchForm
                    key={`mobile-${searchKey}`}
                    className="px-4 pb-3 md:hidden"
                />
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-border">
                <div className="mx-auto w-full max-w-6xl px-4 py-6 text-xs text-muted-foreground md:px-6">
                    © {new Date().getFullYear()} Zetago
                </div>
            </footer>
        </div>
    );
}
