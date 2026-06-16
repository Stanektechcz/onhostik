@extends('layouts.panel')

@php($breadcrumbTitle = $post->exists ? 'Upravit příspěvek' : 'Nový příspěvek')
@php($breadcrumbItems = ['Blog' => route('admin.blog.index'), $breadcrumbTitle => ''])

@section('title', $post->exists ? 'Upravit příspěvek' : 'Nový příspěvek')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="row">
            <div class="col-xl-8">
                <x-panel.card :title="$post->exists ? 'Upravit: ' . $post->title : 'Nový příspěvek'">
                    <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}"
                          id="blog-form">
                        @csrf
                        @if($post->exists) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label f-w-500">Název *</label>
                                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $post->title) }}" required>
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label f-w-500">Kategorie</label>
                                <input type="text" name="category" class="form-control"
                                       value="{{ old('category', $post->category) }}" placeholder="news, tutorialy…">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label f-w-500">Perex (krátký popis)</label>
                                <textarea name="excerpt" rows="2" class="form-control">{{ old('excerpt', $post->excerpt) }}</textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label f-w-500 mb-0">Obsah (HTML)</label>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            onclick="togglePreview('body-editor', 'body-preview')">
                                        <i data-feather="eye" style="width:13px;height:13px"></i> Náhled
                                    </button>
                                </div>
                                <textarea id="body-editor" name="body" rows="20" class="form-control"
                                          style="font-family:monospace;font-size:13px">{{ old('body', $post->body) }}</textarea>
                                <div id="body-preview" class="border rounded p-3 mt-2 prose-content" style="display:none; min-height: 200px;"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label f-w-500">Datum publikace</label>
                                <input type="datetime-local" name="published_at" class="form-control"
                                       value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-md-6 d-flex align-items-end pb-2">
                                <div class="form-check">
                                    <input type="hidden" name="is_published" value="0">
                                    <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published"
                                           @checked(old('is_published', $post->is_published))>
                                    <label for="is_published" class="form-check-label f-w-500">Publikovat</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="save" style="width:14px;height:14px"></i> Uložit
                            </button>
                            <a href="{{ route('admin.blog.index') }}" class="btn btn-light">Zpět</a>
                            @if($post->exists && $post->slug)
                                <a href="{{ route('front.blog.show', $post->slug) }}" target="_blank" class="btn btn-outline-secondary ms-auto">
                                    <i data-feather="external-link" style="width:13px;height:13px"></i> Zobrazit
                                </a>
                            @endif
                        </div>
                    </form>
                </x-panel.card>
            </div>

            {{-- Sidebar --}}
            <div class="col-xl-4">
                @if($post->exists)
                    <x-panel.card title="Informace">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="f-light ps-0">Slug</td>
                                <td><code class="f-12">{{ $post->slug }}</code></td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0">Autor</td>
                                <td>{{ $post->author?->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0">Vytvořeno</td>
                                <td class="f-12">{{ $post->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="f-light ps-0">Aktualizováno</td>
                                <td class="f-12">{{ $post->updated_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        </table>
                    </x-panel.card>
                @endif

                <x-panel.card title="Tipy pro obsah">
                    <p class="f-12 f-light mb-2">Obsah podporuje HTML. Příklady:</p>
                    <pre class="f-12 mb-1" style="font-size:11px">&lt;h2&gt;Nadpis&lt;/h2&gt;
&lt;p&gt;Odstavec textu&lt;/p&gt;
&lt;ul&gt;
  &lt;li&gt;Položka&lt;/li&gt;
&lt;/ul&gt;
&lt;pre&gt;&lt;code&gt;kód&lt;/code&gt;&lt;/pre&gt;
&lt;img src="..." alt="..."&gt;</pre>
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
