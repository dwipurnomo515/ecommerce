import AppLogoIcon from '@/components/app-logo-icon';

/** Monogram + "zetago" wordmark. The sidebar shows only the monogram when collapsed. */
export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center">
                <AppLogoIcon className="size-7 fill-current text-sidebar-foreground" />
            </div>
            <span className="ml-1 truncate text-lg leading-none font-bold tracking-tight text-sidebar-foreground">
                zetago
            </span>
        </>
    );
}
