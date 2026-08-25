@extends('layouts.app')
@section('title', 'Active student count')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Active student count" :filters="$filters"/>

    <x-page-filters :action="route('reports.active-students')" :filters="$filters" :companies="$companies"/>

    <div class="mt-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
        <div class="leading-relaxed">
            Scope of work §8.2 — per bus company, per bus, per month, showing joins and exits. Counted from
            the enrollment history, so it matches what billing charges. A cell reads
            <strong class="font-medium">active</strong> with joins and exits beneath.
        </div>
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th class="sticky start-0 bg-[#F9FAFC]">Bus company / bus</th>
                        @foreach ($report['months'] as $month)
                            <th class="num">{{ $month }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $companyRow)
                        <tr class="bg-mist">
                            <td class="sticky start-0 bg-mist font-medium">{{ $companyRow['company']->name }}</td>
                            @foreach ($companyRow['cells'] as $cell)
                                <td class="num">
                                    <span class="font-medium">{{ number_format($cell['active']) }}</span>
                                    @if ($cell['joined'] || $cell['left'])
                                        <div class="text-[10px] text-faint">
                                            @if ($cell['joined'])<span class="text-positive">+{{ $cell['joined'] }}</span>@endif
                                            @if ($cell['left'])<span class="text-negative ms-1">−{{ $cell['left'] }}</span>@endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @foreach ($companyRow['buses'] as $busRow)
                            <tr>
                                <td class="sticky start-0 bg-white ps-6 text-muted">{{ $busRow['bus']->code }}</td>
                                @foreach ($busRow['cells'] as $cell)
                                    <td class="num text-muted">
                                        {{ number_format($cell['active']) }}
                                        @if ($cell['joined'] || $cell['left'])
                                            <div class="text-[10px] text-faint">
                                                @if ($cell['joined'])<span class="text-positive">+{{ $cell['joined'] }}</span>@endif
                                                @if ($cell['left'])<span class="text-negative ms-1">−{{ $cell['left'] }}</span>@endif
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="{{ count($report['months']) + 1 }}"><x-empty title="No bus companies"/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
