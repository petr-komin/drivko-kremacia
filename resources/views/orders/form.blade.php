@extends('layouts.app')

@php $editing = $order->exists; @endphp

@section('title', $editing ? __('Upravit zakázku') : __('Nová zakázka'))

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">
    <a href="{{ $editing ? route('orders.show', $order) : route('orders.index') }}" class="text-brown small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ __('Zpět') }}</a>
    <h1 class="h4 fw-bold mt-2 mb-3">{{ $editing ? __('Upravit zakázku') . ' ' . $order->number : __('Nová zakázka') }}</h1>

    <form method="POST" action="{{ $editing ? route('orders.update', $order) : route('orders.store') }}"
          enctype="multipart/form-data" data-uploading="{{ $editing ? __('Ukládám…') : __('Nahrávám fotky…') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        @php
            $withPaw = (bool) old('with_paw', $order->exists ? $order->with_paw : true);
            $layout  = old('layout', $order->layout ?? \App\Models\Order::DEFAULT_LAYOUT);
        @endphp
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-7">
                        <label class="form-label" for="pet_name">{{ __('Název') }} * <span class="text-muted fw-normal small">({{ __('jméno zvířete nebo název zakázky') }})</span></label>
                        <input type="text" name="pet_name" id="pet_name" value="{{ old('pet_name', $order->pet_name) }}" class="form-control" required maxlength="120">
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="case_number">{{ __('Vaše číslo případu') }}</label>
                        <input type="text" name="case_number" id="case_number" value="{{ old('case_number', $order->case_number) }}" class="form-control" maxlength="64">
                    </div>
                    @if(\App\Support\Notifier::CLIENT_EMAILS && auth()->user()->isAdmin())
                        {{-- Zpětný zápis starších zakázek: bez e-mailů kremaci ani dílně --}}
                        <div class="col-12">
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="notify" value="0">
                                <input type="checkbox" class="form-check-input" role="switch" name="notify" value="1" id="notify"
                                       @checked(old('notify', $order->exists ? $order->notify : true))>
                                <label class="form-check-label" for="notify">{{ __('Posílat e-maily k této zakázce') }}</label>
                            </div>
                            <div class="form-text">{{ __('Vypněte u zpětně zapisovaných zakázek – změny stavu ani zprávy pak nikomu nepřijdou e-mailem.') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold"><i class="bi bi-brush me-1"></i>{{ __('Co bude na urně') }}</h2>

                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="with_paw" value="0">
                    <input type="checkbox" class="form-check-input" role="switch" name="with_paw" value="1" id="with_paw" @checked($withPaw)>
                    <label class="form-check-label fw-semibold" for="with_paw">{{ __('Otisk tlapky') }}</label>
                </div>

                {{-- Rozměr tlapky místo fotky s pravítkem; stačí jeden, druhý se odvodí z fotky --}}
                <div id="paw-size" class="row g-2 mb-3 {{ $withPaw ? '' : 'd-none' }}">
                    <div class="col-12 small text-muted">{{ __('Rozměr tlapky') }} – {{ __('stačí šířka nebo výška, druhý rozměr odvodíme z fotky. Když ho teď nemáte, doplňte ho později.') }}</div>
                    @foreach(['paw_width_cm' => __('Šířka tlapky'), 'paw_height_cm' => __('Výška tlapky')] as $field => $label)
                        <div class="col-6 col-sm-4">
                            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                            <div class="input-group">
                                <input type="number" name="{{ $field }}" id="{{ $field }}" value="{{ old($field, $order->{$field}) }}"
                                       class="form-control @error($field) is-invalid @enderror" min="{{ \App\Models\Order::MIN_PAW_CM }}" max="{{ \App\Models\Order::MAX_PAW_CM }}"
                                       step="0.1" inputmode="decimal">
                                <span class="input-group-text">cm</span>
                                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @endforeach
                </div>

                @unless($editing)
                    <div id="paw-section" class="mb-3 {{ $withPaw ? '' : 'd-none' }}">
                        <p class="small text-muted mb-2">{{ __('Ostrá fotka zblízka, celá tlapka v záběru, ideálně na světlém podkladu. Klidně víc fotek z různých úhlů.') }}</p>
                        <p class="small text-muted mb-2">{{ __('Fotky můžete nahrát i později v detailu zakázky.') }}</p>
                        @include('partials.photo-input', ['id' => 'paw-photos'])
                    </div>
                @endunless

                @php
                    // Nová zakázka: připravené jméno velkým a datum malým; prázdné řádky se neukládají
                    $lines = old('urn_lines', $order->urn_lines ?: [['text' => '', 'size' => 'L'], ['text' => '', 'size' => 'S']]);
                    $placeholders = [__('Jméno'), __('Data, např. 2012 – 2026'), __('Další řádek')];
                @endphp
                <label class="form-label fw-semibold d-block">{{ __('Text na urnu') }} <span class="text-muted fw-normal small">({{ __('nepovinné') }})</span></label>
                <div id="urn-lines" data-max="{{ \App\Models\Order::MAX_TEXT_LINES }}">
                    @foreach(array_values($lines) as $i => $line)
                        <div class="input-group mb-2 urn-line">
                            <input type="text" name="urn_lines[{{ $i }}][text]" value="{{ $line['text'] ?? '' }}" class="form-control"
                                   maxlength="100" placeholder="{{ $placeholders[min($i, 2)] }}">
                            <select name="urn_lines[{{ $i }}][size]" class="form-select flex-grow-0 w-auto" title="{{ __('Velikost písma') }}">
                                @foreach(\App\Models\Order::TEXT_SIZES as $code => $size)
                                    <option value="{{ $code }}" @selected(($line['size'] ?? 'M') === $code)>{{ __($size['label']) }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary urn-line-remove" title="{{ __('Odebrat řádek') }}"><i class="bi bi-x-lg"></i></button>
                        </div>
                    @endforeach
                </div>
                <button type="button" id="urn-line-add" class="btn btn-sm btn-outline-brown"><i class="bi bi-plus-lg me-1"></i>{{ __('Přidat řádek') }}</button>

                <div id="layout-section" class="mt-3">
                    <label class="form-label fw-semibold d-block">{{ __('Rozložení tlapky a textu') }}</label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(\App\Models\Order::LAYOUTS as $code => $label)
                            <input type="radio" class="btn-check" name="layout" id="layout_{{ $code }}" value="{{ $code }}" @checked($layout === $code)>
                            <label class="btn btn-outline-brown layout-tile p-2" for="layout_{{ $code }}" title="{{ __($label) }}">
                                <span class="layout-preview layout-{{ $code }}"><span class="lp-paw">🐾</span><span class="lp-text">Aa</span></span>
                                <span class="small">{{ __($label) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold"><i class="bi bi-clipboard me-1"></i>{{ __('Zadání') }}</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label d-block">{{ __('Dřevo') }} *</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($woodTypes as $wood)
                                <input type="radio" class="btn-check" name="wood_type_id" id="wood{{ $wood->id }}" value="{{ $wood->id }}"
                                       data-name="{{ $wood->name }}" data-photo="{{ $wood->photoUrl('large') }}"
                                       data-price-small="{{ $wood->price_small_eur }}" data-price-large="{{ $wood->price_large_eur }}"
                                       @checked((int) old('wood_type_id', $order->wood_type_id) === $wood->id) required>
                                <label class="btn btn-outline-brown {{ $wood->photo_path ? 'wood-tile p-1' : '' }}" for="wood{{ $wood->id }}">
                                    @if($wood->photo_path)
                                        <img src="{{ $wood->photoUrl() }}" alt="" loading="lazy">
                                        <a href="{{ $wood->photoUrl('large') }}" class="wood-zoom" title="{{ __('Zvětšit') }}"
                                           data-zoom="{{ $wood->photoUrl('large') }}" data-zoom-title="{{ $wood->label }}"><i class="bi bi-zoom-in"></i></a>
                                    @endif
                                    <span>{{ $wood->label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-sm-5">
                        <label class="form-label" for="volume_ml">{{ __('Objem urny') }} *</label>
                        <div class="input-group">
                            <input type="number" name="volume_ml" id="volume_ml" value="{{ old('volume_ml', $order->volume_ml) }}"
                                   class="form-control" required min="{{ \App\Models\Order::MIN_VOLUME_ML }}" max="{{ \App\Models\Order::MAX_VOLUME_ML }}" step="1" inputmode="numeric" placeholder="{{ __('např. 1500') }}">
                            <span class="input-group-text">ml</span>
                        </div>
                        <div class="form-text">{{ __('1 litr = 1000 ml') }} · {{ __(':min až :max ml', ['min' => \App\Models\Order::MIN_VOLUME_ML, 'max' => number_format(\App\Models\Order::MAX_VOLUME_ML, 0, ',', ' ')]) }}</div>
                    </div>

                    @if(auth()->user()->isAdmin())
                        {{-- Tloušťka desky podle toho, co je na skladě; určuje vnější rozměry --}}
                        <div class="col-sm-5">
                            <label class="form-label" for="wall_mm">{{ __('Tloušťka stěny') }}</label>
                            <div class="input-group">
                                <input type="number" name="wall_mm" id="wall_mm" value="{{ old('wall_mm', $order->wall_mm ?? \App\Models\Order::DEFAULT_WALL_MM) }}"
                                       class="form-control @error('wall_mm') is-invalid @enderror" min="{{ \App\Models\Order::MIN_WALL_MM }}" max="{{ \App\Models\Order::MAX_WALL_MM }}" step="1" inputmode="numeric" required>
                                <span class="input-group-text">mm</span>
                                @error('wall_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">{{ __('Stěny, dno i horní deska víka; :min až :max mm.', ['min' => \App\Models\Order::MIN_WALL_MM, 'max' => \App\Models\Order::MAX_WALL_MM]) }}</div>
                        </div>
                    @endif

                    {{-- Nepovinná pomůcka: objem z vnitřních rozměrů + informativní vnější velikost --}}
                    <div class="col-sm-7">
                        <div class="rounded p-2 small" style="background: var(--sand)">
                            <div class="fw-semibold mb-1"><i class="bi bi-rulers me-1"></i>{{ __('Vnitřní rozměry') }} <span class="text-muted fw-normal">({{ __('nepovinné – jen když chcete konkrétní tvar') }})</span></div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label mb-0" for="calc_width">{{ __('Vnitřní šířka') }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="inner_width_cm" id="calc_width" value="{{ old('inner_width_cm', $order->inner_width_cm) }}"
                                               class="form-control @error('inner_width_cm') is-invalid @enderror" min="{{ \App\Models\Order::MIN_INNER_CM }}" max="{{ \App\Models\Order::MAX_INNER_CM }}" step="0.1" inputmode="decimal">
                                        <span class="input-group-text">cm</span>
                                    </div>
                                    <div class="mt-1">{{ __('Vnější') }}: <strong id="outer_width">–</strong></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label mb-0" for="calc_height">{{ __('Vnitřní výška') }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="inner_height_cm" id="calc_height" value="{{ old('inner_height_cm', $order->inner_height_cm) }}"
                                               class="form-control @error('inner_height_cm') is-invalid @enderror" min="{{ \App\Models\Order::MIN_INNER_CM }}" max="{{ \App\Models\Order::MAX_INNER_CM }}" step="0.1" inputmode="decimal">
                                        <span class="input-group-text">cm</span>
                                    </div>
                                    <div class="mt-1">{{ __('Vnější') }}: <strong id="outer_height">–</strong></div>
                                </div>
                            </div>
                            @if(auth()->user()->isAdmin())
                                <div class="mt-2">@include('partials.cut-list')</div>
                            @endif
                            <div class="mt-2 text-muted">{{ __('Šířka i výška :min–:max cm.', ['min' => \App\Models\Order::MIN_INNER_CM, 'max' => \App\Models\Order::MAX_INNER_CM]) }} {{ __('Půdorys je vždy čtverec. Zadejte šířku a výšku (dopočítá se objem), nebo šířku k objemu (dopočítá se výška). Vnější rozměry jsou orientační (stěny a dno :wall mm, víko 15 + :wall mm). Bez rozměrů je zvolí dílna podle objemu.', ['wall' => $order->wall_mm ?? \App\Models\Order::DEFAULT_WALL_MM]) }}</div>
                        </div>
                    </div>

                    <div class="col-12">
                        @include('partials.urn-3d')
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="note">{{ __('Poznámka') }}</label>
                        <textarea name="note" id="note" rows="3" class="form-control" maxlength="5000"
                                  placeholder="{{ __('Např. kterou tlapku použít, termín, zvláštní přání…') }}">{{ old('note', $order->note) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Orientační cena podle ceníku; uloží se při odeslání --}}
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                <span class="fw-semibold"><i class="bi bi-currency-euro me-1"></i>{{ __('Cena') }}</span>
                <span><span id="price-detail" class="small text-muted me-2"></span><strong id="price-total" class="fs-5">–</strong></span>
            </div>
        </div>

        <button type="submit" class="btn btn-brown btn-lg w-100">
            <i class="bi bi-send me-1"></i>{{ $editing ? __('Uložit změny') : __('Odeslat zakázku') }}
        </button>
    </form>
</div>
</div>
@endsection

@push('styles')
<style>
    .layout-tile { width: 130px; display: flex; flex-direction: column; align-items: center; gap: .35rem; line-height: 1.2; }
    .layout-preview { width: 64px; height: 64px; border: 2px solid currentColor; border-radius: 4px; display: flex;
                      align-items: center; justify-content: center; gap: 4px; font-weight: 700; }
    .layout-paw_top   { flex-direction: column; }
    .layout-text_top  { flex-direction: column-reverse; }
    .layout-paw_left  { flex-direction: row; }
    .layout-text_left { flex-direction: row-reverse; }
    .wood-tile { width: 120px; display: flex; flex-direction: column; gap: .25rem; position: relative; }
    .wood-zoom { position: absolute; top: .4rem; right: .4rem; width: 28px; height: 28px; border-radius: 50%;
                 display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.9);
                 color: var(--brown-dark); text-decoration: none; }
    .wood-tile img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: .25rem; }
</style>
@endpush

@push('scripts')
<script>
    // Tloušťka desky v cm: dílna ji zadává ve formuláři, kremace má tu uloženou u zakázky (výchozí 8 mm)
    const urnWallCm = () => (parseInt(document.getElementById('wall_mm')?.value, 10) || {{ $order->wall_mm ?? \App\Models\Order::DEFAULT_WALL_MM }}) / 10;
</script>
<script>
    /*
     * Kalkulačka objem ↔ rozměry. Dopočítává se podle toho, co uživatel zadal naposled:
     *   - mění výšku          → objem z šířky a výšky
     *   - mění objem          → výška z objemu a šířky
     *   - mění šířku          → podle toho, jestli naposled zadával objem, nebo výšku
     * Nezacyklí se: reaguje jen na událost „input“ od uživatele a programové nastavení
     * hodnoty (pole.value = …) žádnou událost nevyvolá.
     */
    (() => {
        // Stejný výpočet jako Order::dimensionsLabel(outer: true)
        const wall = urnWallCm, PLUG = {{ \App\Models\Order::LID_PLUG_CM }};
        const w = document.getElementById('calc_width'), h = document.getElementById('calc_height');
        const volume = document.getElementById('volume_ml');
        const outerW = document.getElementById('outer_width'), outerH = document.getElementById('outer_height');
        const num = el => parseFloat(String(el.value).replace(',', '.'));
        const cm = n => n.toLocaleString(document.documentElement.lang, { maximumFractionDigits: 1 });

        // Co je „pevné“: objem (dopočítá se výška), nebo rozměry (dopočítá se objem).
        // Bez uložených rozměrů je pevný objem – doplnění šířky rovnou ukáže výšku.
        let driver = (num(w) > 0 && num(h) > 0) ? 'dims' : 'volume';

        const recalc = () => {
            const iw = num(w), ml = num(volume);

            if (driver === 'volume' && iw > 0 && ml > 0) {
                h.value = Math.round(ml / (iw * iw) * 10) / 10;
            } else if (driver === 'dims' && iw > 0 && num(h) > 0) {
                volume.value = Math.round(iw * iw * num(h));
            }
            show();
        };

        // Vnější rozměr hned pod vnitřním – každý zvlášť, ať je vidět i při rozpracovaném zadání
        const show = () => {
            const iw = num(w), ih = num(h);
            outerW.textContent = iw > 0 ? `${cm(iw + 2 * wall())} cm` : '–';
            outerH.textContent = ih > 0 ? `${cm(ih + 2 * wall() + PLUG)} cm` : '–';
        };

        document.getElementById('wall_mm')?.addEventListener('input', show);
        @if(auth()->user()->isAdmin())
            // Přířez desek bere aktuální hodnoty z formuláře, v mm
            window.cutListDims = () => ({ iw: num(w) * 10, ih: num(h) * 10, wall: wall() * 10 });
        @endif

        volume.addEventListener('input', () => { driver = 'volume'; recalc(); });
        h.addEventListener('input', () => { driver = 'dims'; recalc(); });
        w.addEventListener('input', recalc);
        show();
    })();
</script>
@endpush

@push('scripts')
<script>
    // Fotky a rozměr tlapky jen u urny s tlapkou; rozložení jen když je tlapka i text
    (() => {
        const paw = document.getElementById('with_paw'), linesBox = document.getElementById('urn-lines');
        const hasText = () => [...linesBox.querySelectorAll('input[type=text]')].some(i => i.value.trim());
        const pawSection = document.getElementById('paw-section'), layout = document.getElementById('layout-section');

        const toggle = () => {
            pawSection?.classList.toggle('d-none', !paw.checked);
            document.getElementById('paw-size').classList.toggle('d-none', !paw.checked);
            layout.classList.toggle('d-none', !(paw.checked && hasText()));
        };

        paw.addEventListener('change', toggle);
        linesBox.addEventListener('input', toggle);
        linesBox.addEventListener('urn-lines-changed', toggle);
        toggle();
    })();
</script>
@endpush

@push('scripts')
<script>
    // 3D náhled: posbírá stav formuláře; bez rozměrů ukáže krychli podle objemu (jen ilustračně)
    (() => {
        const form = document.getElementById('volume_ml').form;
        const num = id => parseFloat(String(document.getElementById(id).value).replace(',', '.'));
        const cm = n => n.toLocaleString(document.documentElement.lang, { maximumFractionDigits: 1 });
        const t = @json(['outer' => __('Vnější cca'), 'cube' => __('tvar jen ilustrační (krychle podle objemu)')]);

        const refresh = () => {
            if (!window.urnViewer) return;
            let w = num('calc_width'), h = num('calc_height'), note = '';
            const ml = num('volume_ml');
            if (!(w > 0 && h > 0) && ml > 0) {
                w = h = Math.cbrt(ml);
                note = ` · ${t.cube}`;
            }
            const wood = form.querySelector('input[name=wood_type_id]:checked');
            const t = urnWallCm();
            const outerW = w + 2 * t, outerH = h + 2 * t + {{ \App\Models\Order::LID_PLUG_CM }};

            window.urnViewer.update({
                innerW: w * 10, innerH: h * 10, wall: t * 10,
                woodName: wood?.dataset.name, woodPhoto: wood?.dataset.photo || null,
                withPaw: document.getElementById('with_paw').checked,
                lines: [...document.querySelectorAll('#urn-lines .urn-line')]
                    .map(r => ({ text: r.querySelector('input').value.trim(), size: r.querySelector('select').value }))
                    .filter(l => l.text),
                layout: form.querySelector('input[name=layout]:checked')?.value,
            }, `${t.outer} ${cm(outerW)} × ${cm(outerW)} × ${cm(outerH)} cm${note}`);
        };

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        window.addEventListener('urn-viewer-ready', refresh);
        refresh();
    })();
</script>
@endpush

@push('scripts')
<script>
    // Cena: dřevo podle objemu (do / nad hranicí) + příplatek za gravírování (tlapka nebo text)
    (() => {
        const form = document.getElementById('volume_ml').form;
        const LIMIT = {{ \App\Models\Order::PRICE_SMALL_MAX_ML }}, ENGRAVING = {{ \App\Models\Order::ENGRAVING_EUR }};
        const t = {{ Js::from(['wood' => __('dřevo'), 'engraving' => __('gravírování'), 'none' => __('cena dřeva není v ceníku')]) }};
        const eur = n => n.toLocaleString(document.documentElement.lang, { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }) + ' €';
        const total = document.getElementById('price-total'), detail = document.getElementById('price-detail');

        const refresh = () => {
            const wood = form.querySelector('input[name=wood_type_id]:checked');
            const ml = parseInt(document.getElementById('volume_ml').value, 10);
            const engraving = document.getElementById('with_paw').checked
                || [...document.querySelectorAll('#urn-lines input[type=text]')].some(i => i.value.trim());
            const raw = wood && ml > 0 ? (ml <= LIMIT ? wood.dataset.priceSmall : wood.dataset.priceLarge) : null;

            if (!wood || !(ml > 0)) { total.textContent = '–'; detail.textContent = ''; return; }
            if (raw === '' || raw == null) { total.textContent = '–'; detail.textContent = t.none; return; }

            const woodPrice = parseFloat(raw);
            total.textContent = eur(woodPrice + (engraving ? ENGRAVING : 0));
            detail.textContent = engraving ? `${t.wood} ${eur(woodPrice)} + ${t.engraving} ${eur(ENGRAVING)}` : '';
        };

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        document.getElementById('urn-lines').addEventListener('urn-lines-changed', refresh);
        refresh();
    })();
</script>
@endpush

@push('scripts')
<script>
    // Řádky textu: přidat / odebrat (indexy jen rostou – PHP mezery v poli nevadí)
    (() => {
        const box = document.getElementById('urn-lines'), add = document.getElementById('urn-line-add');
        const max = +box.dataset.max;
        let next = box.children.length;

        const changed = () => {
            add.classList.toggle('d-none', box.children.length >= max);
            box.dispatchEvent(new Event('urn-lines-changed', { bubbles: true }));
            box.dispatchEvent(new Event('input', { bubbles: true }));
        };

        add.onclick = () => {
            const row = box.querySelector('.urn-line').cloneNode(true);
            const input = row.querySelector('input'), select = row.querySelector('select');
            input.name = `urn_lines[${next}][text]`;
            select.name = `urn_lines[${next}][size]`;
            next++;
            input.value = '';
            input.placeholder = @json(__('Další řádek'));
            select.value = 'M';
            box.appendChild(row);
            input.focus();
            changed();
        };

        box.addEventListener('click', e => {
            const btn = e.target.closest('.urn-line-remove');
            if (!btn) return;
            const row = btn.closest('.urn-line');
            // Poslední řádek jen vyprázdnit, ať je kam psát
            if (box.children.length === 1) row.querySelector('input').value = '';
            else row.remove();
            changed();
        });

        changed();
    })();
</script>
@endpush
