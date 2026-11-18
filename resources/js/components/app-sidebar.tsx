import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    FolderTree,
    Inbox,
    LayoutGrid,
    MessageSquare,
    Package,
    ShieldAlert,
    ShoppingBag,
    Store,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as adminCategories } from '@/routes/admin/categories';
import { index as adminProducts } from '@/routes/admin/products';
import { index as adminStores } from '@/routes/admin/stores';
import { index as buyerOrders } from '@/routes/orders';
import { index as sellerBalance } from '@/routes/seller/balance';
import { index as sellerOrders } from '@/routes/seller/orders';
import { index as sellerProducts } from '@/routes/seller/products';
import { index as sellerReviews } from '@/routes/seller/reviews';
import { show as sellerStore } from '@/routes/seller/store';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Pesanan saya',
        href: buyerOrders(),
        icon: ShoppingBag,
    },
    {
        // Opens the store profile, or the "open a store" form if none yet
        title: 'Toko saya',
        href: sellerStore(),
        icon: Store,
    },
    {
        title: 'Produk',
        href: sellerProducts(),
        icon: Package,
    },
    {
        title: 'Pesanan masuk',
        href: sellerOrders(),
        icon: Inbox,
    },
    {
        title: 'Ulasan',
        href: sellerReviews(),
        icon: MessageSquare,
    },
    {
        title: 'Saldo',
        href: sellerBalance(),
        icon: Wallet,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Kategori',
        href: adminCategories(),
        icon: FolderTree,
    },
    {
        title: 'Toko',
        href: adminStores(),
        icon: Store,
    },
    {
        title: 'Produk',
        href: adminProducts(),
        icon: ShieldAlert,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    // Only hides the menu. The routes themselves are protected by `can:admin`.
    const isAdmin = usePage().props.auth.user?.is_admin === true;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {isAdmin && <NavMain items={adminNavItems} label="Admin" />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
