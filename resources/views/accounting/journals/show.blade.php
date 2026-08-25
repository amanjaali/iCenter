@extends('layouts.app')
@section('title', 'Journal '.$journal->reference)
@section('subtitle', strtoupper($journal->journal_date->format('j M Y').' · '.$journal->source_type->label()))

@section('actions')
    @can('post-entries')
        @if ($journal->isDraft())
            <form method="POST" action="{{ route('journals.destroy', $journal) }}"
                  data-confirm="Delete this draft journal? It has not reached the ledger.">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Delete draft</button>
            </form>
            <x-confirm-form :action="route('journals.post', $journal)" class="btn btn-primary"
                confirm="Posting commits this entry to the ledger. From then on it can only be corrected by reversal (§9.3).">
                Post journal
            </x-confirm-form>
        @elseif ($journal->canBeReversed())
            <x-confirm-form :action="route('journals.reverse', $journal)" reason
                reason-label="Why is this entry being reversed?"
                confirm="A reversing entry will be created with the debits and credits swapped. Both journals stay on the record — nothing is deleted."
                class="btn btn-secondary">
                <x-icon name="undo" class="size-3.5"/> Reverse
            </x-confirm-form>
        @endif
    @endcan
@endsection

@section('content')
    @if ($journal->isReversed())
        <div class="mb-4 flex items-start gap-2.5 rounded-md border border-negative/25 bg-negative-soft px-4 py-3 text-xs text-negative">
            <x-icon name="undo" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                This entry was reversed by
                <a href="{{ route('journals.show', $journal->reversedBy) }}" class="font-medium underline">{{ $journal->reversedBy?->reference }}</a>.
                Reason: {{ $journal->reversal_reason }}
            </div>
        </div>
    @endif

    @if ($journal->reversalOf)
        <div class="mb-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
            <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
            <div class="leading-relaxed">
                This is a reversing entry for
                <a href="{{ route('journals.show', $journal->reversalOf) }}" class="font-medium text-signal">{{ $journal->reversalOf->reference }}</a>.
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1fr_300px]">
        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">{{ $journal->memo }}</div>
                    <div class="card-sub">{{ $journal->lines->count() }} lines · period {{ $journal->period?->code }}</div>
                </div>
                <x-badge :status="$journal->status"/>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Description</th>
                            <th>Cost centre</th>
                            <th>Project</th>
                            <th class="num">Debit</th>
                            <th class="num">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($journal->lines as $line)
                            <tr>
                                <td>
                                    <a href="{{ route('accounts.show', $line->account) }}" class="hover:text-signal">
                                        <span class="money">{{ $line->account->code }}</span>
                                        <div class="text-[11px] text-muted">{{ $line->account->name }}</div>
                                    </a>
                                </td>
                                <td class="text-muted">
                                    {{ $line->description }}
                                    @if ($line->busCompany)
                                        <div class="eyebrow">{{ $line->busCompany->name }}</div>
                                    @elseif ($line->partner)
                                        <div class="eyebrow">{{ $line->partner->name }}</div>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $line->department?->name ?? '—' }}</td>
                                <td class="text-muted">{{ $line->project?->name ?? '—' }}</td>
                                <td class="num">{{ (float) $line->debit > 0 ? \App\Support\Money::format($line->debit) : '' }}</td>
                                <td class="num">{{ (float) $line->credit > 0 ? \App\Support\Money::format($line->credit) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">Totals</td>
                            <td class="num"><x-money :value="$journal->total_debit"/></td>
                            <td class="num"><x-money :value="$journal->total_credit"/></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-3">Entry</div>
                <dl class="flex flex-col gap-2.5 text-xs">
                    @foreach ([
                        'Reference' => $journal->reference,
                        'Date' => $journal->journal_date->format('j M Y'),
                        'Period' => $journal->period?->code,
                        'Source' => $journal->source_type->label(),
                        'Created by' => $journal->createdBy?->name ?? 'System',
                        'Posted by' => $journal->postedBy?->name,
                        'Posted at' => $journal->posted_at?->format('j M Y H:i'),
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-faint">{{ $label }}</dt>
                            <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @if ($source)
                <div class="card card-pad">
                    <div class="eyebrow mb-3">Source document</div>
                    <p class="mb-3 text-xs leading-relaxed text-muted">
                        This journal was produced automatically by the document below and belongs to it.
                    </p>
                    <a href="{{ match (true) {
                            $source instanceof \App\Models\Invoice => route('invoices.show', $source),
                            $source instanceof \App\Models\Payment => route('payments.show', $source),
                            $source instanceof \App\Models\Expense => route('expenses.show', $source),
                            $source instanceof \App\Models\PayrollRun => route('payroll.show', $source),
                            $source instanceof \App\Models\DepreciationRun => route('assets.run', $source),
                            $source instanceof \App\Models\RevenueShareRun => route('revenue-share.show', $source),
                            $source instanceof \App\Models\DistributionRun => route('distributions.show', $source),
                            default => '#',
                        } }}" class="btn btn-secondary btn-sm w-full justify-between">
                        <span>{{ class_basename($source) }}</span>
                        <span class="money">{{ $source->number ?? $source->reference ?? '#'.$source->id }}</span>
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
