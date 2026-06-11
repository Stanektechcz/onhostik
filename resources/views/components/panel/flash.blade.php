{{-- Session flash + validation error block shared by panel/admin pages --}}
@if(session('status'))
    <div class="alert alert-light-success" role="alert">{{ session('status') }}</div>
@endif

@if(session('payment_failed'))
    <div class="alert alert-light-warning" role="alert">{{ session('payment_failed') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-light-danger" role="alert">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
