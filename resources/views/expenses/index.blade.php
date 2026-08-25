@extends('layouts.app')
@section('title', 'Expenses')
@section('subtitle', number_format($totals->count ?? 0).' ENTRIES · '.\App\Support\Money::format($totals->total ?? 0))

@section('actions')
    @can('manage-expenses')
        <a href="{{ route('expenses.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> Record expense
        </a>
    @endcan
@endsection

@section('content')
    {{-- Scope of work §7: every expense carries an account, a cost centre and a
         project, and is classified fixed/variable and capex/opex — so those are
         the filters, not an afterthought. --}}
    <form method="GET" class="card mb-4 grid gap-3 p-3.5 md:grid-cols-4 lg:grid-cols-6">
        <div class="md:col-span-2">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Number or description">
        </div>
        <div>
            <label class="label" for="account_id">Account</label>
            <select id="account_id" name="account_id" class="select">
                <option value="">All accounts</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>
                        {{ $account->code }} — {{ \Illuminate\Support\Str::limit($account->name, 28) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="department_id">Cost centre</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">All</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">All</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="nature">Nature</label>
            <select id="nature" name="nature" class="select">
                <option value="">All</option>
                <option value="fixed" @selected(request('nature') === 'fixed')>Fixed</option>
                <option value="variable" @selected(request('nature') === 'variable')>Variable</option>
            </select>
        </div>
        <div>
            <label class="label" for="treatment">Treatment</label>
            <select id="treatment" name="treatment" class="select">
                <option value="">All</option>
                <option value="opex" @selected(request('treatment') === 'opex')>Operating</option>
                <option value="capex" @selected(request('treatment') === 'capex')>Capital</option>
            </select>
        </div>
        <div>
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All</option>
                @foreach (['draft' => 'Draft', 'posted' => 'Posted', 'void' => 'Void'] as $v => $l)
                    <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="from">From</label>
            <input id="from" name="from" type="date" class="input" value="{{ request('from') }}">
        </div>
        <div>
            <label class="label" for="to">To</label>
            <input id="to" name="to" type="date" class="input" value="{{ request('to') }}">
        </div>
        <div class="flex items-end gap-2 md:col-span-2">
            <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Filter</button>
            <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="mb-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Total in this view" :value="\App\Support\Money::format($totals->total ?? 0)" accent="#0A1F3D"
               :delta="number_format($totals->count ?? 0).' entries'"/>
        <x-kpi label="Unpaid to suppliers" :value="\App\Support\Money::format($totals->unpaid ?? 0)"
               :accent="($totals->unpaid ?? 0) > 0 ? '#9A6206' : '#98A2B3'" delta="Sitting in accounts payable"/>
        <x-kpi label="Expense report" value="§8.3" accent="#00C2E8"
               delta="By account, department and period" :href="route('reports.expenses')"/>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Account</th>
                        <th>Cost centre</th>
                        <th>Project</th>
                        <th>Class</th>
                        <th class="num">Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>
                                <a href="{{ route('expenses.show', $expense) }}" class="money text-xs hover:text-signal">
                                    {{ $expense->number }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap text-muted">{{ $expense->expense_date->format('j M Y') }}</td>
                            <td class="max-w-[280px] truncate" title="{{ $expense->description }}">
                                {{ $expense->description }}
                                @if ($expense->supplier)
                                    <div class="eyebrow">{{ $expense->supplier->name }}</div>
                                @endif
                            </td>
                            <td class="text-muted">
                                <span class="money">{{ $expense->account->code }}</span>
                            </td>
                            <td class="text-muted">{{ $expense->department->name }}</td>
                            <td class="text-muted">{{ $expense->project->name }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-neutral">{{ $expense->nature->label() }}</span>
                                    @if ($expense->isCapex())
                                        <span class="badge badge-info">Capital</span>
                                    @endif
                                    @if ($expense->payment_status === 'unpaid')
                                        <span class="badge badge-warning">Unpaid</span>
                                    @endif
                                </div>
                            </td>
                            <td class="num"><x-money :value="$expense->total"/></td>
                            <td>
                                <x-badge :status="$expense->status"
                                         :tone="$expense->status === 'posted' ? 'success' : ($expense->status === 'void' ? 'danger' : 'neutral')"/>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty title="No expenses match this view"
                                         message="Scope of work §7: every expense entry carries an account, a cost centre and a project, so project profitability can be reported apart from company overhead.">
                                    @can('manage-expenses')
                                        <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm">Record an expense</a>
                                    @endcan
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($expenses->hasPages())<div class="border-t border-rule px-5 py-3">{{ $expenses->links() }}</div>@endif
    </div>
@endsection
