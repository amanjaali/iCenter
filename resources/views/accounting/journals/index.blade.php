@extends('layouts.app')
@section('title', 'Journals')
@section('subtitle', 'THE GENERAL LEDGER')

@section('actions')
    @can('post-entries')
        <a href="{{ route('journals.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> New journal
        </a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[180px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Reference or memo">
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All</option>
                @foreach (\App\Enums\JournalStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[180px]">
            <label class="label" for="source">Source</label>
            <select id="source" name="source" class="select">
                <option value="">All sources</option>
                @foreach ($sources as $source)
                    <option value="{{ $source->value }}" @selected(request('source') === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="from">From</label>
            <input id="from" name="from" type="date" class="input" value="{{ request('from') }}">
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="to">To</label>
            <input id="to" name="to" type="date" class="input" value="{{ request('to') }}">
        </div>
        <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Filter</button>
        <a href="{{ route('journals.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Memo</th>
                        <th>Source</th>
                        <th>Period</th>
                        <th class="num">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($journals as $journal)
                        <tr>
                            <td>
                                <a href="{{ route('journals.show', $journal) }}" class="money text-xs hover:text-signal">
                                    {{ $journal->reference }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap text-muted">{{ $journal->journal_date->format('j M Y') }}</td>
                            <td class="max-w-[380px] truncate" title="{{ $journal->memo }}">{{ $journal->memo }}</td>
                            <td>
                                <span class="badge {{ $journal->source_type->isAutomatic() ? 'badge-neutral' : 'badge-info' }}">
                                    {{ $journal->source_type->label() }}
                                </span>
                            </td>
                            <td class="text-muted"><span class="money">{{ $journal->period?->code }}</span></td>
                            <td class="num"><x-money :value="$journal->total_debit"/></td>
                            <td><x-badge :status="$journal->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No journals match this view"
                            message="Every financial event in the system lands here as a balanced double entry."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($journals->hasPages())<div class="border-t border-rule px-5 py-3">{{ $journals->links() }}</div>@endif
    </div>
@endsection
