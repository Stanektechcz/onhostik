@extends('layouts.panel')

@php($breadcrumbTitle = 'Doplňky')
@php($breadcrumbItems = [__('panel.nav.services') => route('panel.services.index'), $service->label ?? $service->uuid => route('panel.services.show', $service), 'Doplňky' => ''])

@section('title', 'Doplňky — ' . ($service->label ?? $service->uuid))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            {{-- Active subscriptions --}}
            <div class="col-span-5 xl:col-span-12">
                <x-panel.card title="Aktivní doplňky">
                    @forelse($subscriptions as $sub)
                        <div class="flex items-center justify-between mb-2 p-2 rounded bg-light">
                            <div>
                                <span class="f-14 f-w-600">{{ $sub->addon->name }}</span>
                                <span class="badge badge-light-success ms-2">aktivní</span>
                                <p class="f-12 f-light mb-0">{{ $sub->addon->priceFormatted() }}</p>
                            </div>
                            <form action="{{ route('panel.services.addons.cancel', [$service, $sub]) }}" method="POST"
                                  onsubmit="return confirm('Zrušit doplněk?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Zrušit</button>
                            </form>
                        </div>
                    @empty
                        <p class="f-light f-12 mb-0">Žádné aktivní doplňky.</p>
                    @endforelse
                </x-panel.card>
            </div>

            {{-- Available addons catalogue --}}
            <div class="col-span-7 xl:col-span-12">
                <x-panel.card title="Dostupné doplňky">
                    @forelse($available as $addon)
                        <div class="flex items-start justify-between mb-3 pb-3 border-bottom">
                            <div class="grow pe-3">
                                <p class="f-14 f-w-600 mb-1">{{ $addon->name }}</p>
                                @if($addon->description)
                                    <p class="f-12 f-light mb-1">{{ $addon->description }}</p>
                                @endif
                                <span class="badge badge-light-primary">{{ $addon->priceFormatted() }}</span>
                            </div>
                            @if(in_array($addon->id, $subscribed))
                                <span class="badge badge-light-success">Aktivní</span>
                            @else
                                <form action="{{ route('panel.services.addons.activate', $service) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="addon_id" value="{{ $addon->id }}">
                                    <button type="submit" class="btn btn-sm btn-primary">Aktivovat</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="f-light f-12 mb-0">Žádné doplňky k dispozici.</p>
                    @endforelse
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
