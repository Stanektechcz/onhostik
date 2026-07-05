@extends('layouts.panel')

@section('title', $announcement->exists ? 'Upravit oznámení' : 'Nové oznámení')

@section('content')
<div class="container-fluid py-4" style="max-width:640px">

    <h1 class="h4 mb-4">{{ $announcement->exists ? 'Upravit oznámení' : 'Nové systémové oznámení' }}</h1>

    <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
        @csrf
        @if($announcement->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Název <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $announcement->title) }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Text <span class="text-danger">*</span></label>
                    <textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="4" required>{{ old('body', $announcement->body) }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Typ <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            @foreach(['info' => 'Informace', 'warning' => 'Varování', 'maintenance' => 'Maintenance', 'feature' => 'Novinka'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('type', $announcement->type) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Ikona</label>
                        <input type="text" name="icon" class="form-control"
                               value="{{ old('icon', $announcement->icon ?? 'bell') }}"
                               placeholder="bell">
                        <div class="form-text">Feather icon name</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Vypršení</label>
                    <input type="datetime-local" name="expires_at" class="form-control"
                           value="{{ old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) }}">
                    <div class="form-text">Nechat prázdné = platí trvale</div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="send_email" id="send_email" class="form-check-input" value="1"
                           {{ old('send_email', $announcement->send_email ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="send_email">Odeslat také emailem</label>
                </div>

            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary btn-sm">Zrušit</a>
            </div>
        </div>
    </form>

</div>
@endsection
