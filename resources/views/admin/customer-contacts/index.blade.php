@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Kontakty';
    $breadcrumbItems = [
        'Zákazníci' => route('admin.customers.index'),
        ($customer->company_name ?: $customer->email) => route('admin.customers.show', $customer),
        'Kontakty' => '',
    ];
@endphp

@section('title', 'Kontakty zákazníka')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-3">
        {{-- Add contact form --}}
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header card-no-border"><h5>Přidat kontakt</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.customer-contacts.store', $customer) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Jméno *</label>
                            <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">E-mail *</label>
                            <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Telefon</label>
                            <input type="text" name="phone" class="form-control form-control-sm"
                                   value="{{ old('phone') }}" placeholder="+420 123 456 789">
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Role</label>
                            <select name="role" class="form-select form-select-sm">
                                @foreach(\App\Domains\Customer\Models\CustomerContact::ROLES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('role') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Poznámka</label>
                            <input type="text" name="note" class="form-control form-control-sm" value="{{ old('note') }}">
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="receives_invoices" value="1"
                                   id="recInv" @checked(old('receives_invoices'))>
                            <label class="form-check-label f-12" for="recInv">Dostává faktury</label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="receives_notifications" value="1"
                                   id="recNotif" @checked(old('receives_notifications'))>
                            <label class="form-check-label f-12" for="recNotif">Dostává notifikace</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1"
                                   id="isPrimary" @checked(old('is_primary'))>
                            <label class="form-check-label f-12" for="isPrimary">Primární kontakt</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-full">Přidat kontakt</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Contact list --}}
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border">
                    <h5>Kontakty ({{ $contacts->count() }})</h5>
                </div>
                <div class="card-body pt-0">
                    @if($contacts->isEmpty())
                        <p class="text-center f-light py-4">Žádné kontakty. Přidejte první kontakt.</p>
                    @else
                        @foreach($contacts as $contact)
                        <div class="flex items-start gap-3 py-3 border-bottom">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="f-w-500">{{ $contact->name }}</span>
                                    @if($contact->is_primary)
                                        <span class="badge badge-light-success f-10">Primární</span>
                                    @endif
                                    <span class="badge badge-light-secondary f-10">{{ $contact->roleLabel() }}</span>
                                </div>
                                <div class="flex gap-3 mt-1 f-12 f-light">
                                    <span><i data-feather="mail" style="width:11px;height:11px;"></i> {{ $contact->email }}</span>
                                    @if($contact->phone)
                                        <span><i data-feather="phone" style="width:11px;height:11px;"></i> {{ $contact->phone }}</span>
                                    @endif
                                </div>
                                <div class="flex gap-2 mt-1 f-11">
                                    @if($contact->receives_invoices)
                                        <span class="badge badge-light-primary">Faktury</span>
                                    @endif
                                    @if($contact->receives_notifications)
                                        <span class="badge badge-light-info">Notifikace</span>
                                    @endif
                                    @if($contact->note)
                                        <span class="f-light">{{ $contact->note }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <button type="button" class="btn btn-outline-secondary btn-xs"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editContactModal{{ $contact->id }}">
                                    Upravit
                                </button>
                                <form method="POST"
                                      action="{{ route('admin.customer-contacts.destroy', [$customer, $contact]) }}"
                                      onsubmit="return confirm('Smazat kontakt?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-xs">×</button>
                                </form>
                            </div>
                        </div>

                        {{-- Edit modal --}}
                        <div class="modal fade" id="editContactModal{{ $contact->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST"
                                      action="{{ route('admin.customer-contacts.update', [$customer, $contact]) }}"
                                      class="modal-content">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h6 class="modal-title">Upravit kontakt</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label class="form-label f-12">Jméno *</label>
                                            <input type="text" name="name" class="form-control form-control-sm"
                                                   value="{{ $contact->name }}" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label f-12">E-mail *</label>
                                            <input type="email" name="email" class="form-control form-control-sm"
                                                   value="{{ $contact->email }}" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label f-12">Telefon</label>
                                            <input type="text" name="phone" class="form-control form-control-sm"
                                                   value="{{ $contact->phone }}">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label f-12">Role</label>
                                            <select name="role" class="form-select form-select-sm">
                                                @foreach(\App\Domains\Customer\Models\CustomerContact::ROLES as $key => $label)
                                                    <option value="{{ $key }}" @selected($contact->role === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label f-12">Poznámka</label>
                                            <input type="text" name="note" class="form-control form-control-sm"
                                                   value="{{ $contact->note }}">
                                        </div>
                                        <div class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" name="receives_invoices" value="1"
                                                   @checked($contact->receives_invoices)>
                                            <label class="form-check-label f-12">Dostává faktury</label>
                                        </div>
                                        <div class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" name="receives_notifications" value="1"
                                                   @checked($contact->receives_notifications)>
                                            <label class="form-check-label f-12">Dostává notifikace</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="is_primary" value="1"
                                                   @checked($contact->is_primary)>
                                            <label class="form-check-label f-12">Primární kontakt</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
