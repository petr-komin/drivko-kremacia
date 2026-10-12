<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#5c3d1e">
    <title>@yield('title', __('Zakázky')) – Drivko urny</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --brown: #5c3d1e; --brown-dark: #3d2b10; --sand: #f5ede0; --sand-dark: #e8d5b7; }
        body { background: #f7f4ef; color: var(--brown-dark); }
        .navbar-brand, .navbar .nav-link { color: #fff !important; }
        .navbar { background: var(--brown); }
        .navbar .nav-link.active { background: rgba(255,255,255,.15); border-radius: 6px; }
        .card { border: none; box-shadow: 0 1px 6px rgba(0,0,0,.07); }
        .btn-brown { background: var(--brown); color: #fff; border: none; }
        .btn-brown:hover, .btn-brown:focus { background: var(--brown-dark); color: #fff; }
        .btn-outline-brown { border-color: var(--brown); color: var(--brown); }
        .btn-outline-brown:hover { background: var(--brown); color: #fff; }
        /* Volby (radio jako tlačítka): vybraná výrazně, najetí myší jen jemně – ať se nesplete s výběrem */
        .btn-check + .btn-outline-brown:hover { background: var(--sand); color: var(--brown); }
        .btn-check:checked + .btn-outline-brown { background: var(--brown); color: #fff; border-color: var(--brown);
                                                  box-shadow: 0 0 0 .2rem rgba(92, 61, 30, .35); }
        .btn-check:focus-visible + .btn-outline-brown { box-shadow: 0 0 0 .2rem rgba(92, 61, 30, .5); }
        .text-brown { color: var(--brown); }
        .dropdown-item.active, .dropdown-item:active { background: var(--brown); }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .5rem; }
        .photo-grid .ph { position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: var(--sand); }
        .photo-grid .ph img { width: 100%; height: 100%; object-fit: cover; }
        .photo-grid .ph .noprev { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; font-size: .75rem; text-align: center; padding: .25rem; color: var(--brown); }
        .photo-grid .ph .ph-remove { position: absolute; top: 4px; right: 4px; }
        .msg { max-width: 85%; border-radius: 12px; padding: .6rem .85rem; }
        .msg-mine { background: var(--sand-dark); margin-left: auto; }
        .msg-other { background: #fff; }
        .msg-system { text-align: center; color: #8a7a66; font-size: .85rem; }
        .nav-tabs .nav-link { color: var(--brown); }
        .nav-tabs .nav-link.active { font-weight: 600; }
        .order-row { color: inherit; text-decoration: none; }
        .order-row:hover { background: #fbf8f3; }
        .order-thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; background: var(--sand); }
    </style>
    @stack('styles')
</head>
<body>
@auth
<nav class="navbar navbar-expand-md navbar-dark mb-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="{{ route('orders.index') }}"><i class="bi bi-tree-fill me-1"></i> Drivko · urny</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto gap-md-1">
                <li class="nav-item"><a class="nav-link px-2 {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}"><i class="bi bi-list-task me-1"></i>{{ __('Zakázky') }}</a></li>
                @if(auth()->user()->isAdmin())
                    <li class="nav-item"><a class="nav-link px-2 {{ request()->routeIs('report.*') ? 'active' : '' }}" href="{{ route('report.index') }}"><i class="bi bi-calendar-check me-1"></i>{{ __('Přehled') }}</a></li>
                    <li class="nav-item"><a class="nav-link px-2 {{ request()->routeIs('catalog.*') ? 'active' : '' }}" href="{{ route('catalog.index') }}"><i class="bi bi-tree me-1"></i>{{ __('Dřeva') }}</a></li>
                    <li class="nav-item"><a class="nav-link px-2 {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people me-1"></i>{{ __('Uživatelé') }}</a></li>
                @endif
            </ul>
            {{-- Profil: jazyk prostředí (i e-mailů) a odhlášení --}}
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }} · {{ strtoupper(app()->getLocale()) }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">{{ __('Jazyk') }}</h6></li>
                    @foreach(config('kremacia.locales') as $code => $label)
                        <li>
                            <form method="POST" action="{{ route('profile.locale') }}">
                                @csrf
                                <button name="locale" value="{{ $code }}" class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}">{{ $label }}</button>
                            </form>
                        </li>
                    @endforeach
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item"><i class="bi bi-box-arrow-right me-1"></i>{{ __('Odhlásit') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
@endauth

<main class="container pb-5">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any() && ! View::hasSection('inline-errors'))
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @yield('content')
</main>

{{-- Zvětšený náhled: <a data-zoom="url velké verze" data-zoom-title="…"> --}}
<div class="modal fade" id="zoom-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title h6" id="zoom-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Zavřít') }}"></button>
            </div>
            <div class="modal-body p-2 text-center"><img id="zoom-img" alt="" class="img-fluid rounded" style="max-height: 80vh"></div>
        </div>
    </div>
</div>

@stack('modals')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Zvětšení obrázku v okně; preventDefault – uvnitř <label> by klik jinak zároveň vybral volbu
    document.addEventListener('click', e => {
        const link = e.target.closest('[data-zoom]');
        if (!link) return;
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('zoom-img').src = link.dataset.zoom;
        document.getElementById('zoom-title').textContent = link.dataset.zoomTitle || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('zoom-modal')).show();
    });

    // Odeslání s fotkami z telefonu trvá – zamezí dvojímu odeslání a ukáže, že se nahrává
    document.querySelectorAll('form[data-uploading]').forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('[type=submit]');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + form.dataset.uploading;
        });
    });
</script>
@stack('scripts')
</body>
</html>
