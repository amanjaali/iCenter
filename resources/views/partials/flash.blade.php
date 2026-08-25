@php
    $messages = array_filter([
        'success' => session('success'),
        'warning' => session('warning'),
        'error' => session('error') ?? ($errors->has('posting') ? $errors->first('posting') : null),
        'info' => session('info'),
    ]);
@endphp

@if ($messages)
    <div class="mb-5 flex flex-col gap-2 no-print">
        @foreach ($messages as $tone => $message)
            @php
                [$icon, $classes] = match ($tone) {
                    'success' => ['check', 'border-positive/25 bg-positive-soft text-positive'],
                    'warning' => ['alert', 'border-caution/25 bg-caution-soft text-caution'],
                    'error' => ['alert', 'border-negative/25 bg-negative-soft text-negative'],
                    default => ['info', 'border-line bg-white text-body'],
                };
            @endphp
            <div class="flex items-start gap-2.5 rounded-md border px-3.5 py-2.5 text-xs {{ $classes }}"
                 role="{{ $tone === 'error' ? 'alert' : 'status' }}">
                <x-icon :name="$icon" class="mt-px size-4 shrink-0"/>
                <div class="leading-relaxed">{{ $message }}</div>
            </div>
        @endforeach
    </div>
@endif

@if ($errors->any() && ! $errors->has('posting'))
    <div class="mb-5 rounded-md border border-negative/25 bg-negative-soft px-3.5 py-2.5 no-print" role="alert">
        <div class="flex items-start gap-2.5 text-xs text-negative">
            <x-icon name="alert" class="mt-px size-4 shrink-0"/>
            <div>
                <div class="font-medium">{{ $errors->count() === 1 ? 'There is a problem with this form.' : 'There are '.$errors->count().' problems with this form.' }}</div>
                <ul class="mt-1.5 flex list-disc flex-col gap-0.5 ps-4 leading-relaxed">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
