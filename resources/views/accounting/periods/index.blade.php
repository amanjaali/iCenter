@extends('layouts.app')
@section('title', 'Accounting periods')
@section('subtitle', 'A CLOSED MONTH REFUSES NEW POSTINGS')

@section('actions')
    @can('close-periods')
        <form method="POST" action="{{ route('periods.create-year') }}" class="flex items-center gap-2">
            @csrf
            <input name="year" type="number" min="2020" max="2100" class="input input-num w-[110px]"
                   value="{{ now()->addYear()->year }}" required>
            <button class="btn btn-secondary"><x-icon name="plus" class="size-3.5"/> Create fiscal year</button>
        </form>
    @endcan
@endsection

@section('content')
    @foreach ($years as $year)
        <div class="card mb-4 overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">Fiscal year {{ $year->name }}</div>
                    <div class="card-sub">
                        {{ $year->start_date->format('j M Y') }} – {{ $year->end_date->format('j M Y') }}
                    </div>
                </div>
                @if ($year->is_closed)
                    <span class="badge badge-neutral">Closed</span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Dates</th>
                            <th class="num">Journals</th>
                            <th class="num">Posted value</th>
                            <th>Drafts</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($year->periods as $period)
                            @php
                                $draftCount = $drafts[$period->id] ?? 0;
                                $posted = $postings[$period->id] ?? null;
                            @endphp
                            <tr>
                                <td class="font-medium">{{ $period->label() }}</td>
                                <td class="whitespace-nowrap text-muted">
                                    {{ $period->start_date->format('j M') }} – {{ $period->end_date->format('j M Y') }}
                                </td>
                                <td class="num">{{ number_format($posted->total ?? 0) }}</td>
                                <td class="num">
                                    <x-money :value="$posted->amount ?? 0" muted/>
                                </td>
                                <td>
                                    @if ($draftCount > 0)
                                        <a href="{{ route('journals.index', ['status' => 'draft']) }}" class="badge badge-warning">
                                            {{ $draftCount }} draft
                                        </a>
                                    @else
                                        <span class="text-faint">—</span>
                                    @endif
                                </td>
                                <td><x-badge :status="$period->status"/></td>
                                <td class="text-end">
                                    @can('close-periods')
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($period->isOpen())
                                                <x-confirm-form :action="route('periods.close', $period)"
                                                    :confirm="'Closing '.$period->label().' stops any further posting into that month. It can be reopened by an accountant or administrator, with a reason.'"
                                                    class="btn btn-secondary btn-sm">
                                                    Close
                                                </x-confirm-form>
                                            @elseif ($period->status === \App\Enums\PeriodStatus::Closed)
                                                <x-confirm-form :action="route('periods.reopen', $period)" reason
                                                    reason-label="Why is this period being reopened?"
                                                    :confirm="'Reopening '.$period->label().' allows postings into a month that was already closed. The reason is recorded in the audit trail.'"
                                                    class="btn btn-secondary btn-sm">
                                                    Reopen
                                                </x-confirm-form>
                                                <x-confirm-form :action="route('periods.lock', $period)"
                                                    :confirm="'Locking '.$period->label().' is permanent — it can never be reopened. Use this once the year has been audited.'"
                                                    class="btn btn-danger btn-sm">
                                                    Lock
                                                </x-confirm-form>
                                            @endif
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
