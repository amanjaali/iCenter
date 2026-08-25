@props(['title', 'filters' => null, 'subtitle' => null])

{{-- Only shown when printing: a printed report must identify itself. --}}
<div class="mb-4 hidden print:block">
    <div class="flex items-start justify-between border-b-2 border-signal pb-3">
        <div class="flex items-center gap-3">
            <svg width="26" height="26" viewBox="0 0 120 120" fill="none">
                <path d="M54 10 L54 86 L14 108 Z" fill="#0A1F3D"/>
                <path d="M66 10 L106 108 L66 86 Z" fill="#00C2E8"/>
            </svg>
            <div>
                <div class="font-display text-[17px] font-medium leading-none tracking-[0.06em]">ALLVA</div>
                <div class="text-[6px] font-medium tracking-[0.36em] text-faint ps-[0.36em]">ACCOUNTING</div>
            </div>
        </div>
        <div class="text-end">
            <div class="font-display text-base font-medium">{{ $title }}</div>
            @if ($filters)
                <div class="eyebrow">{{ $filters->label() }}</div>
            @elseif ($subtitle)
                <div class="eyebrow">{{ $subtitle }}</div>
            @endif
            <div class="eyebrow">Prepared {{ now()->format('j M Y H:i') }}</div>
        </div>
    </div>
</div>
