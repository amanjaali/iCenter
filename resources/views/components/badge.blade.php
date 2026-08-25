@props(['status' => null, 'tone' => 'neutral', 'label' => null])

@php
    // A status enum knows its own tone; anything else takes the tone given.
    if ($status instanceof \App\Enums\DocumentStatus) {
        $class = $status->badge();
        $label ??= $status->label();
    } elseif ($status instanceof \App\Enums\JournalStatus) {
        $class = $status->badge();
        $label ??= $status->label();
    } elseif ($status instanceof \App\Enums\PeriodStatus) {
        $class = $status->badge();
        $label ??= $status->label();
    } else {
        $class = 'badge-'.$tone;
        $label ??= is_string($status) ? ucfirst(str_replace('_', ' ', $status)) : '—';
    }
@endphp

<span {{ $attributes->merge(['class' => "badge {$class}"]) }}>{{ $label }}</span>
