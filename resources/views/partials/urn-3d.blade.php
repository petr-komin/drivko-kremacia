{{--
    3D náhled urny. Plátno + popisek; stav do něj posílá stránka přes
    window.urnViewer.update({...}) (formulář) nebo $state (detail zakázky, jen ke čtení).
    Parametry: $state (pole, nepovinné) – innerW, innerH, wall (mm), woodName, woodPhoto, withPaw, lines [{text, size}], layout
--}}
<div class="urn-3d rounded overflow-hidden position-relative" style="background: var(--sand)">
    <canvas id="urn-canvas" style="width:100%; height:280px; display:block; touch-action:none"></canvas>
    <div id="urn-placeholder" class="position-absolute top-50 start-50 translate-middle text-center text-muted small" style="pointer-events:none">
        <i class="bi bi-box fs-1 opacity-25"></i><div>{{ __('Zadejte objem nebo rozměry') }}</div>
    </div>
    <button type="button" id="urn-lid" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2"><i class="bi bi-arrow-up-square me-1"></i>{{ __('Zvednout víko') }}</button>
</div>
<div class="d-flex justify-content-between flex-wrap gap-2 small text-muted mt-1">
    <span id="urn-caption"></span>
    <span><i class="bi bi-cursor me-1"></i>{{ __('táhnutím otočit · kolečkem přiblížit') }}</span>
</div>

@push('scripts')
<script type="importmap">
{ "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.158.0/examples/jsm/"
} }
</script>
<script type="module">
    import { UrnViewer } from '{{ asset('js/urn-viewer.js') }}?v={{ filemtime(public_path('js/urn-viewer.js')) }}';

    const viewer = new UrnViewer(document.getElementById('urn-canvas'), {
        wall: {{ \App\Models\Order::DEFAULT_WALL_MM }},
        lidPlug: {{ \App\Models\Order::LID_PLUG_CM * 10 }},
        textRatios: @json(array_map(fn ($s) => $s['ratio'], \App\Models\Order::TEXT_SIZES)),
    });
    const placeholder = document.getElementById('urn-placeholder');
    const lidBtn = document.getElementById('urn-lid');

    lidBtn.onclick = () => {
        lidBtn.innerHTML = viewer.toggleLid()
            ? '<i class="bi bi-arrow-down-square me-1"></i>' + @json(__('Zavřít víko'))
            : '<i class="bi bi-arrow-up-square me-1"></i>' + @json(__('Zvednout víko'));
    };

    // Obal kvůli placeholderu a popisku; formulář volá window.urnViewer.update(...)
    window.urnViewer = {
        update(state, caption = '') {
            const ok = state.innerW > 0 && state.innerH > 0;
            placeholder.classList.toggle('d-none', ok);
            lidBtn.classList.toggle('d-none', !ok);
            document.getElementById('urn-caption').textContent = ok ? caption : '';
            viewer.update(state);
        },
    };

    @isset($state)
        window.urnViewer.update(@json($state), @json($caption ?? ''));
    @endisset
    window.dispatchEvent(new Event('urn-viewer-ready'));
</script>
@endpush
