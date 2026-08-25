@props(['value' => 0, 'accounting' => false, 'code' => false, 'muted' => false])

@php
    $amount = (float) ($value ?? 0);
    $negative = $amount < -0.005;
    $text = $accounting
        ? \App\Support\Money::accounting($amount)
        : \App\Support\Money::format($amount, $code);
@endphp

<span {{ $attributes->merge([
    'class' => 'money '
        .($negative ? 'money-negative ' : '')
        .($muted && abs($amount) < 0.005 ? 'text-faint ' : ''),
]) }} @if($negative) title="{{ \App\Support\Money::format($amount, true) }}" @endif>{{ $text }}</span>
