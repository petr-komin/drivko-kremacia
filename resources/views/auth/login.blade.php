@extends('layouts.app')

@section('title', __('Přihlášení'))
@section('inline-errors', true)

@section('content')
<div class="row justify-content-center pt-5">
    <div class="col-sm-9 col-md-6 col-lg-4">
        <div class="text-center mb-4">
            <div class="display-6 text-brown"><i class="bi bi-tree-fill"></i></div>
            <h1 class="h4 fw-bold">Drivko · urny</h1>
            <p class="text-muted small mb-0">{{ __('Zakázky uren s otiskem tlapky') }}</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('login.post') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">{{ __('E-mail') }}</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" autocomplete="username"
                               class="form-control @error('email') is-invalid @enderror" required autofocus>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">{{ __('Heslo') }}</label>
                        <input type="password" name="password" id="password" autocomplete="current-password" class="form-control" required>
                    </div>
                    <button class="btn btn-brown w-100">{{ __('Přihlásit') }}</button>
                </form>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">
            {{ __('Máte přístupový odkaz? Stačí ho otevřít – přihlásí vás sám.') }}<br>
            {{ __('Odkaz ani heslo nemáte? Ozvěte se dílně Drivko.') }}
        </p>
    </div>
</div>
@endsection
