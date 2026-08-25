@extends('layouts.app')
@section('title', 'New journal')

@section('content')
    {{-- A journal must balance before it can be saved, so the totals and the
         difference are shown live rather than discovered on submit. --}}
    <form method="POST" action="{{ route('journals.store') }}" class="flex flex-col gap-4"
          x-data="journalForm({{ (int) old('lines') ? count(old('lines')) : 4 }})">
        @csrf

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Journal</div>
                    <div class="card-sub">
                        Manual entries only. Invoices, payroll, depreciation and distributions post their
                        own journals automatically.
                    </div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="journal_date">Date</label>
                    <input id="journal_date" name="journal_date" type="date" class="input" required
                           value="{{ old('journal_date', now()->toDateString()) }}">
                    <div class="hint">The period this date falls in must be open.</div>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="memo">Memo</label>
                    <input id="memo" name="memo" class="input @error('memo') input-invalid @enderror" required
                           value="{{ old('memo') }}" placeholder="What this entry records">
                    @error('memo')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">Lines</div>
                    <div class="card-sub">
                        Expense accounts require a cost centre and a project (scope of work §7).
                    </div>
                </div>
                <button type="button" @click="addLine()" class="btn btn-secondary btn-sm">
                    <x-icon name="plus" class="size-3"/> Add line
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-[300px]">Account</th>
                            <th>Description</th>
                            <th class="w-[150px]">Cost centre</th>
                            <th class="w-[150px]">Project</th>
                            <th class="num w-[140px]">Debit</th>
                            <th class="num w-[140px]">Credit</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="line.key">
                            <tr>
                                <td>
                                    <select :name="`lines[${index}][account_id]`" class="select" x-model="line.account_id">
                                        <option value="">Choose an account</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}"
                                                    data-requires="{{ $account->requires_department || $account->requires_project ? '1' : '0' }}">
                                                {{ $account->code }} — {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input :name="`lines[${index}][description]`" class="input" x-model="line.description"></td>
                                <td>
                                    <select :name="`lines[${index}][department_id]`" class="select" x-model="line.department_id">
                                        <option value="">—</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select :name="`lines[${index}][project_id]`" class="select" x-model="line.project_id">
                                        <option value="">—</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" class="input input-num"
                                           :name="`lines[${index}][debit]`" x-model="line.debit"
                                           @input="if (line.debit) line.credit = ''">
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" class="input input-num"
                                           :name="`lines[${index}][credit]`" x-model="line.credit"
                                           @input="if (line.credit) line.debit = ''">
                                </td>
                                <td class="text-end">
                                    <button type="button" @click="removeLine(index)" class="btn btn-ghost btn-sm"
                                            x-show="lines.length > 2" aria-label="Remove line">
                                        <x-icon name="trash" class="size-3.5"/>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">Totals</td>
                            <td class="num" x-text="format(totalDebit)"></td>
                            <td class="num" x-text="format(totalCredit)"></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="4">
                                <span x-show="balanced" class="text-positive">Balanced</span>
                                <span x-show="!balanced" class="text-negative">
                                    Out of balance by <span x-text="format(Math.abs(difference))"></span> —
                                    the ledger will refuse this entry
                                </span>
                            </td>
                            <td colspan="3" class="num" :class="!balanced && 'text-negative'"
                                x-text="balanced ? '' : format(difference)"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-rule px-5 py-4">
                <label class="flex cursor-pointer items-center gap-2 text-xs text-body">
                    <input type="checkbox" name="post_now" value="1" class="size-3.5 accent-navy" checked>
                    Post immediately — otherwise it is saved as a draft
                </label>
                <div class="flex items-center gap-2">
                    <a href="{{ route('journals.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" :disabled="!balanced || totalDebit === 0">Save journal</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function journalForm(initialCount) {
            return {
                nextKey: 0,
                lines: [],
                init() {
                    for (let i = 0; i < Math.max(2, initialCount); i++) this.addLine();
                },
                addLine() {
                    this.lines.push({
                        key: this.nextKey++, account_id: '', description: '',
                        department_id: '', project_id: '', debit: '', credit: '',
                    });
                },
                removeLine(index) { this.lines.splice(index, 1); },
                get totalDebit() {
                    return this.lines.reduce((s, l) => s + (parseFloat(l.debit) || 0), 0);
                },
                get totalCredit() {
                    return this.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0);
                },
                get difference() { return this.totalDebit - this.totalCredit; },
                get balanced() { return Math.abs(this.difference) < 0.005; },
                format(value) { return new Intl.NumberFormat().format(Math.round(value)); },
            };
        }
    </script>
@endsection
