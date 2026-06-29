@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.ai');
    $breadcrumbItems = [__('panel.nav.ai') => ''];
@endphp

@section('title', __('panel.nav.ai'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-5 xl:col-span-12">
                <x-panel.card :title="__('panel.nav.ai')" :subtitle="__('panel.ai.mock_note')">
                    <form method="POST" action="{{ route('panel.ai.run') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="ai-feature">{{ __('panel.ai.feature') }}</label>
                            <select id="ai-feature" name="feature" class="form-select">
                                @foreach($features as $feature)
                                    <option value="{{ $feature }}">{{ __("panel.ai.features.$feature") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="ai-text">{{ __('panel.ai.input') }}</label>
                            <textarea id="ai-text" name="text" class="form-control" rows="4" required minlength="3" maxlength="2000">{{ old('text') }}</textarea>
                            @error('text')<div class="text-danger f-12">{{ $message }}</div>@enderror
                            @error('feature')<div class="text-danger f-12">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            {{ __('panel.ai.run') }}
                            <span class="badge badge-light-warning ms-1">{{ __('panel.admin.mock_badge') }}</span>
                        </button>
                    </form>
                </x-panel.card>
            </div>

            <div class="col-span-7 xl:col-span-12">
                <x-panel.card :title="__('panel.ai.history')">
                    @if($runs->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                    @else
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($runs as $run)
                                        @php
                                            $answer = collect($run->messages)
                                                ->firstWhere('role', 'assistant')?->content;
                                        @endphp
                                        <li>
                                            <div class="timeline-dot timeline-dot-primary"></div>
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <span class="f-w-600 f-14">{{ __("panel.ai.features.{$run->feature}") }}</span>
                                                <span class="f-light f-11 ms-2 text-nowrap">{{ $run->created_at?->format('d.m.Y H:i') }}</span>
                                            </div>
                                            <span class="badge badge-light-secondary f-10 mb-2">{{ $run->provider }}</span>
                                            @if($answer)
                                                <p class="mb-0 f-13 f-light" style="white-space: pre-line;">{{ $answer }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
