@extends('layouts.panel')

@section('title', 'Nový SLA incident')

@section('content')
<div class="container-fluid py-4" style="max-width:640px">

    <h1 class="h4 mb-4">Nový SLA incident</h1>

    <form method="POST" action="{{ route('admin.sla-incidents.store') }}">
        @csrf
        <div class="card">
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Služba <span class="text-danger">*</span></label>
                    <select name="service_id" class="form-select @error('service_id') is-invalid @enderror" required>
                        <option value="">— Vyberte službu —</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>
                                {{ $service->name }}
                                @if($service->customer) ({{ $service->customer->company }}) @endif
                            </option>
                        @endforeach
                    </select>
                    @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Název <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Závažnost <span class="text-danger">*</span></label>
                    <select name="severity" class="form-select" required>
                        @foreach(['critical' => 'Kritická', 'high' => 'Vysoká', 'medium' => 'Střední', 'low' => 'Nízká'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('severity') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Popis</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>

            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-danger btn-sm">Otevřít incident</button>
                <a href="{{ route('admin.sla-incidents.index') }}" class="btn btn-outline-secondary btn-sm">Zrušit</a>
            </div>
        </div>
    </form>

</div>
@endsection
