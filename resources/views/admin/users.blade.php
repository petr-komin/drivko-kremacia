@extends('layouts.app')

@section('title', __('Uživatelé'))

@section('content')
<h1 class="h4 fw-bold mb-1">{{ __('Uživatelé') }}</h1>
<p class="text-muted small mb-3">
    {{ __('Přihlášení funguje přístupovým odkazem (bez hesla), heslem, nebo obojím.') }}
    {{ __('Odkaz otevřený v prohlížeči přihlásí na dlouho – stačí ho otevřít jednou na každém zařízení.') }}
    {{ __('Jazyk účtu platí i pro e-maily, které dostává.') }}
</p>

@if(session('access_link'))
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1"><i class="bi bi-link-45deg me-1"></i>{{ __('Přístupový odkaz') }}</div>
        <div class="input-group mb-2">
            <input type="text" readonly class="form-control font-monospace small" id="access-link" value="{{ session('access_link')['url'] }}">
            <button class="btn btn-brown" type="button" data-copy="access-link"><i class="bi bi-clipboard me-1"></i>{{ __('Kopírovat') }}</button>
        </div>
        <div class="small">{{ __('Kdo má odkaz, je přihlášený – nepřeposílejte ho dál než komu patří. Když unikne, vygenerujte nový, starý tím přestane platit.') }}
            {{ __('Později ho najdete znovu u účtu pod „Zobrazit odkaz“.') }}</div>
    </div>
@endif

@push('scripts')
<script>
    document.querySelectorAll('[data-copy]').forEach(btn => btn.onclick = async () => {
        const input = document.getElementById(btn.dataset.copy);
        try { await navigator.clipboard.writeText(input.value); } catch { input.select(); document.execCommand('copy'); }
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>' + @json(__('Zkopírováno'));
    });
</script>
@endpush

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h6 fw-bold">{{ __('Nový účet') }}</h2>
        <form method="POST" action="{{ route('users.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><label class="form-label small">{{ __('Jméno') }}</label><input name="name" class="form-control" required value="{{ old('name') }}" placeholder="{{ __('např. Kremace') }}"></div>
            <div class="col-md-3"><label class="form-label small">{{ __('E-mail (pro upozornění)') }}</label><input type="email" name="email" class="form-control" required value="{{ old('email') }}"></div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('Role') }}</label>
                <select name="role" class="form-select">
                    @foreach(\App\Models\User::ROLES as $code => $label)
                        <option value="{{ $code }}" @selected(old('role', 'client') === $code)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small">{{ __('Jazyk') }}</label>
                <select name="locale" class="form-select">
                    @foreach(config('kremacia.locales') as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', 'cs') === $code)>{{ strtoupper($code) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small">{{ __('Heslo (nepovinné)') }}</label><input type="text" name="password" class="form-control" autocomplete="new-password" placeholder="{{ __('jen odkaz') }}"></div>
            <div class="col-md-1"><button class="btn btn-brown w-100">{{ __('Založit + odkaz') }}</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        @foreach($users as $user)
            <div class="list-group-item py-3 {{ $user->active ? '' : 'bg-light text-muted' }}">
                <form method="POST" action="{{ route('users.update', $user) }}" class="row g-2 align-items-end">
                    @csrf @method('PUT')
                    <div class="col-md-3"><label class="form-label small mb-0">{{ __('Jméno') }}</label><input name="name" class="form-control form-control-sm" value="{{ $user->name }}" required></div>
                    <div class="col-md-2"><label class="form-label small mb-0">{{ __('E-mail') }}</label><input type="email" name="email" class="form-control form-control-sm" value="{{ $user->email }}" required></div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">{{ __('Role') }}</label>
                        <select name="role" class="form-select form-select-sm">
                            @foreach(\App\Models\User::ROLES as $code => $label)
                                <option value="{{ $code }}" @selected($user->role === $code)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-0">{{ __('Jazyk') }}</label>
                        <select name="locale" class="form-select form-select-sm">
                            @foreach(config('kremacia.locales') as $code => $label)
                                <option value="{{ $code }}" @selected($user->preferredLocale() === $code)>{{ strtoupper($code) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label small mb-0">{{ $user->password ? __('Nové heslo') : __('Heslo') }}</label><input type="text" name="password" class="form-control form-control-sm" placeholder="{{ $user->password ? __('beze změny') : __('nemá – jen odkaz') }}" autocomplete="new-password"></div>
                    <div class="col-6 col-md-1">
                        <div class="form-check">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" class="form-check-input" id="active{{ $user->id }}" @checked($user->active)>
                            <label class="form-check-label small" for="active{{ $user->id }}">{{ __('Aktivní') }}</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-1"><button class="btn btn-sm btn-outline-brown w-100">{{ __('Uložit') }}</button></div>
                </form>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small">
                    <i class="bi bi-link-45deg"></i>
                    @if($user->hasAccessLink())
                        <span>{{ __('Odkaz platí od') }} {{ $user->access_token_created_at->format('j. n. Y') }}</span>
                        @if($user->accessLink())
                            <button class="btn btn-sm btn-outline-brown py-0" type="button" data-bs-toggle="collapse" data-bs-target="#link{{ $user->id }}">{{ __('Zobrazit odkaz') }}</button>
                        @endif
                    @else
                        <span class="text-muted">{{ __('Bez přístupového odkazu') }}</span>
                    @endif
                    <form method="POST" action="{{ route('users.link', $user) }}"
                          @if($user->hasAccessLink()) onsubmit="return confirm(@js(__('Starý odkaz přestane platit a všechna zařízení se odhlásí. Pokračovat?')))" @endif>
                        @csrf
                        <button class="btn btn-sm btn-outline-brown py-0">{{ $user->hasAccessLink() ? __('Vygenerovat nový') : __('Vygenerovat odkaz') }}</button>
                    </form>
                    @if($user->hasAccessLink())
                        <form method="POST" action="{{ route('users.link.revoke', $user) }}" onsubmit="return confirm(@js(__('Zrušit odkaz a odhlásit všechna zařízení?')))">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0">{{ __('Zrušit') }}</button>
                        </form>
                    @endif
                </div>
                @if($user->accessLink())
                    <div class="collapse mt-2" id="link{{ $user->id }}">
                        <div class="input-group input-group-sm">
                            <input type="text" readonly class="form-control font-monospace" id="link-url{{ $user->id }}" value="{{ $user->accessLink() }}">
                            <button class="btn btn-brown" type="button" data-copy="link-url{{ $user->id }}"><i class="bi bi-clipboard me-1"></i>{{ __('Kopírovat') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
