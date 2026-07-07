@extends('layouts.panel')

@section('title', 'Analytika zákaznických segmentů')

@section('content')
<x-panel.flash />

<x-panel.card title="Analytika zákaznických segmentů">
    @if($tags->isEmpty())
        <p class="text-muted">Žádné štítky segmentů.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Štítek</th>
                        <th>Zákazníků</th>
                        <th>Popis</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tags as $tag)
                        <tr>
                            <td>
                                @if($tag->color)
                                    <span class="d-inline-block rounded-circle me-2"
                                          style="width:12px;height:12px;background-color:{{ $tag->color }};"></span>
                                @endif
                                {{ $tag->name }}
                            </td>
                            <td>{{ $pivotCounts[$tag->id]->customer_count ?? 0 }}</td>
                            <td>{{ $tag->description ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>
@endsection
