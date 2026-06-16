@extends('layouts.panel')

@php($breadcrumbTitle = $article->exists ? 'Upravit článek' : 'Nový článek')
@php($breadcrumbItems = ['Znalostní báze' => route('admin.kb.index'), $breadcrumbTitle => ''])

@section('title', $article->exists ? 'Upravit článek' : 'Nový článek')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="row">
            <div class="col-xl-8">
                <x-panel.card :title="$article->exists ? 'Upravit: ' . $article->title : 'Nový článek KB'">
                    <form method="POST"
                          action="{{ $article->exists ? route('admin.kb.update', $article) : route('admin.kb.store') }}">
                        @csrf
                        @if($article->exists) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label f-w-500">Název *</label>
                                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $article->title) }}" required>
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label f-w-500">Kategorie</label>
                                <input type="text" name="category" class="form-control"
                                       value="{{ old('category', $article->category) }}" placeholder="dns, email, ssl…">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label f-w-500">Pořadí</label>
                                <input type="number" name="sort_order" class="form-control"
                                       value="{{ old('sort_order', $article->sort_order ?? 0) }}" min="0">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label f-w-500">Perex</label>
                                <textarea name="excerpt" rows="2" class="form-control">{{ old('excerpt', $article->excerpt) }}</textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label f-w-500 mb-0">Obsah (HTML)</label>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            onclick="togglePreview('body-editor', 'body-preview')">
                                        <i data-feather="eye" style="width:13px;height:13px"></i> Náhled
                                    </button>
                                </div>
                                <textarea id="body-editor" name="body" rows="22" class="form-control"
                                          style="font-family:monospace;font-size:13px">{{ old('body', $article->body) }}</textarea>
                                <div id="body-preview" class="border rounded p-3 mt-2 prose-content" style="display:none; min-height:200px;"></div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="hidden" name="is_published" value="0">
                                    <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published"
                                           @checked(old('is_published', $article->is_published ?? true))>
                                    <label for="is_published" class="form-check-label f-w-500">Publikovat</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="save" style="width:14px;height:14px"></i> Uložit
                            </button>
                            <a href="{{ route('admin.kb.index') }}" class="btn btn-light">Zpět</a>
                            @if($article->exists && $article->slug)
                                <a href="{{ route('front.kb.show', $article->slug) }}" target="_blank"
                                   class="btn btn-outline-secondary ms-auto">
                                    <i data-feather="external-link" style="width:13px;height:13px"></i> Zobrazit
                                </a>
                            @endif
                        </div>
                    </form>
                </x-panel.card>
            </div>

            <div class="col-xl-4">
                @if($article->exists)
                    <x-panel.card title="Informace">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="f-light ps-0">Slug</td>
                                <td><code class="f-12">{{ $article->slug }}</code></td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0">Vytvořeno</td>
                                <td class="f-12">{{ $article->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0">Aktualizováno</td>
                                <td class="f-12">{{ $article->updated_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        </table>
                    </x-panel.card>
                @endif

                <x-panel.card title="Tipy pro obsah">
                    <p class="f-12 f-light mb-2">Obsah podporuje HTML. Příklady:</p>
                    <pre style="font-size:11px; white-space: pre-wrap;">&lt;h2&gt;Nadpis sekce&lt;/h2&gt;
&lt;p&gt;Odstavec textu.&lt;/p&gt;
&lt;ol&gt;
  &lt;li&gt;Krok 1&lt;/li&gt;
  &lt;li&gt;Krok 2&lt;/li&gt;
&lt;/ol&gt;
&lt;pre&gt;&lt;code&gt;příkaz&lt;/code&gt;&lt;/pre&gt;
&lt;div class="alert alert-info"&gt;Tip&lt;/div&gt;</pre>
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function togglePreview(editorId, previewId) {
    const editor  = document.getElementById(editorId);
    const preview = document.getElementById(previewId);
    if (preview.style.display === 'none') {
        preview.innerHTML = editor.value || '<em class="text-muted">Žádný obsah</em>';
        preview.style.display = 'block';
        editor.style.display  = 'none';
    } else {
        editor.style.display  = 'block';
        preview.style.display = 'none';
    }
}
</script>
@endpush
