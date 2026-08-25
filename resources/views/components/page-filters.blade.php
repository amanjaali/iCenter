@props(['action', 'filters', 'projects' => null, 'departments' => null, 'companies' => null, 'compact' => false])

{{--
  Scope of work §8: "All reports must be filterable by date range, project, and
  department, and must be exportable." One component so every report's filter
  bar behaves identically.
--}}
<form method="GET" action="{{ $action }}" class="card flex flex-wrap items-end gap-3 p-3.5 no-print">
    <div class="min-w-[130px]">
        <label class="label" for="filter-from">From</label>
        <input type="date" id="filter-from" name="from" class="input" value="{{ $filters->from->toDateString() }}">
    </div>
    <div class="min-w-[130px]">
        <label class="label" for="filter-to">To</label>
        <input type="date" id="filter-to" name="to" class="input" value="{{ $filters->to->toDateString() }}">
    </div>

    @if ($projects)
        <div class="min-w-[150px]">
            <label class="label" for="filter-project">Project</label>
            <select id="filter-project" name="project_id" class="select">
                <option value="">All projects</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected($filters->projectId === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($departments)
        <div class="min-w-[150px]">
            <label class="label" for="filter-department">Cost centre</label>
            <select id="filter-department" name="department_id" class="select">
                <option value="">All cost centres</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected($filters->departmentId === $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($companies)
        <div class="min-w-[170px]">
            <label class="label" for="filter-company">Bus company</label>
            <select id="filter-company" name="bus_company_id" class="select">
                <option value="">All bus companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected($filters->busCompanyId === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{ $slot }}

    <div class="flex items-center gap-2">
        <button type="submit" class="btn btn-primary">
            <x-icon name="filter" class="size-3.5"/> Apply
        </button>
        <a href="{{ $action }}" class="btn btn-secondary">Reset</a>
    </div>

    <div class="ms-auto flex items-center gap-2">
        <button type="submit" name="export" value="csv" class="btn btn-secondary">
            <x-icon name="download" class="size-3.5"/> CSV
        </button>
        <button type="button" onclick="window.print()" class="btn btn-secondary">
            <x-icon name="print" class="size-3.5"/> Print
        </button>
    </div>
</form>
