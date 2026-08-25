@extends('layouts.app')
@section('title', $bus->exists ? 'Edit bus '.$bus->code : 'New bus')

@section('content')
    <form method="POST" action="{{ $bus->exists ? route('buses.update', $bus) : route('buses.store') }}"
          class="mx-auto flex max-w-3xl flex-col gap-4">
        @csrf
        @if ($bus->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Bus details</div>
                    <div class="card-sub">Each bus belongs to one company and carries its own students.</div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="bus_company_id">Bus company</label>
                    <select id="bus_company_id" name="bus_company_id" class="select @error('bus_company_id') input-invalid @enderror" required>
                        <option value="">Choose a company</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('bus_company_id', $bus->bus_company_id) == $company->id)>
                                {{ $company->name }} ({{ $company->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('bus_company_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="code">Bus number</label>
                    <input id="code" name="code" class="input @error('code') input-invalid @enderror"
                           value="{{ old('code', $bus->code) }}" required>
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="plate_number">Plate number</label>
                    <input id="plate_number" name="plate_number" class="input" value="{{ old('plate_number', $bus->plate_number) }}">
                </div>
                <div>
                    <label class="label" for="capacity">Capacity</label>
                    <input id="capacity" name="capacity" type="number" min="0" max="200" class="input input-num"
                           value="{{ old('capacity', $bus->capacity ?? 0) }}" required>
                </div>
                <div>
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="select">
                        @foreach (['active' => 'Active', 'maintenance' => 'Maintenance', 'retired' => 'Retired'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', $bus->status) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="route_name">Route</label>
                    <input id="route_name" name="route_name" class="input" value="{{ old('route_name', $bus->route_name) }}">
                </div>
                <div>
                    <label class="label" for="device_serial">eTrackify device serial</label>
                    <input id="device_serial" name="device_serial" class="input money"
                           value="{{ old('device_serial', $bus->device_serial) }}">
                </div>
                <div>
                    <label class="label" for="driver_name">Driver</label>
                    <input id="driver_name" name="driver_name" class="input" value="{{ old('driver_name', $bus->driver_name) }}">
                </div>
                <div>
                    <label class="label" for="driver_phone">Driver phone</label>
                    <input id="driver_phone" name="driver_phone" class="input" value="{{ old('driver_phone', $bus->driver_phone) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="textarea">{{ old('notes', $bus->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $bus->exists ? route('buses.show', $bus) : route('buses.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $bus->exists ? 'Save changes' : 'Register bus' }}</button>
        </div>
    </form>
@endsection
