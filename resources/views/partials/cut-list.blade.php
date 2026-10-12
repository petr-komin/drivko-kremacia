{{--
    Přířez desek pro dílnu: odkaz + okno s díly, ze kterých se urna slepí (stejná stavba jako 3D náhled).
    Rozměry bere z funkce window.cutListDims() → {iw, ih, wall} v mm, kterou nastaví stránka
    (detail z uložené zakázky, formulář z polí). Parametry: $class (nepovinné) – třídy odkazu.
--}}
<a href="#" class="{{ $class ?? 'small' }} text-brown" data-bs-toggle="modal" data-bs-target="#cut-list-modal"><i class="bi bi-grid-3x3 me-1"></i>{{ __('Přířez desek') }}</a>

@once
@push('modals')
<div class="modal fade" id="cut-list-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title h6"><i class="bi bi-grid-3x3 me-1"></i>{{ __('Přířez desek') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Zavřít') }}"></button>
            </div>
            <div class="modal-body" id="cut-list-body"></div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
    // Prstové spoje: všechny 4 stěny i dno se řežou na vnější rozměr, každý rozměr + 0,5 mm rezerva z každé strany
    // (celkem 1 mm) na obroušení po zasunutí. Stěny jsou vyšší o zátku víka; víko = horní deska na vnější rozměr + zátka (o 0,5 mm menší než vnitřek kvůli vůli).
    (() => {
        const PLUG = {{ \App\Models\Order::LID_PLUG_CM * 10 }}, GAP = 0.5, SAND = 2 * 0.5;
        const t = {{ Js::from([
            'part' => __('Díl'), 'pcs' => __('ks'), 'size' => __('Rozměr (mm)'), 'thick' => __('Tl.'),
            'walls' => __('Stěny'), 'bottom' => __('Dno'),
            'lidTop' => __('Víko – horní deska'), 'plug' => __('Víko – zátka'),
            'area' => __('Plocha desky :wall mm celkem'), 'missing' => __('Zadejte vnitřní šířku a výšku urny.'),
            'note' => __('Prstové spoje: stěny i dno na vnější rozměr + 0,5 mm z každé strany (celkem 1 mm) na obroušení. Výška stěn = dno + vnitřní výška + zátka víka. Zátka je na čistý rozměr.'),
        ]) }};
        const mm = n => n.toLocaleString(document.documentElement.lang, { maximumFractionDigits: 1 });

        document.getElementById('cut-list-modal').addEventListener('show.bs.modal', () => {
            const body = document.getElementById('cut-list-body');
            const { iw, ih, wall: s } = window.cutListDims?.() ?? {};
            if (!(iw > 0 && ih > 0 && s > 0)) {
                body.innerHTML = `<p class="text-muted mb-0">${t.missing}</p>`;
                return;
            }

            const W = iw + 2 * s + SAND, H = s + ih + PLUG + SAND, plug = iw - GAP;
            const parts = [
                [t.walls, 4, W, H, s], [t.bottom, 1, W, W, s],
                [t.lidTop, 1, W, W, s], [t.plug, 1, plug, plug, PLUG],
            ];
            const area = parts.filter(p => p[4] === s).reduce((sum, [, n, a, b]) => sum + n * a * b, 0) / 1e6;

            const tr = el => { const r = document.createElement('tr'); el.forEach(([tag, text, cls]) => {
                const c = document.createElement(tag); c.textContent = text; if (cls) c.className = cls; r.append(c); }); return r; };
            const table = document.createElement('table');
            table.className = 'table table-sm align-middle mb-2';
            table.createTHead().append(tr([['th', t.part], ['th', t.pcs, 'text-end'], ['th', t.size, 'text-end'], ['th', t.thick, 'text-end']]));
            const tb = table.createTBody();
            parts.forEach(([name, n, a, b, th]) => tb.append(tr([['td', name], ['td', `${n}×`, 'text-end'],
                ['td', `${mm(a)} × ${mm(b)}`, 'text-end fw-semibold'], ['td', `${mm(th)} mm`, 'text-end text-muted']])));

            body.replaceChildren(table);
            body.insertAdjacentHTML('beforeend', `<div class="small"><strong>${t.area.replace(':wall', mm(s))}:</strong> `
                + `${area.toLocaleString(document.documentElement.lang, { maximumFractionDigits: 3 })} m²</div>`
                + `<div class="small text-muted mt-1">${t.note}</div>`);
        });
    })();
</script>
@endpush
@endonce
