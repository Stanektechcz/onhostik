@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Nová kampaň';
    $breadcrumbItems = ['Newsletter' => route('admin.newsletter.index'), 'Nová kampaň' => ''];
@endphp

@section('title', 'Nová newsletter kampaň')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <form method="POST" action="{{ route('admin.newsletter.store') }}">
        @csrf

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
                                   value="{{ old('subject') }}" maxlength="255"
                                   placeholder="Novinky ze světa webhostingu — červen 2026" required>
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">HTML obsah *</label>
                            <textarea name="body_html" id="body_html"
                                      class="form-control @error('body_html') is-invalid @enderror"
                                      rows="18" required
                                      placeholder="Vložte HTML kód e-mailu…">{{ old('body_html') }}</textarea>
                            @error('body_html')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="f-light f-11 mt-1">
                                Doporučujeme používat tabulkové HTML kompatibilní se starými e-mailovými klienty.
                                Proměnná <code>&#123;&#123; $subscriber->name &#125;&#125;</code> bude nahrazena jménem odběratele.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Textová verze <span class="f-light">(nepovinné)</span></label>
                            <textarea name="body_text" class="form-control"
                                      rows="6"
                                      placeholder="Textová záložní verze pro e-mailové klienty bez HTML…">{{ old('body_text') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-4 sm:col-span-12">
                <div class="card card-no-border">
                    <div class="card-header">
                        <h5>Odeslání</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light-primary f-12 mb-3">
                            <i data-feather="info" style="width:14px;height:14px;"></i>
                            Po uložení jako koncept budete moci kampaň zkontrolovat a spustit hromadné odeslání.
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary text-white">
                                <i data-feather="save" style="width:14px;height:14px;"></i> Uložit jako koncept
                            </button>
                            <a href="{{ route('admin.newsletter.index') }}" class="btn btn-outline-secondary">
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
