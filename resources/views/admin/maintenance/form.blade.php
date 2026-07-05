@extends('layouts.panel')

@section('title', $window->exists ? 'Upravit okno údržby' : 'Nové okno údržby')

@section('content')
<div class="container-fluid py-4" style="max-width:700px">

    <h1 class="h4 mb-4">{{ $window->exists ? 'Upravit okno údržby' : 'Nové okno údržby' }}</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST"
                  action="{{ $window->exists ? route('admin.maintenance.update', $window) : route('admin.maintenance.store') }}">
                @csrf
                @if($window->exists) @method('PUT') @endif

                @if($errors->any())
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Název *</label>
                    <input type="text" name="title" value="{{ old('title', $window->title) }}"
                           class="form-control @error('title') is-invalid @enderror" required maxlength="200">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Popis</label>
                    <textarea name="description" rows="3"
                              class="form-control @error('description') is-invalid @enderror"
                              maxlength="2000">{{ old('description', $window->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Služba (volitelné)</label>
                    <select name="service_id" class="form-select @error('service_id') is-invalid @enderror">
                        <option value="">— Globální (bez vazby na konkrétní službu) —</option>
                        @foreach($services as $s)
                            <option value="{{ $s->id }}"
                                    @selected(old('service_id', $window->service_id) == $s->id)>
                                #{{ $s->id }} {{ $s->label ?? '' }} — {{ $s->customer?->user->name ?? '?' }}
                            </option>
                        @endforeach
                    </select>
                    @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Plánovaný začátek *</label>
                        <input type="datetime-local" name="scheduled_start"
                               value="{{ old('scheduled_start', $window->scheduled_start?->format('Y-m-d\TH:i')) }}"
                               class="form-control @error('scheduled_start') is-invalid @enderror" required>
                        @error('scheduled_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plánovaný konec *</label>
                        <input type="datetime-local" name="scheduled_end"
                               value="{{ old('scheduled_end', $window->scheduled_end?->format('Y-m-d\TH:i')) }}"
                               class="form-control @error('scheduled_end') is-invalid @enderror" required>
                        @error('scheduled_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Stav</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach(['scheduled' => 'Plánováno', 'in_progress' => 'Probíhá', 'completed' => 'Dokončeno', 'cancelled' => 'Zrušeno'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', $window->status ?? 'scheduled') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-check mb-4">
                    <input type="hidden" name="notify_customers" value="0">
                    <input class="form-check-input" type="checkbox" name="notify_customers" value="1" id="notify"
                           @checked(old('notify_customers', $window->notify_customers ?? true))>
                    <label class="form-check-label" for="notify">Upozornit zákazníky</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                    <a href="{{ route('admin.maintenance.index') }}" class="btn btn-outline-secondary btn-sm">Zpět</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
