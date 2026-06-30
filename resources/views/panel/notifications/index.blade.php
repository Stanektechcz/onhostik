@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.notifications');
    $breadcrumbItems = [__('panel.nav.notifications') => ''];
@endphp

@section('title', __('panel.nav.notifications'))

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Unread count widget --}}
        <div class="col-span-4 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                :label="__('panel.notifications.unread')"
                :value="$unreadCount"
                icon="bell"
                :color="$unreadCount > 0 ? 'warning' : 'secondary'"
            />
        </div>

        {{-- Mark all read --}}
        @if($unreadCount > 0)
        <div class="col-span-8 md:col-span-6 sm:col-span-12 d-flex align-items-center justify-content-end">
            <form method="POST" action="{{ route('panel.notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i data-feather="check-circle" style="width:14px;height:14px;"></i>
                    {{ __('panel.notifications.mark_all_read') }}
                </button>
            </form>
        </div>
        @endif

        {{-- Notifications list --}}
        <div class="col-span-12">
            <x-panel.card :title="__('panel.nav.notifications')">
                @if($notifications->isEmpty())
                    <div class="text-center py-5">
                        <i data-feather="bell-off" style="width:48px;height:48px;" class="text-muted mb-3 d-block mx-auto"></i>
                        <h6 class="f-light mt-2">{{ __('panel.common.empty') }}</h6>
                        <p class="f-light f-12 mb-0">{{ __('panel.notifications.no_notifications') }}</p>
                    </div>
                @else
                    @php
                        $iconMap = [
                            'check-circle'   => 'check-circle',
                            'file-text'      => 'file-text',
                            'alert-triangle' => 'alert-triangle',
                            'clock'          => 'clock',
                            'server'         => 'server',
                            'message-circle' => 'message-circle',
                            'user-check'     => 'user-check',
                            'gift'           => 'gift',
                        ];
                        $colorMap = [
                            'success' => 'badge-light-success txt-success',
                            'primary' => 'badge-light-primary txt-primary',
                            'warning' => 'badge-light-warning txt-warning',
                            'danger'  => 'badge-light-danger txt-danger',
                            'info'    => 'badge-light-info txt-info',
                        ];
                    @endphp

                    <div class="notification-wrapper">
                        @foreach($notifications as $notification)
                            @php
                                $d      = $notification->data ?? [];
                                $icon   = $iconMap[$d['icon'] ?? ''] ?? 'bell';
                                $color  = $colorMap[$d['color'] ?? ''] ?? 'badge-light-secondary txt-secondary';
                                $isRead = $notification->read_at !== null;
                            @endphp
                            <div class="d-flex align-items-start gap-3 py-3 border-bottom {{ $isRead ? '' : 'bg-light' }}"
                                 style="{{ $isRead ? '' : 'background:rgba(115,102,255,.03);border-left:3px solid rgba(115,102,255,.5);padding-left:12px;' }}">

                                {{-- Icon bubble --}}
                                <div class="flex-shrink-0">
                                    <span class="badge {{ $color }} rounded-circle p-2" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                                        <i data-feather="{{ $icon }}" style="width:16px;height:16px;"></i>
                                    </span>
                                </div>

                                {{-- Content --}}
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <p class="mb-1 f-14 {{ $isRead ? 'f-light' : 'f-w-600' }}">
                                            {{ $d['title'] ?? '' }}
                                            @if(!$isRead)
                                                <span class="badge badge-light-primary f-10 ms-1">{{ __('panel.notifications.new') }}</span>
                                            @endif
                                        </p>
                                        <span class="f-light f-11 text-nowrap">{{ $notification->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="mb-1 f-13 f-light">{{ $d['body'] ?? '' }}</p>
                                    <div class="d-flex gap-2 mt-1">
                                        @if(!empty($d['url']) && $d['url'] !== '#')
                                            <a href="{{ $d['url'] }}" class="btn btn-outline-primary btn-xs">
                                                <i data-feather="arrow-right" style="width:11px;height:11px;"></i>
                                                {{ __('panel.common.detail') }}
                                            </a>
                                        @endif
                                        @if(!$isRead)
                                            <form method="POST"
                                                  action="{{ route('panel.notifications.read', ['id' => $notification->id]) }}"
                                                  style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-secondary">
                                                    <i data-feather="check" style="width:11px;height:11px;"></i>
                                                    {{ __('panel.notifications.mark_read') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        {{ $notifications->links() }}
                    </div>
                @endif
            </x-panel.card>
        </div>

    </div>
</div>
@endsection
