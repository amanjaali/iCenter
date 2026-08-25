@props(['label', 'value', 'emphasis' => false, 'indent' => false, 'negative' => false, 'accounting' => true])

<div class="flex items-baseline justify-between gap-4 py-1.5 {{ $emphasis ? 'border-t border-line-strong pt-2.5 font-medium' : '' }}">
    <span class="text-xs {{ $emphasis ? 'text-ink' : 'text-body' }} {{ $indent ? 'ps-4' : '' }}">{{ $label }}</span>
    <x-money :value="$value" :accounting="$accounting"
             class="shrink-0 text-xs {{ $emphasis ? 'font-medium text-ink' : 'text-body' }}"/>
</div>
