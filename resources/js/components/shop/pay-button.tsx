import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/Shop/CheckoutController';
import { Button } from '@/components/ui/button';

export type PaymentConfig = { client_key: string | null; snap_js: string };

type SnapResult = Record<string, unknown>;

declare global {
    interface Window {
        snap?: {
            pay: (
                token: string,
                callbacks: {
                    onSuccess?: (r: SnapResult) => void;
                    onPending?: (r: SnapResult) => void;
                    onError?: (r: SnapResult) => void;
                    onClose?: () => void;
                },
            ) => void;
        };
    }
}

/**
 * Loads Midtrans snap.js once (with the PUBLIC client key), asks our server
 * for the payment token, and opens the Midtrans popup.
 *
 * The popup's "success" is only a hint for the UI. The real proof of
 * payment is the notification Midtrans sends to our server (next step):
 * anyone can call onSuccess from the browser console.
 */
export function PayButton({
    code,
    config,
}: {
    code: string;
    config: PaymentConfig;
}) {
    const [ready, setReady] = useState(() => Boolean(window.snap));
    const [busy, setBusy] = useState(false);
    const { errors } = usePage().props;

    useEffect(() => {
        if (window.snap || !config.client_key) {
            return;
        }

        const script = document.createElement('script');
        script.src = config.snap_js;
        script.dataset.clientKey = config.client_key;
        script.async = true;
        script.onload = () => setReady(true);
        document.body.appendChild(script);
    }, [config]);

    // The server answers "Bayar" with flash data { payment: { token } }.
    useEffect(() => {
        return router.on('flash', (event) => {
            const token = (event as CustomEvent).detail?.flash?.payment?.token;

            if (typeof token === 'string' && window.snap) {
                window.snap.pay(token, {
                    // Reload to show the latest status. It turns "Lunas"
                    // once our server has received Midtrans' notification.
                    onSuccess: () => router.reload(),
                    onPending: () => router.reload(),
                    onClose: () => setBusy(false),
                });
            }
        });
    }, []);

    if (!config.client_key) {
        return (
            <p className="text-sm text-muted-foreground">
                Pembayaran online belum dikonfigurasi.
            </p>
        );
    }

    return (
        <div className="grid gap-2">
            <Button
                size="lg"
                disabled={!ready || busy}
                onClick={() =>
                    router.post(
                        CheckoutController.pay.url(code),
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setBusy(true),
                            onFinish: () => setBusy(false),
                        },
                    )
                }
            >
                {ready ? 'Bayar sekarang' : 'Menyiapkan pembayaran…'}
            </Button>
            {errors.payment && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.payment}
                </p>
            )}
        </div>
    );
}
