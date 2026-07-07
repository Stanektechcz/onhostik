@extends('layouts.panel')

@section('title', 'Oznámení portálu')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <x-panel.flash />

        <x-panel.card title="Oznámení portálu">
            @if($announcements->isEmpty())
                <div class="text-center py-4">
                    <p class="text-muted mb-0">Žádná aktuální oznámení.</p>
                </div>
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach($announcements as $announcement)
                    <div class="alert alert-{{ $announcement->type }} mb-0" role="alert">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="alert-heading mb-1 fw-bold">{{ $announcement->title }}</h6>
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
