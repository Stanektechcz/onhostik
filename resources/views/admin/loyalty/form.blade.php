@extends('layouts.panel')

@section('title', $milestone->exists ? 'Upravit milník' : 'Nový milník')

@section('content')
<div class="container-fluid py-4" style="max-width:700px">

    <h1 class="h4 mb-4">{{ $milestone->exists ? 'Upravit milník' : 'Nový věrnostní milník' }}</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST"
                  action="{{ $milestone->exists ? route('admin.loyalty.update', $milestone) : route('admin.loyalty.store') }}">
                @csrf
                @if($milestone->exists) @method('PUT') @endif

                @if($errors->any())
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 md:col-span-8">
                        <label class="form-label">Název *</label>
                        <input type="text" name="name" value="{{ old('name', $milestone->name) }}"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="form-label">Slug *</label>
                        <input type="text" name="slug" value="{{ old('slug', $milestone->slug) }}"
                               class="form-control @error('slug') is-invalid @enderror" required>
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Typ podmínky *</label>
                        <select name="trigger_type" class="form-select @error('trigger_type') is-invalid @enderror" required>
                            @foreach(['account_age_days' => 'Věk účtu (dní)', 'order_count' => 'Počet objednávek', 'total_spent_czk' => 'Celkem utraceno (haléře)'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('trigger_type', $milestone->trigger_type) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        @error('trigger_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Hodnota podmínky *</label>
                        <input type="number" name="trigger_value" min="1"
                               value="{{ old('trigger_value', $milestone->trigger_value) }}"
                               class="form-control @error('trigger_value') is-invalid @enderror" required>
                        @error('trigger_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Typ odměny *</label>
                        <select name="reward_type" class="form-select @error('reward_type') is-invalid @enderror" required>
                            @foreach(['credit_czk' => 'Kredit (haléře)', 'badge' => 'Odznak (ID)', 'discount_percent' => 'Sleva (%)'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('reward_type', $milestone->reward_type) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        @error('reward_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Hodnota odměny *</label>
                        <input type="number" name="reward_value" min="1"
                               value="{{ old('reward_value', $milestone->reward_value) }}"
                               class="form-control @error('reward_value') is-invalid @enderror" required>
                        @error('reward_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12">
                        <label class="form-label">Popis</label>
                        <textarea name="description" rows="2"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $milestone->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-span-12 md:col-span-4">
                        <label class="form-label">Pořadí</label>
                        <input type="number" name="sort_order" min="0"
                               value="{{ old('sort_order', $milestone->sort_order ?? 0) }}"
                               class="form-control">
                    </div>
                    <div class="col-span-12 md:col-span-4 flex items-end">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                   @checked(old('is_active', $milestone->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Aktivní</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                    <a href="{{ route('admin.loyalty.index') }}" class="btn btn-outline-secondary btn-sm">Zpět</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
