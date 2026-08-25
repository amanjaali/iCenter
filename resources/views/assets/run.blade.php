@extends('layouts.app')
@section('title', 'Depreciation '.$run->run_month->format('F Y'))
@section('subtitle', $run->reference.' · '.strtoupper($run->status))

@section('actions')
    @can('manage-assets')
        @if ($run->isDraft())
            <x-confirm-form :action="route('assets.post-run', $run)" class="btn btn-primary"
                confirm="Posting charges depreciation to 8050, 9060 and 9070 as appropriate, credits accumulated depreciation (1190), and rolls each asset's accumulated figure forward.">
                Post depreciation
            </x-confirm-form>
        @endif
    @endcan
@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Charge for {{ $run->run_month->format('F Y') }}</div>
                <div class="card-sub">{{ $run->lines->count() }} assets · period {{ $run->period?->code }}</div>
            </div>
            @if ($run->journal)
                <a href="{{ route('journals.show', $run->journal) }}" class="money text-xs text-muted hover:text-signal">
                    {{ $run->journal->reference }}
                </a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset</th><th>Charged to</th><th class="num">Charge</th>
                        <th class="num">Accumulated after</th><th class="num">Net book value after</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($run->lines as $line)
                        <tr>
                            <td>
                                <a href="{{ route('assets.show', $line->asset) }}" class="hover:text-signal">{{ $line->asset->name }}</a>
                                <div class="eyebrow">{{ $line->asset->code }}</div>
                            </td>
                            <td class="text-muted">{{ $line->asset->depreciationExpenseAccount->displayName() }}</td>
                            <td class="num"><x-money :value="$line->amount"/></td>
                            <td class="num"><x-money :value="$line->accumulated_after" muted/></td>
                            <td class="num"><x-money :value="$line->net_book_value_after"/></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total charge</td>
                        <td class="num"><x-money :value="$run->total_amount"/></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
