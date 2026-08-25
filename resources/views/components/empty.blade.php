@props(['icon' => 'info', 'title' => 'Nothing here yet', 'message' => null])

<div class="empty">
    {{-- The symbol alone, watermarked — brand rule for empty states. --}}
    <svg width="34" height="34" viewBox="0 0 120 120" fill="none" class="opacity-25" aria-hidden="true">
        <path d="M54 10 L54 86 L14 108 Z" fill="#98A2B3"/>
        <path d="M66 10 L106 108 L66 86 Z" fill="#C7D0DC"/>
    </svg>
    <div class="font-display text-sm font-medium text-muted">{{ $title }}</div>
    @if ($message)
        <p class="max-w-md text-xs leading-relaxed">{{ $message }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-1">{{ $slot }}</div>
    @endif
</div>
