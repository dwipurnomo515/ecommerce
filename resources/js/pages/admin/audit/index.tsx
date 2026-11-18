import { Head, Link } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import Heading from '@/components/heading';
import { SimplePagination } from '@/components/simple-pagination';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/audit';
import type { Paginated } from '@/types/pagination';

type Log = {
    id: number;
    action: string;
    label: string;
    actor: string;
    subject: string;
    details: Record<string, string | number | null> | null;
    ip: string | null;
    created_at: string;
};

type Props = {
    logs: Paginated<Log>;
    group: string;
    groups: { value: string; label: string }[];
};

const detailNames: Record<string, string> = {
    reason: 'Alasan',
    previous_reason: 'Alasan blokir sebelumnya',
    from: 'Dari',
    to: 'Ke',
    amount: 'Jumlah',
    refund: 'Refund',
    reference: 'Referensi',
};

const moneyKeys = new Set(['amount', 'refund']);

const timeFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export default function AuditLogs({ logs, group, groups }: Props) {
    return (
        <>
            <Head title="Audit" />

            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <Heading
                    title="Audit"
                    description="Catatan aksi penting: siapa melakukan apa dan kapan. Catatan tidak bisa diubah atau dihapus."
                />

                <nav
                    aria-label="Jenis aksi"
                    className="-mb-2 flex gap-1 overflow-x-auto border-b border-border"
                >
                    {groups.map((g) => (
                        <Link
                            key={g.value}
                            href={index({ query: { grup: g.value } })}
                            preserveScroll
                            aria-current={
                                g.value === group ? 'page' : undefined
                            }
                            className={cn(
                                '-mb-px shrink-0 border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                                g.value === group
                                    ? 'border-primary font-semibold text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {g.label}
                        </Link>
                    ))}
                </nav>

                {logs.data.length === 0 ? (
                    <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-6 py-14 text-center">
                        <ScrollText
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="text-sm text-muted-foreground">
                            Belum ada catatan.
                        </p>
                    </div>
                ) : (
                    <ol className="divide-y divide-border rounded-lg border border-border bg-card">
                        {logs.data.map((log) => (
                            <li
                                key={log.id}
                                className="grid gap-1.5 p-4 text-sm"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <p>
                                        <span className="font-semibold">
                                            {log.label}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {log.subject}
                                        </span>
                                    </p>
                                    <time
                                        dateTime={log.created_at}
                                        className="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {timeFormat.format(
                                            new Date(log.created_at),
                                        )}
                                    </time>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    oleh {log.actor}
                                    {log.ip && ` · IP ${log.ip}`}
                                </p>
                                {log.details && (
                                    <dl className="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs">
                                        {Object.entries(log.details)
                                            .filter(
                                                ([, v]) =>
                                                    v !== null && v !== '',
                                            )
                                            .map(([key, value]) => (
                                                <div
                                                    key={key}
                                                    className="contents"
                                                >
                                                    <dt className="text-muted-foreground">
                                                        {detailNames[key] ??
                                                            key}
                                                    </dt>
                                                    <dd className="min-w-0 break-words">
                                                        {moneyKeys.has(key) &&
                                                        typeof value ===
                                                            'number'
                                                            ? formatRupiah(
                                                                  value,
                                                              )
                                                            : String(value)}
                                                    </dd>
                                                </div>
                                            ))}
                                    </dl>
                                )}
                            </li>
                        ))}
                    </ol>
                )}

                <SimplePagination page={logs} />
            </div>
        </>
    );
}

AuditLogs.layout = {
    breadcrumbs: [{ title: 'Audit', href: index() }],
};
