@extends('layouts.panel')

@php($breadcrumbTitle = 'Import zákazníků')
@php($breadcrumbItems = ['Zákazníci' => route('admin.customers.index'), 'Import CSV' => ''])

@section('title', 'Import zákazníků z CSV')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- Upload form --}}
        <div class="col-span-6 xl:col-span-12">
            <x-panel.card title="Nahrát CSV soubor">
                @if(isset($imported))
                    {{-- Results after import --}}
                    <div class="alert {{ $imported > 0 ? 'alert-light-success' : 'alert-light-secondary' }} f-12 mb-3">
                        <strong>Import dokončen:</strong>
                        {{ $imported }} importováno,
                        {{ $skipped ?? 0 }} přeskočeno.
                    </div>
                    @if(!empty($errors))
                        <div class="mb-3">
                            <p class="f-12 f-w-500 text-danger">Upozornění:</p>
                            <ul class="f-12 text-danger">
                                @foreach($errors as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-primary btn-sm">Zpět na zákazníky</a>
                    <a href="{{ route('admin.customers.import') }}" class="btn btn-outline-secondary btn-sm ms-2">Importovat znovu</a>
                @else
                    <p class="f-12 f-light mb-3">
                        Nahrajte CSV soubor s novými zákazníky. Maximum {{ 500 }} řádků.
                        Existující e-mailové adresy budou přeskočeny.
                    </p>

                    <form method="POST" action="{{ route('admin.customers.import.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">CSV soubor</label>
                            <input type="file" name="file" accept=".csv,text/csv"
                                   class="form-control form-control-sm @error('file') is-invalid @enderror" required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-feather="upload" style="width:13px;height:13px;"></i>
                            Importovat zákazníky
                        </button>
                    </form>
                @endif
            </x-panel.card>
        </div>

        {{-- Template / docs --}}
        <div class="col-span-6 xl:col-span-12">
            <x-panel.card title="Formát CSV souboru">
                <p class="f-12 f-light mb-2">Záhlaví (první řádek) musí obsahovat tyto sloupce:</p>

                <table class="table table-sm table-borderless f-12">
                    <thead>
                        <tr>
                            <th>Sloupec</th>
                            <th>Povinný</th>
                            <th>Popis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>name</code></td><td><span class="text-danger">Ano</span></td><td>Jméno uživatele</td></tr>
                        <tr><td><code>email</code></td><td><span class="text-danger">Ano</span></td><td>E-mailová adresa (musí být unikátní)</td></tr>
                        <tr><td><code>company_name</code></td><td class="text-muted">Ne</td><td>Název firmy</td></tr>
                        <tr><td><code>country_code</code></td><td class="text-muted">Ne</td><td>Kód země (např. CZ, SK, DE). Výchozí: CZ</td></tr>
                        <tr><td><code>phone</code></td><td class="text-muted">Ne</td><td>Telefonní číslo</td></tr>
                        <tr><td><code>type</code></td><td class="text-muted">Ne</td><td><code>person</code> nebo <code>company</code>. Výchozí: person</td></tr>
                    </tbody>
                </table>

                <hr class="my-3">

                <p class="f-12 f-w-500 mb-1">Příklad CSV:</p>
                <code class="f-11 d-block p-2 rounded" style="background:rgba(0,0,0,.05);white-space:pre;">name,email,company_name,country_code,phone,type
Jan Novák,jan.novak@example.com,,CZ,+420123456789,person
Acme s.r.o.,info@acme.cz,Acme s.r.o.,CZ,+420987654321,company
Marie Horáková,marie@example.com,,,, </code>

                <div class="mt-3">
                    <p class="f-12 f-light mb-0">
                        <i data-feather="info" style="width:12px;height:12px;"></i>
                        Každý importovaný zákazník dostane náhodné heslo. Zákazník si může heslo resetovat přes „Zapomenuté heslo".
                    </p>
                </div>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
