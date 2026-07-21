@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Changelog';
    $breadcrumbItems = ['Administrace' => '#', 'Changelog' => ''];
@endphp

@section('title', 'Changelog — správa')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-12">
            <x-panel.card title="Changelog — co je nového">
                <x-slot:headerRight>
                    <a href="{{ route('admin.product-updates.create') }}" class="btn btn-primary btn-sm text-white">
                        <i data-feather="plus" style="width:14px;height:14px;"></i>
                        Nový záznam
                    </a>
                </x-slot:headerRight>

                @if($updates->isEmpty())
                    <x-panel.empty-state
                        icon="gift"
                        title="Žádné záznamy"
                        subtitle="Vytvořte první záznam changelogu.">
                        <a href="{{ route('admin.product-updates.create') }}" class="btn btn-primary btn-sm text-white">
                            Nový záznam
                        </a>
                    </x-panel.empty-state>
                @else
                    <x-panel.data-table :headers="['Název', 'Kategorie', 'Verze', 'Stav', 'Publikováno', 'Akce']">
                        @foreach($updates as $update)
                            <tr>
                                <td class="f-w-500">{{ $update->title }}</td>
                                <td>
                                    <span class="badge badge-light-{{ $update->categoryColor() }} f-11">
                                        {{ $update->categoryLabel() }}
                                    </span>
                                </td>
                                <td class="f-12">{{ $update->version ?? '—' }}</td>
                                <td>
                                    @if($update->is_published && $update->published_at?->isFuture())
                                        <span class="badge badge-light-info f-11">Naplánováno</span>
                                    @elseif($update->is_published)
                                        <span class="badge badge-light-success f-11">Publikováno</span>
                                    @else
                                        <span class="badge badge-light-secondary f-11">Koncept</span>
                                    @endif
                                </td>
                                <td class="f-12">{{ $update->published_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="{{ route('admin.product-updates.edit', $update) }}"
                                           class="btn btn-outline-primary btn-xs">
                                            <i data-feather="edit-2" style="width:12px;height:12px;"></i>
                                        </a>
                                        <form method="POST"
                                              action="{{ route('admin.product-updates.destroy', $update) }}"
                                              data-confirm="Opravdu smazat záznam „{{ $update->title }}“?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs">
                                                <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>

                    <div class="mt-3">
                        {{ $updates->links() }}
                    </div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
