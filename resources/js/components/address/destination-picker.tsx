import { Check, Loader2, MapPin } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { search } from '@/routes/destinations';

export type DestinationChoice = { label: string; token: string };

type Props = {
    /** Label of the region chosen so far (current address when editing). */
    value: string | null;
    onChange: (choice: DestinationChoice) => void;
    invalid?: boolean;
    inputId?: string;
};

const MIN_CHARS = 3;
const DEBOUNCE_MS = 350;

/**
 * Type "kebayoran" or "12120", pick a suggestion. Suggestions come from
 * GET /wilayah (RajaOngkir, cached on our server).
 *
 * - Debounced: waits until typing pauses, instead of one request per key.
 * - Each new search aborts the previous one, so a slow old answer can
 *   never overwrite a newer one ("race" between responses).
 * - Keyboard: ↑ ↓ to move, Enter to choose, Esc to close.
 */
export function DestinationPicker({
    value,
    onChange,
    invalid,
    inputId,
}: Props) {
    const listId = useId();
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [results, setResults] = useState<DestinationChoice[]>([]);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const abort = useRef<AbortController | null>(null);

    useEffect(() => {
        const q = query.trim();

        if (q.length < MIN_CHARS) {
            return;
        }

        const timer = setTimeout(async () => {
            abort.current?.abort();
            const controller = new AbortController();
            abort.current = controller;
            setLoading(true);
            setMessage(null);

            try {
                const response = await fetch(search.url({ query: { q } }), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                const body = await response.json();

                if (!response.ok) {
                    setResults([]);
                    setMessage(
                        response.status === 429
                            ? 'Terlalu banyak pencarian. Tunggu sebentar.'
                            : (body.message ?? 'Pencarian gagal.'),
                    );

                    return;
                }

                setResults(body.data);
                setActive(0);
                setMessage(
                    body.data.length === 0
                        ? `Tidak ada wilayah untuk "${q}". Coba nama kecamatan atau kode pos.`
                        : null,
                );
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    setMessage('Tidak bisa terhubung. Periksa koneksi Anda.');
                }
            } finally {
                if (abort.current === controller) {
                    setLoading(false);
                }
            }
        }, DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [query]);

    const choose = (choice: DestinationChoice) => {
        onChange(choice);
        setQuery('');
        setResults([]);
        setOpen(false);
    };

    const showList =
        open &&
        query.trim().length >= MIN_CHARS &&
        (results.length > 0 || message);

    return (
        <div className="grid gap-2">
            {value && (
                <p className="flex items-start gap-2 rounded-md bg-muted/60 px-3 py-2 text-sm">
                    <MapPin
                        className="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden
                    />
                    <span>{value}</span>
                </p>
            )}

            <div className="relative">
                <input
                    id={inputId}
                    type="text"
                    role="combobox"
                    aria-expanded={Boolean(showList)}
                    aria-controls={listId}
                    aria-autocomplete="list"
                    aria-activedescendant={
                        showList && results[active]
                            ? `${listId}-${active}`
                            : undefined
                    }
                    aria-invalid={invalid}
                    autoComplete="off"
                    value={query}
                    placeholder={
                        value
                            ? 'Ganti wilayah: ketik kecamatan atau kode pos'
                            : 'Ketik kecamatan, kelurahan, atau kode pos'
                    }
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    // Delay so a click on an option lands before closing.
                    onBlur={() => setTimeout(() => setOpen(false), 150)}
                    onKeyDown={(e) => {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            setActive((i) =>
                                Math.min(i + 1, results.length - 1),
                            );
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            setActive((i) => Math.max(i - 1, 0));
                        } else if (
                            e.key === 'Enter' &&
                            showList &&
                            results[active]
                        ) {
                            // Don't submit the whole form.
                            e.preventDefault();
                            choose(results[active]);
                        } else if (e.key === 'Escape') {
                            setOpen(false);
                        }
                    }}
                    className="h-10 w-full rounded-md border border-input bg-transparent px-3 pr-9 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                />
                {loading && (
                    <Loader2
                        className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                        aria-label="Mencari"
                    />
                )}

                {showList && (
                    <ul
                        id={listId}
                        role="listbox"
                        className="absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-md"
                    >
                        {message && (
                            <li className="px-3 py-2 text-sm text-muted-foreground">
                                {message}
                            </li>
                        )}
                        {results.map((r, i) => (
                            <li
                                key={r.token}
                                id={`${listId}-${i}`}
                                role="option"
                                aria-selected={i === active}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => choose(r)}
                                onMouseEnter={() => setActive(i)}
                                className={cn(
                                    'flex cursor-pointer items-start gap-2 rounded-sm px-3 py-2 text-sm',
                                    i === active && 'bg-muted',
                                )}
                            >
                                <Check
                                    className={cn(
                                        'mt-0.5 size-4 shrink-0',
                                        r.label === value
                                            ? 'text-primary'
                                            : 'invisible',
                                    )}
                                    aria-hidden
                                />
                                {r.label}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
