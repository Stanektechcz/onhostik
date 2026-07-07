@extends('layouts.panel')
@section('title', 'Migrační dávky služeb')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="row g-4">
        <div class="col-md-8">
            <x-panel.card title="Migrační dávky služeb">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Zdrojový server</th>
                                <th>Cílový server</th>
                                <th>Počet služeb</th>
                                <th>Stav</th>
                                <th>Vytvořeno</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($batches as $batch)
                            <tr>
                                <td>{{ $batch->name }}</td>
                                <td>#{{ $batch->source_server_id }}</td>
                                <td>#{{ $batch->target_server_id }}</td>
                                <td>{{ is_array($batch->service_ids) ? count($batch->service_ids) : 0 }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($batch->status) {
                                            'pending'   => 'secondary',
                                            'running'   => 'warning',
                                            'completed' => 'success',
                                            'failed'    => 'danger',
                                            default     => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}">{{ $batch->status }}</span>
                                </td>
                                <td>{{ $batch->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Žádné záznamy.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $batches->links() }}</div>
            </x-panel.card>
        </div>
        <div class="col-md-4">
            <x-panel.card title="Nová migrační dávka">
                <form method="POST" action="{{ route('admin.service-migration-batches.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="100" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Zdrojový server ID</label>
                        <input type="number" name="source_server_id" class="form-control @error('source_server_id') is-invalid @enderror" value="{{ old('source_server_id') }}" required>
                        @error('source_server_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cílový server ID</label>
                        <input type="number" name="target_server_id" class="form-control @error('target_server_id') is-invalid @enderror" value="{{ old('target_server_id') }}" required>
                        @error('target_server_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ID služeb (čárkou oddělená)</label>
                        <input type="text" name="service_ids_raw" class="form-control @error('service_ids') is-invalid @enderror" value="{{ old('service_ids_raw') }}" placeholder="1,2,3,4" required>
                        <div class="form-text">Zadejte ID služeb oddělená čárkou.</div>
                        @error('service_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Vytvořit dávku</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
