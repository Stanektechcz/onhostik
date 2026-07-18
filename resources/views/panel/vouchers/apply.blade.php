@extends('layouts.panel')

@section('title', 'Uplatnit voucher')

@section('content')
<div class="grid grid-cols-12 justify-center">
    <div class="col-span-12 lg:col-span-5">
        <x-panel.card title="Uplatnit voucher">
            <x-panel.flash />

            <form method="POST" action="{{ route('panel.voucher-application.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Kód voucheru <span class="text-danger">*</span></label>
                    <input type="text" name="code"
                        class="form-control form-control-lg @error('code') is-invalid @enderror"
                        value="{{ old('code') }}" maxlength="50"
                        placeholder="Zadejte kód voucheru" required autofocus>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-full">Uplatnit</button>
            </form>
        </x-panel.card>
    </div>
</div>
@endsection
