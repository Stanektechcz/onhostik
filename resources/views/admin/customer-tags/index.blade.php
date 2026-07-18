@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Štítky zákazníků';
    $breadcrumbItems = ['Zákazníci' => route('admin.customers.index'), 'Štítky' => ''];
@endphp

@section('title', 'Štítky zákazníků')

@section('content')
<div class="container-fluid">
    <div class="grid grid-cols-12">

        {{-- Left: tag list + create form --}}
        <div class="col-span-12 xl:col-span-4 col-span-12 lg:col-span-5">
            <div class="card">
                <div class="card-header card-no-border">
                    <h5>Správa štítků</h5>
                </div>
                <div class="card-body">
                    @if($tags->isEmpty())
                        <p class="f-light f-12 text-center py-3">Zatím žádné štítky.</p>
                    @else
                        <ul class="list-unstyled mb-4">
                            @foreach($tags as $tag)
                            <li class="flex items-center gap-2 py-2 border-bottom">
                                <span class="badge rounded-full px-3 py-2"
                                      style="{{ $tag->colorBadgeStyle() }}">
                                    {{ $tag->name }}
                                </span>
                                <small class="f-light ms-1">{{ $tag->customer_count }} zákazníků</small>
                                <div class="ms-auto flex gap-1">
                                    <a href="{{ route('admin.customer-tags.index', ['tag' => $tag->id]) }}"
                                       class="btn btn-outline-primary btn-xs">Filtr</a>
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-xs"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editTagModal{{ $tag->id }}">
                                        Upravit
                                    </button>
                                    <form method="POST" action="{{ route('admin.customer-tags.destroy', $tag) }}"
                                          onsubmit="return confirm('Smazat štítek?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-xs">×</button>
                                    </form>
                                </div>
                            </li>

                            {{-- Edit modal --}}
                            <div class="modal fade" id="editTagModal{{ $tag->id }}" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <form method="POST" action="{{ route('admin.customer-tags.update', $tag) }}" class="modal-content">
                                        @csrf @method('PUT')
                                        <div class="modal-header">
                                            <h6 class="modal-title">Upravit štítek</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-2">
                                                <label class="form-label f-12">Název</label>
                                                <input type="text" name="name" class="form-control form-control-sm"
                                                       value="{{ $tag->name }}" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label f-12">Barva (hex)</label>
                                                <div class="flex gap-2">
                                                    <input type="color" name="color" class="form-control form-control-color"
                                                           value="{{ $tag->color }}" style="width:50px;">
                                                    <input type="text" name="color" class="form-control form-control-sm"
                                                           value="{{ $tag->color }}" pattern="^#[0-9a-fA-F]{6}$">
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label f-12">Popis</label>
                                                <input type="text" name="description" class="form-control form-control-sm"
                                                       value="{{ $tag->description }}">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Create new tag form --}}
                    <h6 class="f-w-500 mb-3">Nový štítek</h6>
                    <form method="POST" action="{{ route('admin.customer-tags.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Název *</label>
                            <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="VIP, At-Risk, Enterprise…" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Barva *</label>
                            <div class="flex gap-2 items-center">
                                <input type="color" name="color" class="form-control form-control-color"
                                       value="{{ old('color', '#0d6efd') }}" style="width:50px;">
                                <div class="flex gap-1 flex-wrap">
                                    @foreach(['#0d6efd','#198754','#dc3545','#ffc107','#6f42c1','#fd7e14','#20c997','#6c757d'] as $preset)
                                        <button type="button" class="btn btn-xs" style="background:{{ $preset }};width:22px;height:22px;padding:0;"
                                                onclick="this.closest('form').querySelector('[type=color]').value='{{ $preset }}'"></button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Popis</label>
                            <input type="text" name="description" class="form-control form-control-sm"
                                   value="{{ old('description') }}" placeholder="Volitelný popis…">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-full">Vytvořit štítek</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right: filtered customers --}}
        <div class="col-span-12 xl:col-span-8 col-span-12 lg:col-span-7">
            @php $activeTag = $tags->firstWhere('id', $tagId); @endphp

            @if($activeTag)
            <div class="card">
                <div class="card-header card-no-border flex items-center gap-2">
                    <h5 class="mb-0">
                        Zákazníci se štítkem
                        <span class="badge rounded-full ms-1" style="{{ $activeTag->colorBadgeStyle() }}">
                            {{ $activeTag->name }}
                        </span>
                    </h5>
                    <span class="badge bg-secondary ms-auto">{{ $customers->total() }}</span>
                </div>
                <div class="card-body pt-0">
                    @if($customers->isEmpty())
                        <p class="text-center f-light py-4">Žádní zákazníci s tímto štítkem.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Zákazník</th>
                                        <th>Email</th>
                                        <th>Štítky</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customers as $customer)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.customers.show', $customer) }}" class="f-w-500">
                                                {{ $customer->company_name ?: $customer->user?->name }}
                                            </a>
                                        </td>
                                        <td class="f-light f-12">{{ $customer->email }}</td>
                                        <td>
                                            @foreach($customer->tags as $t)
                                                <span class="badge rounded-full me-1" style="{{ $t->colorBadgeStyle() }}">
                                                    {{ $t->name }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td class="text-right">
                                            <form method="POST"
                                                  action="{{ route('admin.customer-tags.detach', [$customer, $activeTag]) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-xs">Odebrat</button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $customers->withQueryString()->links() }}
                    @endif
                </div>
            </div>
            @else
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="tag" style="width:40px;height:40px;" class="text-muted mb-3 block mx-auto"></i>
                    <h6 class="f-light">Vyberte štítek vlevo pro filtrování zákazníků</h6>
                    <p class="f-light f-12">Nebo přiřaďte štítky přímo z detailu zákazníka.</p>
                </div>
            </div>
            @endif
        </div>

    </div>
</div>
@endsection
