@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Upravit kampaň';
    $breadcrumbItems = [
        'Newsletter' => route('admin.newsletter.index'),
        Str::limit($campaign->subject, 30) => route('admin.newsletter.show', $campaign),
        'Upravit' => '',
    ];
@endphp

@section('title', 'Upravit kampaň')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <form method="POST" action="{{ route('admin.newsletter.update', $campaign) }}">
        @csrf @method('PUT')

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-8 sm:col-span-12">
                <div class="card card-no-border">
                    <div class="card-header">
                        <h5>Obsah kampaně</h5>
                    </div>
                    <div class="card-body custom-input">
                        <div class="mb-3">
                            <label class="form-label">Předmět e-mailu *</label>
                            <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
                                   value="{{ old('subject', $campaign->subject) }}" maxlength="255" required>
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">HTML obsah *</label>
                            <textarea name="body_html" class="form-control @error('body_html') is-invalid @enderror"
                                      rows="18" required>{{ old('body_html', $campaign->body_html) }}</textarea>
                            @error('body_html')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Textová verze <span class="f-light">(nepovinné)</span></label>
                            <textarea name="body_text" class="form-control" rows="6">{{ old('body_text', $campaign->body_text) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-4 sm:col-span-12">
                <div class="card card-no-border">
                    <div class="card-header">
                        <h5>Akce</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary text-white">
                                <i data-feather="save" style="width:14px;height:14px;"></i> Uložit změny
                            </button>
                            <a href="{{ route('admin.newsletter.show', $campaign) }}" class="btn btn-outline-secondary">
                                Zrušit
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
