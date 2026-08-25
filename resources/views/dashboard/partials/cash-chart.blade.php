@php
    /**
     * An inline SVG chart. Drawn here rather than pulled from a charting
     * library: the page must render on a network that cannot reach a CDN, and
     * three series over twelve months does not need one.
     */
    $width = 700;
    $height = 240;
    $padding = ['top' => 14, 'right' => 8, 'bottom' => 22, 'left' => 8];

    $values = collect($series)->flatMap(fn ($p) => [$p['cash'], $p['revenue'], $p['costs']]);
    $max = max(1.0, (float) $values->max());
    $min = min(0.0, (float) $values->min());
    $span = max(1.0, $max - $min);

    $plotWidth = $width - $padding['left'] - $padding['right'];
    $plotHeight = $height - $padding['top'] - $padding['bottom'];
    $count = max(1, count($series) - 1);

    $x = fn (int $i) => $padding['left'] + ($count === 0 ? $plotWidth / 2 : $i * $plotWidth / $count);
    $y = fn (float $v) => $padding['top'] + $plotHeight - (($v - $min) / $span) * $plotHeight;

    $path = function (string $key) use ($series, $x, $y) {
        return collect($series)
            ->map(fn ($point, $i) => ($i === 0 ? 'M' : 'L')
                .round($x($i), 1).' '.round($y((float) $point[$key]), 1))
            ->implode(' ');
    };

    $cashPath = $path('cash');
    $areaPath = $cashPath.' L'.round($x(count($series) - 1), 1).' '.round($y($min), 1)
        .' L'.round($x(0), 1).' '.round($y($min), 1).' Z';
@endphp

@if (collect($series)->every(fn ($p) => abs($p['cash']) < 1 && abs($p['revenue']) < 1))
    <x-empty title="No ledger activity yet"
             message="Once invoices and expenses are posted, the cash position will be plotted here."/>
@else
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-full max-h-[260px] w-full" preserveAspectRatio="none"
         role="img" aria-label="Cash, revenue and costs over the last twelve months">
        {{-- Gridlines --}}
        <g stroke="#EDF0F5" stroke-width="1">
            @for ($i = 0; $i <= 4; $i++)
                @php $gy = $padding['top'] + $i * $plotHeight / 4; @endphp
                <line x1="{{ $padding['left'] }}" y1="{{ $gy }}" x2="{{ $width - $padding['right'] }}" y2="{{ $gy }}"/>
            @endfor
        </g>

        {{-- Zero line, when the range crosses it --}}
        @if ($min < 0)
            <line x1="{{ $padding['left'] }}" y1="{{ round($y(0), 1) }}"
                  x2="{{ $width - $padding['right'] }}" y2="{{ round($y(0), 1) }}"
                  stroke="#DBE1EA" stroke-width="1.5"/>
        @endif

        <path d="{{ $areaPath }}" fill="#00C2E8" opacity="0.07"/>
        <path d="{{ $path('costs') }}" stroke="#C7D0DC" stroke-width="2" fill="none"
              stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="5 6"/>
        <path d="{{ $path('revenue') }}" stroke="#00C2E8" stroke-width="2" fill="none"
              stroke-linecap="round" stroke-linejoin="round"/>
        <path d="{{ $cashPath }}" stroke="#0A1F3D" stroke-width="2.5" fill="none"
              stroke-linecap="round" stroke-linejoin="round"/>

        <circle cx="{{ round($x(count($series) - 1), 1) }}"
                cy="{{ round($y((float) end($series)['cash']), 1) }}" r="4.5" fill="#00C2E8"/>

        {{-- Month labels, thinned so they never collide --}}
        @foreach ($series as $i => $point)
            @if ($i % 2 === 0 || $i === count($series) - 1)
                <text x="{{ round($x($i), 1) }}" y="{{ $height - 5 }}"
                      text-anchor="middle" font-size="9" font-family="'IBM Plex Mono', monospace"
                      fill="#98A2B3">{{ $point['label'] }}</text>
            @endif
            <title>{{ $point['full_label'] }}</title>
        @endforeach
    </svg>
@endif
