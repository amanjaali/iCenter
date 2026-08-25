<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Scope of work §9.3 — the audit trail, readable but never editable. */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-audit-trail');

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->string('event')->toString(), fn ($q, $e) => $q->where('event', $e))
            ->when($request->string('type')->toString(), fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('description', 'like', "%{$term}%")->orWhere('reason', 'like', "%{$term}%")
            ))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'events' => AuditLog::distinct()->orderBy('event')->pluck('event'),
            'types' => AuditLog::whereNotNull('auditable_type')->distinct()->pluck('auditable_type'),
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
