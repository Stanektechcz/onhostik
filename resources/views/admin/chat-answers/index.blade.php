@extends('layouts.panel')

@section('title', 'Odpovědi chatu')

@php
    $breadcrumbTitle = 'Odpovědi chatu';
    $breadcrumbItems = ['Odpovědi chatu' => ''];
@endphp

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Znalostní báze chatu</h5>
                        <p class="f-m-light mt-1">
                            Chat odpovídá deterministicky z klíčových slov — bez externí AI.
                            Zde přidané odpovědi se slučují s vestavěnou bází; vyšší priorita
                            vyhrává při shodě. Sloupec <strong>Použito</strong> ukazuje, které
                            odpovědi zákazníci opravdu potkávají.
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Otázka</th>
                                    <th>Kategorie</th>
                                    <th>Priorita</th>
                                    <th>Použito</th>
                                    <th>Aktivní</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($answers as $answer)
                                    <tr>
                                        <td>
                                            <div class="f-w-500">{{ $answer->question }}</div>
                                            <div class="f-11 f-light">{{ Str::limit($answer->keywords, 70) }}</div>
                                        </td>
                                        <td class="f-12">{{ $answer->category }}</td>
                                        <td class="f-12">{{ $answer->priority }}</td>
                                        <td class="f-12">{{ $answer->hits }}×</td>
                                        <td>
                                            <span class="badge {{ $answer->is_active ? 'badge-light-success' : 'badge-light-secondary' }}">
                                                {{ $answer->is_active ? 'Ano' : 'Ne' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.chat-answers.destroy', $answer) }}"
                                                  data-confirm="Odstranit odpověď „{{ $answer->question }}“?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light">Odstranit</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">
                                        Zatím žádné vlastní odpovědi — chat používá vestavěnou bázi.
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Nová odpověď</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.chat-answers.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12">Kategorie</label>
                            <select name="category" class="form-select form-select-sm" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category['key'] }}">{{ $category['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Otázka (zobrazí se v našeptávači)</label>
                            <input type="text" name="question" class="form-control form-control-sm @error('question') is-invalid @enderror"
                                   value="{{ old('question') }}" placeholder="Jak změním PHP verzi?" required>
                            @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Klíčová slova (oddělená čárkou)</label>
                            <textarea name="keywords" class="form-control form-control-sm" rows="2"
                                      placeholder="php verze, změnit php, php 8" required>{{ old('keywords') }}</textarea>
                            <div class="f-11 f-light mt-1">Delší a přesnější fráze mají při shodě větší váhu.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Odpověď</label>
                            <textarea name="answer" class="form-control form-control-sm" rows="4" required>{{ old('answer') }}</textarea>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-3">
                            <div class="col-span-6">
                                <label class="form-label f-12">Odkaz — text</label>
                                <input type="text" name="link_label" class="form-control form-control-sm" value="{{ old('link_label') }}">
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12">Odkaz — URL</label>
                                <input type="text" name="link_url" class="form-control form-control-sm" value="{{ old('link_url') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Priorita (0–100)</label>
                            <input type="number" name="priority" class="form-control form-control-sm"
                                   value="{{ old('priority', 10) }}" min="0" max="100">
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="answer-active" checked>
                            <label class="form-check-label f-12" for="answer-active">Aktivní</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm text-white">Přidat odpověď</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
