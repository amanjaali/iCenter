@extends('layouts.app')
@section('title', $student->exists ? 'Edit '.$student->name : 'Enrol a student')

@section('content')
    <form method="POST" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}"
          class="mx-auto flex max-w-3xl flex-col gap-4">
        @csrf
        @if ($student->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Enrolment</div>
                    <div class="card-sub">
                        The joining date starts the billable enrolment. Changing the bus later closes the
                        current enrolment and opens a new one, so past invoices stay reproducible.
                    </div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="bus_company_id">Bus company</label>
                    <select id="bus_company_id" name="bus_company_id" class="select @error('bus_company_id') input-invalid @enderror" required>
                        <option value="">Choose a company</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('bus_company_id', $student->bus_company_id) == $company->id)>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('bus_company_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="bus_id">Bus</label>
                    <select id="bus_id" name="bus_id" class="select @error('bus_id') input-invalid @enderror">
                        <option value="">Not yet assigned</option>
                        @foreach ($buses as $bus)
                            <option value="{{ $bus->id }}" data-company="{{ $bus->bus_company_id }}"
                                @selected(old('bus_id', $student->bus_id) == $bus->id)>
                                {{ $bus->code }}{{ $bus->route_name ? ' · '.$bus->route_name : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('bus_id')<div class="field-error">{{ $message }}</div>@enderror
                    <div class="hint">The bus must belong to the company chosen above.</div>
                </div>
                <div>
                    <label class="label" for="code">Student code</label>
                    <input id="code" name="code" class="input money @error('code') input-invalid @enderror"
                           value="{{ old('code', $student->code) }}" required placeholder="STU-000001">
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="joined_on">Joined on</label>
                    <input id="joined_on" name="joined_on" type="date" class="input"
                           value="{{ old('joined_on', $student->joined_on?->toDateString() ?? now()->toDateString()) }}" required>
                    <div class="hint">Billing starts from this date, prorated by the rule in Settings.</div>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $student->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name_ar">Name in Arabic</label>
                    <input id="name_ar" name="name_ar" class="input" dir="rtl" value="{{ old('name_ar', $student->name_ar) }}">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div class="card-title">Guardian and school</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="guardian_name">Guardian</label>
                    <input id="guardian_name" name="guardian_name" class="input" value="{{ old('guardian_name', $student->guardian_name) }}">
                </div>
                <div>
                    <label class="label" for="guardian_phone">Guardian phone</label>
                    <input id="guardian_phone" name="guardian_phone" class="input" value="{{ old('guardian_phone', $student->guardian_phone) }}">
                </div>
                <div>
                    <label class="label" for="school_name">School</label>
                    <input id="school_name" name="school_name" class="input" value="{{ old('school_name', $student->school_name) }}">
                </div>
                <div>
                    <label class="label" for="grade">Grade</label>
                    <input id="grade" name="grade" class="input" value="{{ old('grade', $student->grade) }}">
                </div>
                <div>
                    <label class="label" for="ble_tag">BLE tag</label>
                    <input id="ble_tag" name="ble_tag" class="input money" value="{{ old('ble_tag', $student->ble_tag) }}">
                </div>
                <div>
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="select">
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'left' => 'Left'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', $student->status) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <div class="hint">Use “Record as left” on the student page to stop billing on a given date.</div>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="textarea">{{ old('notes', $student->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $student->exists ? route('students.show', $student) : route('students.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $student->exists ? 'Save changes' : 'Enrol student' }}</button>
        </div>
    </form>
@endsection
