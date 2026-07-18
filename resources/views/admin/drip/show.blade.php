@extends('layouts.panel')

@php
    $breadcrumbTitle = $drip->name;
    $breadcrumbItems = ['Marketing' => '#', 'Drip sekvence' => route('admin.drip.index'), $drip->name => ''];
@endphp

@section('title', $drip->name)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Header --}}
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $drip->name }}</h4>
            <div class="f-light f-12">
                Spouštěč: {{ $drip->triggerLabel() }}
                &bull; {{ $activeEnrollments }} aktivních &bull; {{ $completedEnrollments }} dokončených
            </div>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.drip.toggle', $drip) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-{{ $drip->is_active ? 'outline-warning' : 'outline-success' }}">
                    <i data-feather="{{ $drip->is_active ? 'pause' : 'play' }}" style="width:13px;height:13px"></i>
                    {{ $drip->is_active ? 'Deaktivovat' : 'Aktivovat' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-12 card-gap">

        {{-- Steps --}}
        <div class="col-span-7 xl:col-span-12">
            <x-panel.card title="Kroky sekvence">
                @if($drip->steps->isEmpty())
                    <div class="text-center py-4 f-light f-12">
                        Přidejte první krok vpravo.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm f-13">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Předmět</th>
                                    <th class="text-center">Odložit (dny)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($drip->steps as $i => $step)
                                <tr>
                                    <td class="f-light">{{ $i + 1 }}</td>
                                    <td>
                                        <span class="f-w-500">{{ $step->subject }}</span>
                                        <div class="f-11 f-light">{{ Str::limit(strip_tags($step->body_html), 80) }}</div>
                                    </td>
                                    <td class="text-center">+{{ $step->delay_days }} d</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('admin.drip.step.destroy', [$drip, $step]) }}"
                                              class="inline"
                                              onsubmit="return confirm('Smazat krok?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-outline-danger">
                                                <i data-feather="trash-2" style="width:11px;height:11px"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel.card>
        </div>

        <div class="col-span-5 xl:col-span-12">

            {{-- Add step --}}
            <x-panel.card title="Přidat krok">
                <form method="POST" action="{{ route('admin.drip.step.store', $drip) }}" class="custom-input">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Předmět e-mailu *</label>
                        <input class="form-control @error('subject') is-invalid @enderror"
                               type="text" name="subject" value="{{ old('subject') }}"
                               placeholder="Začínáme!" required maxlength="255">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Odeslat po (dny) *</label>
                        <input class="form-control @error('delay_days') is-invalid @enderror"
                               type="number" name="delay_days" value="{{ old('delay_days', 0) }}"
                               min="0" max="365" required>
                        <div class="f-11 f-light mt-1">0 = hned po předchozím kroku</div>
                        @error('delay_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Obsah e-mailu (HTML) *</label>
                        <textarea class="form-control @error('body_html') is-invalid @enderror"
                                  name="body_html" rows="5"
                                  placeholder="<p>Obsah e-mailu…</p>" required>{{ old('body_html') }}</textarea>
                        @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary text-white w-full">
                        <i data-feather="plus" style="width:14px;height:14px"></i> Přidat krok
                    </button>
                </form>
            </x-panel.card>

            {{-- Manual enroll --}}
            <x-panel.card title="Zapsat kontakt">
                <form method="POST" action="{{ route('admin.drip.enroll', $drip) }}" class="custom-input">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">E-mail *</label>
                        <input class="form-control" type="email" name="email"
                               placeholder="kontakt@example.com" required maxlength="254">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jméno</label>
                        <input class="form-control" type="text" name="name" maxlength="150">
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-full">
                        Zapsat do sekvence
                    </button>
                </form>
            </x-panel.card>

        </div>
    </div>
</div>
@endsection
