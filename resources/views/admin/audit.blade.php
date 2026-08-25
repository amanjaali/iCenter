@extends('layouts.app')
@section('title', 'Audit trail')
@section('subtitle', 'SCOPE OF WORK §9.3 · APPEND ONLY')

@section('content')
    <div class="mb-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
        <div class="leading-relaxed">
            Every entry is logged with the user, the date and the time. Nothing here can be edited or deleted,
            including by an administrator — corrections to the ledger are made by reversing entries, and the
            reversal is logged too.
        </div>
    </div>

    <form method="GET" class="card mb-4 grid gap-3 p-3.5 md:grid-cols-3 lg:grid-cols-6">
        <div class="md:col-span-2">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Description or reason">
        </div>
        <div>
            <label class="label" for="user_id">User</label>
            <select id="user_id" name="user_id" class="select">
                <option value="">All users</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="event">Event</label>
            <select id="event" name="event" class="select">
                <option value="">All events</option>
                @foreach ($events as $event)
                    <option value="{{ $event }}" @selected(request('event') === $event)>
                        {{ ucfirst(str_replace('_', ' ', $event)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="from">From</label>
            <input id="from" name="from" type="date" class="input" value="{{ request('from') }}">
        </div>
        <div>
            <label class="label" for="to">To</label>
            <input id="to" name="to" type="date" class="input" value="{{ request('to') }}">
        </div>
        <div class="flex items-end gap-2 md:col-span-3">
            <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Filter</button>
            <a href="{{ route('audit.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-compact">
                <thead>
                    <tr><th>When</th><th>User</th><th>Event</th><th>Subject</th><th>Description</th><th>Reason</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr x-data="{ open: false }">
                            <td class="whitespace-nowrap text-muted">{{ $log->created_at?->format('j M Y H:i:s') }}</td>
                            <td>{{ $log->user?->name ?? $log->user_name ?? 'System' }}</td>
                            <td>
                                <x-badge :label="ucfirst(str_replace('_', ' ', $log->event))"
                                         :tone="match(true) {
                                            str_contains($log->event, 'reversed'), str_contains($log->event, 'void'),
                                            str_contains($log->event, 'deleted') => 'danger',
                                            str_contains($log->event, 'posted') => 'success',
                                            str_contains($log->event, 'changed') => 'warning',
                                            default => 'neutral',
                                         }"/>
                            </td>
                            <td class="text-muted">{{ $log->subjectLabel() }}</td>
                            <td>
                                <button type="button" @click="open = !open" class="text-start hover:text-signal">
                                    {{ $log->description ?: '—' }}
                                </button>
                                <template x-if="open">
                                    <div class="mt-2 rounded-md bg-mist p-2.5 font-mono text-[10px] leading-relaxed">
                                        @if ($log->old_values)
                                            <div class="text-negative">− {{ json_encode($log->old_values) }}</div>
                                        @endif
                                        @if ($log->new_values)
                                            <div class="text-positive">+ {{ json_encode($log->new_values) }}</div>
                                        @endif
                                        @if ($log->ip_address)
                                            <div class="mt-1 text-faint">{{ $log->ip_address }}</div>
                                        @endif
                                    </div>
                                </template>
                            </td>
                            <td class="max-w-[240px] text-muted">{{ $log->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="No audit entries match this view"/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="border-t border-rule px-5 py-3">{{ $logs->links() }}</div>@endif
    </div>
@endsection
