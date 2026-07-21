@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Fronta úloh';
    $breadcrumbItems = ['Systém a nastavení' => '', 'Fronta úloh' => ''];
@endphp

@section('title', 'Fronta úloh | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if(! $available)
        <x-panel.card title="Fronta úloh">
            <x-panel.empty-state icon="server" title="Tabulky fronty nejsou k dispozici"
                subtitle="Migrace pro 'jobs' / 'failed_jobs' zatím neproběhly." />
        </x-panel.card>
    @else
        <div class="grid grid-cols-12 card-gap mb-3">
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <x-panel.card title="Čeká ve frontě">
                    <h4 class="f-w-600 mb-0">{{ $pendingCount }}</h4>
                    <p class="f-light f-12 mb-0">
                        @if($oldestPending)
                            Nejstarší: {{ \Carbon\Carbon::createFromTimestamp($oldestPending)->diffForHumans() }}
                        @else
                            Fronta je prázdná
                        @endif
                    </p>
                </x-panel.card>
            </div>
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <x-panel.card title="Neúspěšné">
                    <h4 class="f-w-600 mb-0 {{ $failedCount > 0 ? 'txt-danger' : '' }}">{{ $failedCount }}</h4>
                    <p class="f-light f-12 mb-0">Vyžadují pozornost</p>
                </x-panel.card>
            </div>
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <x-panel.card title="Zaseknuté">
                    <h4 class="f-w-600 mb-0 {{ $stuckCount > 0 ? 'txt-warning' : '' }}">{{ $stuckCount }}</h4>
                    <p class="f-light f-12 mb-0">Rezervované déle než 15 min</p>
                </x-panel.card>
            </div>
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <x-panel.card title="Fronty">
                    @forelse($byQueue as $queue => $total)
                        <div class="flex items-center justify-between f-12">
                            <span class="f-light">{{ $queue }}</span>
                            <span class="f-w-600">{{ $total }}</span>
                        </div>
                    @empty
                        <p class="f-light f-12 mb-0">Žádné čekající úlohy.</p>
                    @endforelse
                </x-panel.card>
            </div>
        </div>

        @if($stuckCount > 0)
            <div class="alert alert-light-warning f-12">
                Některé úlohy jsou rezervované déle než 15 minut — pravděpodobně spadl worker.
                Zřízení služeb je jištěno plánovanou úlohou <code>services:provision-pending</code>, která běží synchronně i bez workeru.
            </div>
        @endif

        <x-panel.card title="Neúspěšné úlohy" :subtitle="$failedCount . ' celkem'">
            @if($failedCount > 0)
                <div class="flex items-center gap-2 mb-3">
                    <form method="POST" action="{{ route('admin.queue.retry') }}"
                          data-confirm="Opakovat všechny neúspěšné úlohy?">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i data-feather="refresh-cw" style="width:13px;height:13px"></i> Opakovat vše
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.queue.forget') }}"
                          data-confirm="Opravdu smazat VŠECHNY záznamy neúspěšných úloh? Tuto akci nelze vrátit.">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i data-feather="trash-2" style="width:13px;height:13px"></i> Smazat vše
                        </button>
                    </form>
                </div>
            @endif

            @if($failed->isEmpty())
                <x-panel.empty-state icon="check-circle" title="Žádné neúspěšné úlohy"
                    subtitle="Fronta je v pořádku." />
            @else
                <div class="table-responsive theme-scrollbar">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Úloha</th>
                                <th>Fronta</th>
                                <th>Selhala</th>
                                <th>Chyba</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($failed as $job)
                                @php
                                    $payload = json_decode((string) $job->payload, true);
                                    $name    = is_array($payload) ? ($payload['displayName'] ?? '—') : '—';
                                    $error   = \Illuminate\Support\Str::limit((string) $job->exception, 160);
                                @endphp
                                <tr>
                                    <td class="f-w-500">{{ class_basename((string) $name) }}</td>
                                    <td class="f-12">{{ $job->queue }}</td>
                                    <td class="f-12">{{ $job->failed_at }}</td>
                                    <td class="f-12 f-light">{{ $error }}</td>
                                    <td class="text-right">
                                        <div class="flex gap-1 justify-end">
                                            <form method="POST" action="{{ route('admin.queue.retry') }}">
                                                @csrf
                                                <input type="hidden" name="uuid" value="{{ $job->uuid }}">
                                                <button type="submit" class="btn btn-outline-primary btn-xs">Opakovat</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.queue.forget') }}"
                                                  data-confirm="Smazat tento záznam?">
                                                @csrf
                                                <input type="hidden" name="uuid" value="{{ $job->uuid }}">
                                                <button type="submit" class="btn btn-outline-danger btn-xs">Smazat</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel.card>
    @endif
</div>
@endsection
