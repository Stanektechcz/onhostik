@extends('layouts.panel')

@php
    use App\Domains\Customer\Enums\CustomerRole;
    $breadcrumbTitle = 'Členové účtu';
    $breadcrumbItems = [__('panel.nav.account') => '#', 'Členové účtu' => ''];
@endphp

@section('title', 'Členové účtu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        {{-- Invite --}}
        <div class="card">
            <div class="card-header card-no-border">
                <div class="header-top">
                    <h5>Pozvat člena</h5>
                    <p class="f-m-light mt-1">
                        Pozvaný uživatel získá přístup ke správě služeb, domén a tiketů.
                        Fakturaci, platební metody a zrušení účtu spravuje pouze vlastník.
                    </p>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('panel.account.members.invite') }}" class="flex gap-2 items-start flex-wrap">
                    @csrf
                    <div class="flex-1" style="min-width:240px;">
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               placeholder="email@firma.cz" value="{{ old('email') }}" required
                               aria-label="E-mail pozvaného">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary text-white">
                        <i data-feather="user-plus" class="me-1" style="width:14px;height:14px;"></i>
                        Odeslat pozvánku
                    </button>
                </form>
            </div>
        </div>

        {{-- Members --}}
        <div class="card mt-4">
            <div class="card-header card-no-border">
                <div class="header-top"><h5>Členové s přístupem</h5></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Jméno</th>
                                <th>E-mail</th>
                                <th>Role</th>
                                <th class="text-right">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $member)
                                @php $role = CustomerRole::tryFrom($member->pivot->role ?? 'member'); @endphp
                                <tr>
                                    <td class="f-w-500">{{ $member->name }}</td>
                                    <td class="f-light">{{ $member->email }}</td>
                                    <td>
                                        <span class="badge {{ $role === CustomerRole::Owner ? 'badge-light-primary' : 'badge-light-secondary' }}">
                                            {{ $role?->label() ?? $member->pivot->role }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        @if ($role !== CustomerRole::Owner)
                                            <form method="POST" action="{{ route('panel.account.members.remove', $member) }}"
                                                  data-confirm="Opravdu odebrat přístup tomuto členovi?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light">
                                                    <i data-feather="user-x" style="width:14px;height:14px;"></i>
                                                    Odebrat
                                                </button>
                                            </form>
                                        @else
                                            <span class="f-12 f-light">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Pending invitations --}}
        @if ($invitations->isNotEmpty())
            <div class="card mt-4">
                <div class="card-header card-no-border">
                    <div class="header-top"><h5>Nevyřízené pozvánky</h5></div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>E-mail</th>
                                    <th>Platnost do</th>
                                    <th class="text-right">Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invitations as $invitation)
                                    <tr>
                                        <td class="f-w-500">{{ $invitation->email }}</td>
                                        <td class="f-light">{{ $invitation->expires_at->format('d.m.Y H:i') }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('panel.account.members.invitations.revoke', $invitation) }}"
                                                  data-confirm="Zrušit tuto pozvánku?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light">Zrušit</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
