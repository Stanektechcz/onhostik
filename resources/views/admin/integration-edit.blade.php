@extends('layouts.panel')

@php($breadcrumbTitle = $integration->label)
@php($breadcrumbItems = [__('panel.nav.admin_integrations') => route('admin.integrations.index'), $integration->label => ''])

@section('title', $integration->label)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        @if(session('integration_error'))
            <div class="alert alert-light-danger" role="alert">{{ session('integration_error') }}</div>
        @endif

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-7 xl:col-span-12">
                <x-panel.card :title="$integration->label" :subtitle="__('panel.admin.credentials_hint')">
                    <form method="POST" action="{{ route('admin.integrations.update', $integration) }}">
                        @csrf
                        @method('PUT')

                        <div class="d-flex gap-4 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="int-active" @checked($integration->is_active)>
                                <label class="form-check-label" for="int-active">{{ __('panel.admin.active') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mock_mode" value="1" id="int-mock" @checked($integration->mock_mode)>
                                <label class="form-check-label" for="int-mock">{{ __('panel.admin.mock_mode') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="dry_run" value="1" id="int-dry" @checked($integration->dry_run)>
                                <label class="form-check-label" for="int-dry">{{ __('panel.admin.dry_run') }}</label>
                            </div>
                        </div>

                        @foreach($fields as $field)
                            <div class="mb-3">
                                <label class="form-label f-12 f-light" for="cred-{{ $field }}">{{ $field }}</label>
                                <input id="cred-{{ $field }}" type="password" name="credentials[{{ $field }}]"
                                       class="form-control" autocomplete="new-password"
                                       placeholder="{{ $masked[$field] ?? '' }}">
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary">{{ __('panel.admin.save') }}</button>
                    </form>
                </x-panel.card>
            </div>

            <div class="col-span-5 xl:col-span-12">
                <x-panel.card :title="__('panel.admin.health')">
                    <p class="mb-1"><span class="f-light">{{ __('panel.admin.last_success') }}:</span> {{ $integration->last_success_at?->format('d.m.Y H:i') ?? '—' }}</p>
                    <p class="mb-1"><span class="f-light">{{ __('panel.admin.last_error') }}:</span> {{ $integration->last_error_at?->format('d.m.Y H:i') ?? '—' }}</p>
                    @if($integration->last_error_message)
                        <p class="f-light f-12 mb-3">{{ $integration->last_error_message }}</p>
                    @endif

                    <form method="POST" action="{{ route('admin.integrations.test', $integration) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary">{{ __('panel.admin.test_connection') }}</button>
                    </form>

                    @if(!empty($masked))
                        <div class="border-top pt-3 mt-3">
                            <h6 class="f-light f-12">{{ __('panel.admin.credentials_masked') }}</h6>
                            @foreach($masked as $key => $value)
                                <p class="mb-1 f-12"><span class="f-light">{{ $key }}:</span> <code>{{ $value }}</code></p>
                            @endforeach
                        </div>
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
