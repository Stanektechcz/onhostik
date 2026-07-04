@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Drip sekvence';
    $breadcrumbItems = ['Marketing' => '#', 'Drip sekvence' => ''];
@endphp

@section('title', 'Drip sekvence')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Sequence list --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="E-mailové sekvence">
                @if($sequences->isEmpty())
                    <div class="text-center py-4">
                        <i data-feather="mail" style="width:36px;height:36px" class="text-muted mb-2"></i>
                        <p class="f-light f-12">Zatím žádné sekvence. Vytvořte první vpravo.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover f-13">
                            <thead>
                                <tr>
                                    <th>Název</th>
                                    <th>Spouštěč</th>
                                    <th class="text-center">Kroků</th>
                                    <th class="text-center">Zapsaných</th>
                                    <th>Stav</th>
                                    <th class="text-end">Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sequences as $seq)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.drip.show', $seq) }}" class="f-w-500">
                                            {{ $seq->name }}
                                        </a>
                                        @if($seq->description)
                                            <div class="f-light f-11">{{ Str::limit($seq->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td class="f-light">{{ $seq->triggerLabel() }}</td>
                                    <td class="text-center">{{ $seq->steps_count }}</td>
                                    <td class="text-center">{{ $seq->enrollments_count }}</td>
                                    <td>
                                        @if($seq->is_active)
                                            <span class="badge badge-light-success">Aktivní</span>
                                        @else
                                            <span class="badge badge-light-secondary">Neaktivní</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.drip.show', $seq) }}"
                                           class="btn btn-xs btn-outline-primary">
                                            <i data-feather="settings" style="width:11px;height:11px"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.drip.destroy', $seq) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Smazat sekvenci? Všechny kroky a zápisy budou odstraněny.')">
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

        {{-- Create form --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Nová sekvence">
                <form method="POST" action="{{ route('admin.drip.store') }}" class="custom-input">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název *</label>
                        <input class="form-control @error('name') is-invalid @enderror"
                               type="text" name="name" value="{{ old('name') }}"
                               placeholder="Onboarding sekvence" required maxlength="150">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Spouštěč *</label>
                        <select class="form-select" name="trigger_event" required>
                            <option value="manual">Manuální zápis</option>
                            <option value="signup">Registrace zákazníka</option>
                            <option value="service_created">Vytvoření služby</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Popis</label>
                        <textarea class="form-control" name="description" rows="2"
                                  placeholder="Krátký popis účelu sekvence" maxlength="500">{{ old('description') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary text-white w-full">
                        <i data-feather="plus" style="width:14px;height:14px"></i> Vytvořit sekvenci
                    </button>
                </form>
            </x-panel.card>
        </div>

    </div>
</div>
@endsection
