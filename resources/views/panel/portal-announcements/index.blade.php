@extends('layouts.panel')

@section('title', 'Oznámení portálu')

@section('content')
<div class="grid grid-cols-12 justify-center">
    <div class="col-span-12 lg:col-span-8">
        <x-panel.flash />

        <x-panel.card title="Oznámení portálu">
            @if($announcements->isEmpty())
                <div class="text-center py-4">
                    <p class="text-muted mb-0">Žádná aktuální oznámení.</p>
                </div>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($announcements as $announcement)
                    <div class="alert alert-{{ $announcement->type }} mb-0" role="alert">
                        <div class="flex justify-between items-start">
                            <h6 class="alert-heading mb-1 font-bold">{{ $announcement->title }}</h6>
                            @if($announcement->published_at)
                            <small class="text-muted ms-3" style="white-space:nowrap;">
                                {{ $announcement->published_at->format('d.m.Y') }}
                            </small>
                            @endif
                        </div>
                        <p class="mb-0">{{ $announcement->body }}</p>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $announcements->links() }}</div>
            @endif
        </x-panel.card>
    </div>
</div>
@endsection
