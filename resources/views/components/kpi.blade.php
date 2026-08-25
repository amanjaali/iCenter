@props(['label', 'value', 'delta' => null, 'direction' => null, 'accent' => '#0A1F3D', 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="card flex flex-col gap-2.5 p-[18px] {{ $href ? 'transition-colors hover:border-line-strong' : '' }}"
    style="border-top: 2px solid {{ $accent }}">
    <span class="eyebrow">{{ $label }}</span>
    <span class="kpi-value">{{ $value }}</span>
    @if ($delta !== null)
        <span class="flex items-center gap-1.5">
            @if ($direction)
                <x-icon :name="$direction === 'up' ? 'arrow-up' : 'arrow-down'"
                        class="size-3 shrink-0" style="color: {{ $accent }}; stroke-width: 2.4"/>
            @endif
            <span class="text-[11px] text-muted">{{ $delta }}</span>
        </span>
    @endif
</{{ $tag }}>
