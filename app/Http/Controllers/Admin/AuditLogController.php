<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /** Tabs: action prefixes. */
    private const GROUPS = [
        'semua' => 'Semua',
        'product' => 'Produk',
        'store' => 'Toko',
        'order' => 'Pesanan',
        'payout' => 'Penarikan',
        'refund' => 'Refund',
        'user' => 'Admin',
    ];

    public function index(Request $request): Response
    {
        $group = $request->string('grup')->value();
        $group = array_key_exists($group, self::GROUPS) ? $group : 'semua';

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($group !== 'semua', fn ($q) => $q->where('action', 'like', "{$group}.%"))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'label' => AuditLog::LABELS[$log->action] ?? $log->action,
                'actor' => $log->actor ? "{$log->actor->name} ({$log->actor->email})" : 'Sistem',
                'subject' => $log->subject_label,
                'details' => $log->details,
                'ip' => $log->ip,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/audit/index', [
            'logs' => $logs,
            'group' => $group,
            'groups' => collect(self::GROUPS)->map(fn (string $label, string $value) => compact('value', 'label'))->values(),
        ]);
    }
}
