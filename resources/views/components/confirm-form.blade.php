@props(['action', 'method' => 'POST', 'confirm' => 'Are you sure?', 'reason' => false, 'reasonLabel' => 'Reason'])

{{--
  Destructive and irreversible actions (posting, reversing, voiding) always ask
  first, and the ones §9.3 requires a reason for collect it here rather than
  leaving the audit trail with an empty explanation.
--}}
<form method="POST" action="{{ $action }}" x-data="{ open: false }" @submit="if (!open) $event.preventDefault()">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <button type="button" @click="open = true" {{ $attributes->merge(['class' => 'btn btn-secondary btn-sm']) }}>
        {{ $slot }}
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy/40 p-4"
             @keydown.escape.window="open = false">
            <div class="card w-full max-w-md p-5" @click.outside="open = false">
                <div class="mb-4 flex items-start gap-3">
                    <x-icon name="alert" class="mt-px size-5 shrink-0 text-caution"/>
                    <p class="text-xs leading-relaxed text-body">{{ $confirm }}</p>
                </div>

                @if ($reason)
                    <div class="mb-4">
                        <label class="label" for="reason-{{ md5($action) }}">{{ $reasonLabel }}</label>
                        <textarea id="reason-{{ md5($action) }}" name="reason" rows="2" required
                                  class="textarea" placeholder="Recorded in the audit trail"></textarea>
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <button type="button" @click="open = false" class="btn btn-secondary btn-sm">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                </div>
            </div>
        </div>
    </template>
</form>
