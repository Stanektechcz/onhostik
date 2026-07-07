@extends('layouts.panel')

@section('title', 'Winback kampaně')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Nová kampaň">
                <form method="POST" action="{{ route('admin.winback-campaigns.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název kampaně</label>
                        <input type="text" name="name" class="form-control form-control-sm" maxlength="150" required>
                        @error('name')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cílový segment</label>
                        <select name="target_segment" class="form-select form-select-sm">
                            <option value="churned">Churned (odchozí)</option>
                            <option value="at_risk">At Risk (ohrožení)</option>
                            <option value="healthy">Healthy (zdraví)</option>
                            <option value="vip">VIP</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Zpráva zákazníkovi</label>
                        <textarea name="message" class="form-control form-control-sm" rows="4" required minlength="10" maxlength="2000"></textarea>
                        @error('message')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Vytvořit kampaň</button>
                </form>
            </x-panel.card>
        </div>

        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Kampaně">
                @if($campaigns->isEmpty())
                    <p class="text-muted">Žádné kampaně.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Segment</th>
                                <th class="text-end">Odesláno</th>
                                <th>Odesláno</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($campaigns as $campaign)
                            <tr>
                                <td class="f-w-500">{{ $campaign->name }}</td>
                                <td><span class="badge bg-secondary">{{ $campaign->target_segment }}</span></td>
                                <td class="text-end">{{ $campaign->sent_count }}</td>
                                <td class="f-12">
                                    @if($campaign->sent_at)
                                        <span class="text-success">{{ $campaign->sent_at->format('d.m.Y H:i') }}</span>
                                    @else
                                        <span class="text-muted">Neodesláno</span>
                                    @endif
                                </td>
                                <td>
                                    @if($campaign->sent_at === null)
                                    <form method="POST" action="{{ route('admin.winback-campaigns.send', $campaign) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-success" onclick="return confirm('Odeslat kampaň?')">
                                            Odeslat
                                        </button>
                                    </form>
                                    @else
                                        <small class="text-muted">Odesláno</small>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $campaigns->links() }}</div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
