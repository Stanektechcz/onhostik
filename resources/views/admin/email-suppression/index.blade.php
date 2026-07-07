@extends('layouts.panel')

@section('title', 'Potlačení e-mailů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Zákazníci s potlačeným e-mailem">
        @if($suppressed->isEmpty())
            <p class="text-muted">Žádní zákazníci s potlačeným e-mailem.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Zákazník</th>
                        <th>E-mail</th>
                        <th>Potlačeno od</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppressed as $user)
                    <tr>
                        <td class="f-w-500">{{ $user->customer?->display_name ?? $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td class="f-12">{{ $user->email_suppressed_at->format('d.m.Y H:i') }}</td>
                        <td>
                            @if($user->customer)
                            <form method="POST" action="{{ route('admin.email-suppression.unsuppress', $user->customer) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-xs btn-outline-success">Odebrat potlačení</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $suppressed->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
