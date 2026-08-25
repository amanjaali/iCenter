@extends('layouts.app')
@section('title', 'Fixed assets')
@section('subtitle', 'CAPITALISED AND DEPRECIATED · SCOPE OF WORK §7')

@section('actions')
    @can('manage-assets')
        <form method="POST" action="{{ route('assets.prepare-run') }}" class="flex items-center gap-2">
            @csrf
            <input name="month" type="month" class="input w-[150px]" value="{{ now()->format('Y-m') }}" required>
            <button class="btn btn-primary"><x-icon name="refresh" class="size-3.5"/> Run depreciation</button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="mb-4 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Cost" :value="\App\Support\Money::format($totals['cost'])" accent="#0A1F3D" delta="Total acquisition cost"/>
        <x-kpi label="Accumulated depreciation" :value="\App\Support\Money::format($totals['accumulated'])" accent="#98A2B3"
               delta="Charged to date"/>
        <x-kpi label="Net book value" :value="\App\Support\Money::format($totals['net_book_value'])" accent="#00C2E8"
               delta="Cost less depreciation"/>
        <x-kpi label="Monthly charge" :value="\App\Support\Money::format($totals['monthly_charge'])" accent="#0E7C5A"
               delta="Straight line, active assets"/>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1fr_300px]">
        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">Asset register</div>
                    <div class="card-sub">Vehicles depreciate to 8050; other assets to 9060, intangibles to 9070.</div>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <select name="category" class="select w-[150px]" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        @foreach (['vehicle' => 'Vehicles', 'it' => 'IT equipment', 'furniture' => 'Furniture', 'intangible' => 'Intangible'] as $v => $l)
                            <option value="{{ $v }}" @selected(request('category') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Asset</th><th>Category</th><th>Acquired</th><th class="num">Cost</th>
                            <th class="num">Accumulated</th><th class="num">Net book value</th>
                            <th class="num">Monthly</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td>
                                    <a href="{{ route('assets.show', $asset) }}" class="font-medium hover:text-signal">{{ $asset->name }}</a>
                                    <div class="eyebrow">{{ $asset->code }}</div>
                                </td>
                                <td class="text-muted">{{ ucfirst($asset->category) }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $asset->acquisition_date->format('j M Y') }}</td>
                                <td class="num"><x-money :value="$asset->cost"/></td>
                                <td class="num"><x-money :value="$asset->accumulated_depreciation" muted/></td>
                                <td class="num"><x-money :value="$asset->netBookValue()"/></td>
                                <td class="num"><x-money :value="$asset->monthlyCharge()" muted/></td>
                                <td>
                                    <x-badge :status="$asset->status"
                                             :tone="match($asset->status) { 'active' => 'success', 'disposed' => 'danger', default => 'neutral' }"/>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-empty title="No fixed assets"
                                message="Record a capital expense, then capitalise it from the expense page to add it here."/></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($assets->hasPages())<div class="border-t border-rule px-5 py-3">{{ $assets->links() }}</div>@endif
        </div>

        <div class="card">
            <div class="card-head"><div class="card-title">Depreciation runs</div></div>
            <div class="flex flex-col">
                @forelse ($runs as $run)
                    <a href="{{ route('assets.run', $run) }}"
                       class="flex items-center justify-between gap-3 border-b border-rule px-5 py-3 last:border-0 hover:bg-mist">
                        <span class="flex flex-col gap-0.5">
                            <span class="text-xs">{{ $run->run_month->format('F Y') }}</span>
                            <span class="eyebrow">{{ $run->reference }}</span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <x-money :value="$run->total_amount" class="text-xs"/>
                            <x-badge :status="$run->status" :tone="$run->status === 'posted' ? 'success' : 'neutral'"/>
                        </span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-xs text-faint">No depreciation has been run yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
