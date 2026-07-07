@extends('layouts.panel')

@section('title', 'Onboarding zákazníků')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Create form --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Přidat krok onboardingu">
                <form method="POST" action="{{ route('admin.customer-onboarding-steps.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Customer ID <span class="text-danger">*</span></label>
                        <input type="number" name="customer_id" class="form-control form-control-sm"
                               value="{{ old('customer_id', $customerId) }}" required>
                        @error('customer_id')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Krok <span class="text-danger">*</span></label>
                        <input type="text" name="step" class="form-control form-control-sm" maxlength="100"
                               value="{{ old('step') }}" required placeholder="napr. email_verified">
                        @error('step')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Popis</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3"
                                  maxlength="500">{{ old('description') }}</textarea>
                        @error('description')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_required" value="1" id="is_required"
                               class="form-check-input" {{ old('is_required', '1') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_required">Povinný krok</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Uložit krok</button>
                </form>
            </x-panel.card>
        </div>

        {{-- Table --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Kroky onboardingu">

                {{-- Filter --}}
                <form method="GET" action="{{ route('admin.customer-onboarding-steps.index') }}" class="d-flex gap-2 mb-3 flex-wrap">
                    <input type="number" name="customer_id" class="form-control form-control-sm w-auto"
                           placeholder="Customer ID" value="{{ $customerId }}">
                    <div class="form-check align-self-center ms-2">
                        <input type="checkbox" name="pending" value="1" id="pending_filter"
                               class="form-check-input" {{ $onlyPending ? 'checked' : '' }}>
                        <label class="form-check-label" for="pending_filter">Pouze čekající</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-secondary">Filtrovat</button>
                    @if($customerId || $onlyPending)
                        <a href="{{ route('admin.customer-onboarding-steps.index') }}" class="btn btn-sm btn-outline-secondary">Zrušit</a>
                    @endif
                </form>

                @if($steps->isEmpty())
                    <p class="text-muted">Žádné kroky onboardingu.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer ID</th>
                                <th>Krok</th>
                                <th>Popis</th>
                                <th>Povinný</th>
                                <th>Dokončen</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($steps as $step)
                            <tr>
                                <td class="f-12 text-muted">{{ $step->id }}</td>
                                <td>{{ $step->customer_id }}</td>
                                <td>
                                    <code class="badge bg-secondary f-12">{{ $step->step }}</code>
                                </td>
                                <td class="f-12 text-muted" style="max-width:200px">
                                    {{ $step->description ? \Illuminate\Support\Str::limit($step->description, 60) : '—' }}
                                </td>
                                <td>
                                    @if($step->is_required)
                                        <span class="badge bg-danger">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td class="f-12">
                                    @if($step->completed_at)
                                        <span class="text-success">
                                            {{ $step->completed_at instanceof \Carbon\Carbon ? $step->completed_at->format('d.m.Y H:i') : \Carbon\Carbon::parse($step->completed_at)->format('d.m.Y H:i') }}
                                        </span>
                                    @else
                                        <span class="text-muted">Čeká</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- Toggle completed_at --}}
                                    <form method="POST" action="{{ route('admin.customer-onboarding-steps.update', $step) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-xs {{ $step->completed_at ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                            {{ $step->completed_at ? 'Odznačit' : 'Dokončit' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $steps->appends(request()->query())->links() }}</div>
                @endif

            </x-panel.card>
        </div>

    </div>
</div>
@endsection
