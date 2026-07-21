{{-- Session flash + validation error block shared by panel/admin pages.
     N171: aria-live so a screen reader announces the outcome of an action
     without the user having to hunt for it. status is polite; errors are
     assertive — a failed submit is worth interrupting for. --}}
@if(session('status'))
    <div class="alert alert-light-success" role="status" aria-live="polite">{{ session('status') }}</div>
@endif

@if(session('payment_failed'))
    <div class="alert alert-light-warning" role="alert" aria-live="assertive">{{ session('payment_failed') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-light-danger" role="alert" aria-live="assertive">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
