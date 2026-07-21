@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Recenze';
    $breadcrumbItems = ['Recenze' => ''];
@endphp

@section('title', 'Správa recenzí')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Recenze zákazníků">
        <x-slot name="headerRight">
            <div class="flex gap-1">
                @foreach(['pending' => 'Čekající', 'approved' => 'Schválené', 'rejected' => 'Zamítnuté'] as $key => $label)
                    <a href="{{ route('admin.reviews', ['status' => $key]) }}"
                       class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $label }} <span class="badge badge-light-secondary ms-1">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </div>
        </x-slot>

        @if($reviews->isEmpty())
            <x-panel.empty-state icon="star" title="Žádné recenze"
                subtitle="V tomto stavu nejsou žádné recenze." />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Zákazník</th>
                            <th>Produkt</th>
                            <th>Hodnocení</th>
                            <th>Recenze</th>
                            <th>Datum</th>
                            <th class="text-right">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reviews as $review)
                            <tr>
                                <td class="f-12">{{ $review->customer?->company_name ?? $review->customer?->user?->name ?? '—' }}</td>
                                <td class="f-12">{{ $review->product?->name ?? '—' }}</td>
                                <td class="text-warning" style="white-space:nowrap;letter-spacing:1px;" title="{{ $review->rating }}/5">
                                    {{ str_repeat('★', $review->rating) }}<span class="text-muted">{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                </td>
                                <td style="max-width:320px;">
                                    @if($review->title)<div class="f-w-600 f-12">{{ $review->title }}</div>@endif
                                    <div class="f-light f-12" style="white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($review->body, 180) }}</div>
                                </td>
                                <td class="f-light f-12">{{ $review->created_at->format('d.m.Y') }}</td>
                                <td class="text-right">
                                    @if($review->status === \App\Models\ServiceReview::STATUS_PENDING)
                                        <div class="flex gap-1 justify-end">
                                            <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success text-white">Schválit</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.reviews.reject', $review) }}"
                                                  data-confirm="Zamítnout tuto recenzi?">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Zamítnout</button>
                                            </form>
                                        </div>
                                    @else
                                        @php($badge = $review->status === 'approved' ? 'success' : 'secondary')
                                        <span class="badge badge-light-{{ $badge }}">{{ $review->status === 'approved' ? 'Schváleno' : 'Zamítnuto' }}</span>
                                        @if($review->moderator)
                                            <div class="f-light f-11">{{ $review->moderator->name }}</div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $reviews->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
