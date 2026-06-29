@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_ai');
    $breadcrumbItems = [__('panel.nav.admin_ai') => ''];
@endphp

@section('title', __('panel.nav.admin_ai'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            {{-- Left: run form + pending approvals --}}
            <div class="col-span-5 xl:col-span-12">
                <x-panel.card :title="__('panel.nav.admin_ai')" :subtitle="__('panel.ai.mock_note')">
                    <form method="POST" action="{{ route('admin.ai.run') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="aai-feature">{{ __('panel.ai.feature') }}</label>
                            <select id="aai-feature" name="feature" class="form-select">
                                @foreach($features as $feature)
                                    <option value="{{ $feature }}">{{ __("panel.ai.features.$feature") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="aai-text">{{ __('panel.ai.input') }}</label>
                            <textarea id="aai-text" name="text" class="form-control" rows="4" required minlength="3" maxlength="4000"></textarea>
                            @error('text')<div class="text-danger f-12">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="zap" style="width:14px;height:14px"></i>
                            {{ __('panel.ai.run') }}
                            <span class="badge badge-light-warning ms-1">{{ __('panel.admin.mock_badge') }}</span>
                        </button>
                    </form>
                </x-panel.card>

                {{-- Pending approvals --}}
                <x-panel.card :title="__('panel.admin.pending_approvals')">
                    @error('approval')<div class="alert alert-light-danger f-12 py-2">{{ $message }}</div>@enderror
                    @if($approvals->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                    @else
                        @foreach($approvals as $approval)
                            @php
                                $isPending = $approval->status === \App\Domains\Ai\Enums\ApprovalStatus::Pending;
                                $statusColor = match($approval->status->value ?? '') {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default    => 'warning',
                                };
                            @endphp
                            <div class="card card-no-border border rounded mb-2 {{ $isPending ? 'border-warning' : '' }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge badge-light-{{ $statusColor }} me-1">{{ $approval->status->value }}</span>
                                            <strong class="f-14">{{ $approval->action_type }}</strong>
                                        </div>
                                        <span class="f-light f-12 text-nowrap">{{ $approval->created_at?->format('d.m. H:i') }}</span>
                                    </div>
                                    <p class="f-light f-12 mb-2">
                                        <i data-feather="user" style="width:11px;height:11px"></i>
                                        {{ $approval->requester?->name ?? '—' }}
                                        @if($approval->reason)
                                            · {{ $approval->reason }}
                                        @endif
                                    </p>
                                    @if($isPending)
                                        <div class="d-flex gap-2">
                                            <form method="POST" action="{{ route('admin.ai.review', $approval) }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i data-feather="check" style="width:12px;height:12px"></i>
                                                    {{ __('panel.admin.approve') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.ai.review', $approval) }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="reject">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i data-feather="x" style="width:12px;height:12px"></i>
                                                    {{ __('panel.admin.reject') }}
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($approval->reviewer)
                                        <p class="f-12 f-light mb-0">
                                            <i data-feather="user-check" style="width:11px;height:11px"></i>
                                            {{ $approval->reviewer->name }} · {{ $approval->updated_at?->format('d.m. H:i') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </x-panel.card>
            </div>

            {{-- Right: runs timeline + templates + usage --}}
            <div class="col-span-7 xl:col-span-12">
                <x-panel.card :title="__('panel.admin.runs')">
                    @if($runs->isEmpty())
                        <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                    @else
                        <div class="activity-log">
                            <div class="basic-timeline">
                                <ul>
                                    @foreach($runs as $run)
                                        <li>
                                            <div class="timeline-dot-primary"></div>
                                            <div class="ms-4 pb-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <span class="badge badge-light-primary me-1">{{ $run->provider }}</span>
                                                        <span class="f-w-500">{{ __("panel.ai.features.{$run->feature}") }}</span>
                                                    </div>
                                                    <span class="f-light f-12 text-nowrap ms-2">{{ $run->created_at?->format('d.m. H:i') }}</span>
                                                </div>
                                                <p class="f-12 f-light mb-1 mt-1">
                                                    <i data-feather="user" style="width:11px;height:11px"></i>
                                                    {{ $run->user?->name ?? 'system' }}
                                                </p>
                                                @foreach($run->messages as $message)
                                                    @if($message->role === 'assistant')
                                                        <p class="mb-0 f-12 f-light" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($message->content, 200) }}</p>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                </x-panel.card>

                <div class="grid grid-cols-12 card-gap">
                    <div class="col-span-6 md:col-span-12">
                        <x-panel.card :title="__('panel.admin.templates')">
                            @if($templates->isEmpty())
                                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                            @else
                                @foreach($templates as $template)
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge badge-light-{{ $template->audience === 'admin' ? 'secondary' : 'primary' }}">{{ $template->audience }}</span>
                                        <span class="f-12">{{ $template->name }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </x-panel.card>
                    </div>
                    <div class="col-span-6 md:col-span-12">
                        <x-panel.card :title="__('panel.admin.usage_log')">
                            @if($usage->isEmpty())
                                <p class="f-light mb-0">{{ __('panel.common.empty') }}</p>
                            @else
                                @foreach($usage as $log)
                                    <div class="d-flex justify-content-between f-12 mb-1">
                                        <div>
                                            <span class="f-light">{{ $log->created_at?->format('d.m. H:i') }}</span>
                                            <span class="ms-1">{{ $log->feature }}</span>
                                        </div>
                                        <span class="f-light">
                                            <i data-feather="cpu" style="width:10px;height:10px"></i>
                                            {{ number_format($log->tokens_in + $log->tokens_out) }} tok
                                        </span>
                                    </div>
                                @endforeach
                            @endif
                        </x-panel.card>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
