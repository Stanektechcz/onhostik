@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Konverzace';
    $breadcrumbItems = ['Podpora' => route('admin.support.index'), 'Chat' => route('admin.chat.index'), 'Konverzace' => ''];
    $customerName = $conversation->customer?->company_name ?? $conversation->startedBy?->name ?? 'Neznámý';
@endphp

@section('title', 'Konverzace | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h5 class="mb-0">{{ $customerName }}</h5>
                        <p class="f-light f-12 mb-0">{{ $conversation->startedBy?->email }}</p>
                    </div>
                    <span class="badge badge-light-{{ $conversation->status->color() }}">{{ $conversation->status->label() }}</span>
                </div>

                {{-- Message thread (customer left, agent right, bot muted left) --}}
                <div class="chat-thread" id="chat-thread">
                    @foreach($conversation->messages as $m)
                        @if($m->role === 'system')
                            <div class="chat-system">{{ $m->body }}</div>
                        @else
                            @php
                                $bubble = match($m->role) { 'agent' => 'chat-msg--out', 'bot' => 'chat-msg--bot', default => 'chat-msg--in' };
                                $author = match($m->role) { 'agent' => $m->author?->name ?? 'Operátor', 'bot' => 'AI asistent', default => $customerName };
                            @endphp
                            <div class="chat-msg {{ $bubble }}">
                                <span class="chat-author">{{ $author }}</span>
                                {{ $m->body }}
                                @if(is_array($m->meta) && !empty($m->meta['attachment']['url']))
                                    @php $att = $m->meta['attachment']; @endphp
                                    <div class="chat-attach">
                                        @if(!empty($att['mime']) && str_starts_with($att['mime'], 'image/'))
                                            <a href="{{ $att['url'] }}" target="_blank"><img src="{{ $att['url'] }}" alt="{{ $att['name'] ?? 'příloha' }}"></a>
                                        @else
                                            <a href="{{ $att['url'] }}" target="_blank">📎 {{ $att['name'] ?? 'příloha' }}</a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Reply --}}
                @if($conversation->status->isOpen())
                    <form method="POST" action="{{ route('admin.chat.reply', $conversation) }}" class="mt-3" id="chat-reply-form">
                        @csrf
                        @error('body')<div class="text-danger f-12 mb-1">{{ $message }}</div>@enderror
                        <div class="flex gap-2">
                            <textarea name="body" rows="2" class="form-control" placeholder="Napište odpověď zákazníkovi…" required></textarea>
                            <button type="submit" class="btn btn-primary text-white shrink-0">
                                <i data-feather="send" style="width:14px;height:14px;"></i> Odeslat
                            </button>
                        </div>
                    </form>
                @else
                    <div class="alert alert-light-secondary mt-3 mb-0">Konverzace je uzavřená.</div>
                @endif
            </x-panel.card>
        </div>

        {{-- Side panel --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Detaily">
                <ul class="list-none p-0 m-0">
                    <li class="mb-2"><span class="f-light f-12 block">Zákazník</span>
                        @if($conversation->customer)
                            <a href="{{ route('admin.customers.show', $conversation->customer) }}">{{ $customerName }}</a>
                        @else {{ $customerName }} @endif
                    </li>
                    <li class="mb-2"><span class="f-light f-12 block">Operátor</span>{{ $conversation->agent?->name ?? 'Nepřiřazeno' }}</li>
                    <li class="mb-2"><span class="f-light f-12 block">Zahájeno</span>{{ $conversation->created_at?->format('d.m.Y H:i') }}</li>
                    <li class="mb-2"><span class="f-light f-12 block">Poslední aktivita</span>{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</li>
                </ul>

                <form method="POST" action="{{ route('admin.chat.to-ticket', $conversation) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary w-full">
                        <i data-feather="file-plus" style="width:14px;height:14px;"></i> Vytvořit ticket z konverzace
                    </button>
                </form>

                @if($conversation->status->isOpen())
                    <form method="POST" action="{{ route('admin.chat.close', $conversation) }}" class="mt-2"
                          data-confirm="Uzavřít konverzaci?">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-full">
                            <i data-feather="check-circle" style="width:14px;height:14px;"></i> Uzavřít konverzaci
                        </button>
                    </form>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function(){
    var thread=document.getElementById('chat-thread');
    if(thread) thread.scrollTop=thread.scrollHeight;
    // Refresh the thread while the agent isn't mid-typing, so customer replies appear.
    var ta=document.querySelector('#chat-reply-form textarea');
    setInterval(function(){ if(!ta || !ta.value.trim()) location.reload(); }, 20000);
})();
</script>
@endsection
