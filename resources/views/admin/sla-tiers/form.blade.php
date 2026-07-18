@extends('layouts.panel')

@section('title', $tier->exists ? 'Upravit SLA tier' : 'Nový SLA tier')

@section('content')
<div class="container-fluid py-4" style="max-width:640px">

    <h1 class="h4 mb-4">{{ $tier->exists ? 'Upravit SLA tier' : 'Nový SLA tier' }}</h1>

    <form method="POST" action="{{ $tier->exists ? route('admin.sla-tiers.update', $tier) : route('admin.sla-tiers.store') }}">
        @csrf
        @if($tier->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label font-semibold">Název <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $tier->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label font-semibold">Slug <span class="text-danger">*</span></label>
                    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug', $tier->slug) }}" required>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label font-semibold">Uptime SLA (×100) <span class="text-danger">*</span></label>
                    <input type="number" name="uptime_percent_x100" class="form-control @error('uptime_percent_x100') is-invalid @enderror"
                           value="{{ old('uptime_percent_x100', $tier->uptime_percent_x100) }}"
                           min="9000" max="9999" required>
                    <div class="form-text">9999 = 99.99 %, 9990 = 99.9 %, 9950 = 99.5 %</div>
                    @error('uptime_percent_x100')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="grid grid-cols-12">
                    <div class="col-span-12 md:col-span-6 mb-3">
                        <label class="form-label font-semibold">Odezva podpory (min) <span class="text-danger">*</span></label>
                        <input type="number" name="response_time_minutes" class="form-control"
                               value="{{ old('response_time_minutes', $tier->response_time_minutes) }}" min="1" required>
                    </div>
                    <div class="col-span-12 md:col-span-6 mb-3">
                        <label class="form-label font-semibold">Čas řešení (hod) <span class="text-danger">*</span></label>
                        <input type="number" name="resolution_time_hours" class="form-control"
                               value="{{ old('resolution_time_hours', $tier->resolution_time_hours) }}" min="1" required>
                    </div>
                </div>

                <div class="grid grid-cols-12">
                    <div class="col-span-12 md:col-span-6 mb-3">
                        <label class="form-label font-semibold">Kredit za hodinu výpadku (%) <span class="text-danger">*</span></label>
                        <input type="number" name="credit_percent_per_hour" class="form-control"
                               value="{{ old('credit_percent_per_hour', $tier->credit_percent_per_hour) }}" min="1" max="100" required>
                    </div>
                    <div class="col-span-12 md:col-span-6 mb-3">
                        <label class="form-label font-semibold">Max. kredit za měsíc (%) <span class="text-danger">*</span></label>
                        <input type="number" name="max_credit_percent" class="form-control"
                               value="{{ old('max_credit_percent', $tier->max_credit_percent) }}" min="1" max="100" required>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
                           {{ old('is_active', $tier->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktivní</label>
                </div>

            </div>
            <div class="card-footer flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                <a href="{{ route('admin.sla-tiers.index') }}" class="btn btn-outline-secondary btn-sm">Zrušit</a>
            </div>
        </div>
    </form>

</div>
@endsection
