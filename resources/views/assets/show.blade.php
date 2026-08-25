@extends('layouts.app')
@section('title', $asset->name)
@section('subtitle', $asset->code.' · '.strtoupper($asset->category))

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            <div class="mb-0 grid gap-3.5 sm:grid-cols-3">
                <x-kpi label="Cost" :value="\App\Support\Money::format($asset->cost)" accent="#0A1F3D"
                       :delta="'Acquired '.$asset->acquisition_date->format('j M Y')"/>
                <x-kpi label="Accumulated depreciation" :value="\App\Support\Money::format($asset->accumulated_depreciation)"
                       accent="#98A2B3" :delta="$asset->useful_life_months.' month life'"/>
                <x-kpi label="Net book value" :value="\App\Support\Money::format($asset->netBookValue())" accent="#00C2E8"
                       :delta="'Salvage '.\App\Support\Money::format($asset->salvage_value)"/>
            </div>

            @php
                $depreciated = (float) $asset->cost > 0
                    ? min(100, round((float) $asset->accumulated_depreciation / max(0.01, $asset->depreciableAmount()) * 100, 1))
                    : 0;
            @endphp
            <div class="card card-pad">
                <div class="mb-2 flex items-center justify-between text-xs">
                    <span class="text-muted">Depreciated</span>
                    <span class="money">{{ $depreciated }}%</span>
                </div>
                <div class="h-[6px] rounded-sm bg-rule">
                    <div class="h-[6px] rounded-sm bg-signal" style="width: {{ $depreciated }}%"></div>
                </div>
                <div class="mt-3 text-[11px] leading-relaxed text-faint">
                    Straight line at {{ \App\Support\Money::format($asset->monthlyCharge(), true) }} a month,
                    charged to {{ $asset->depreciationExpenseAccount->displayName() }}.
                    The final month absorbs the rounding so the asset never depreciates past its salvage value.
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-head"><div class="card-title">Depreciation schedule to date</div></div>
                <table class="table table-compact">
                    <thead>
                        <tr><th>Month</th><th>Run</th><th class="num">Charge</th><th class="num">Accumulated</th><th class="num">Net book value</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($asset->depreciationLines->sortByDesc(fn ($l) => $l->run->run_month) as $line)
                            <tr>
                                <td>{{ $line->run->run_month->format('F Y') }}</td>
                                <td>
                                    <a href="{{ route('assets.run', $line->run) }}" class="money text-xs hover:text-signal">
                                        {{ $line->run->reference }}
                                    </a>
                                </td>
                                <td class="num"><x-money :value="$line->amount"/></td>
                                <td class="num"><x-money :value="$line->accumulated_after" muted/></td>
                                <td class="num"><x-money :value="$line->net_book_value_after"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty title="Not yet depreciated"
                                message="Run depreciation for a month to start the schedule."/></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-3">Asset</div>
                <dl class="flex flex-col gap-2.5 text-xs">
                    @foreach ([
                        'Code' => $asset->code,
                        'Category' => ucfirst($asset->category),
                        'Asset account' => $asset->assetAccount->displayName(),
                        'Depreciation to' => $asset->depreciationExpenseAccount->displayName(),
                        'Cost centre' => $asset->department?->name,
                        'Project' => $asset->project?->name,
                        'Acquired' => $asset->acquisition_date->format('j M Y'),
                        'Depreciation starts' => $asset->depreciation_start_date->format('j M Y'),
                        'Useful life' => $asset->useful_life_months.' months',
                        'Serial number' => $asset->serial_number,
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-faint">{{ $label }}</dt>
                            <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @can('manage-assets')
                @if ($asset->status !== 'disposed')
                    <form method="POST" action="{{ route('assets.dispose', $asset) }}" class="card">
                        @csrf
                        <div class="card-head">
                            <div>
                                <div class="card-title">Dispose of this asset</div>
                                <div class="card-sub">
                                    Clears the cost and accumulated depreciation, and books the gain or loss.
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 p-5">
                            <div>
                                <label class="label" for="disposal_date">Date</label>
                                <input id="disposal_date" name="disposal_date" type="date" class="input" required value="{{ now()->toDateString() }}">
                            </div>
                            <div>
                                <label class="label" for="proceeds">Proceeds</label>
                                <input id="proceeds" name="proceeds" type="number" step="1" min="0" class="input input-num" required value="0">
                            </div>
                            <div>
                                <label class="label" for="proceeds_account_id">Received into</label>
                                <select id="proceeds_account_id" name="proceeds_account_id" class="select" required>
                                    @foreach ($cashAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="reason">Reason</label>
                                <textarea id="reason" name="reason" rows="2" class="textarea" required></textarea>
                            </div>
                            <button class="btn btn-danger w-full justify-center">Dispose of asset</button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection
