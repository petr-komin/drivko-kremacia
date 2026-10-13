{{--
    Výběr fotek z telefonu: vyfotit / vybrat z galerie, opakovaně a s náhledy.
    Soubory se sbírají do jednoho skrytého <input name="photos[]">, takže jde
    nafotit tlapky postupně jednu po druhé.
    Parametry: $id (unikátní), $required (bool)
--}}
@php
    $max   = \App\Support\Photos::MAX_FILES;
    $maxMb = \App\Support\Photos::MAX_KB / 1024;
@endphp
<div class="photo-input" data-max="{{ $max }}" data-max-mb="{{ $maxMb }}" data-required="{{ ($required ?? false) ? 1 : 0 }}" id="{{ $id }}">
    {{-- Bez atributu required – u skrytého pole by prohlížeč odeslání tiše zablokoval --}}
    <input type="file" name="photos[]" multiple class="d-none pi-real" accept="image/*,.heic,.heif,.svg">
    <input type="file" accept="image/*" capture="environment" class="d-none pi-camera">
    <input type="file" accept="image/*,.heic,.heif,.svg" multiple class="d-none pi-gallery">

    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-outline-brown pi-btn-camera"><i class="bi bi-camera me-1"></i>{{ __('Vyfotit') }}</button>
        <button type="button" class="btn btn-outline-brown pi-btn-gallery"><i class="bi bi-images me-1"></i>{{ __('Vybrat fotky') }}</button>
    </div>
    <div class="photo-grid pi-previews"></div>
    <div class="form-text pi-info">{{ __('Max. :max fotek po :mb MB', ['max' => $max, 'mb' => $maxMb]) }} · JPG, PNG, WebP, HEIC, SVG</div>
    <div class="text-danger small pi-error"></div>
</div>

@once
@push('scripts')
<script>
const piText = {{ Js::from([
    'tooMany'  => __('Najednou jde nahrát nejvýš :max fotek.'),
    'tooBig'   => __('Fotka :name je větší než :mb MB.'),
    'required' => __('Přiložte alespoň jednu fotku.'),
]) }};
document.querySelectorAll('.photo-input').forEach(box => {
    const real = box.querySelector('.pi-real');
    const previews = box.querySelector('.pi-previews');
    const error = box.querySelector('.pi-error');
    const max = +box.dataset.max, maxBytes = box.dataset.maxMb * 1024 * 1024;
    let files = [];

    box.querySelector('.pi-btn-camera').onclick = () => box.querySelector('.pi-camera').click();
    box.querySelector('.pi-btn-gallery').onclick = () => box.querySelector('.pi-gallery').click();

    const sync = () => {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        real.files = dt.files;

        previews.innerHTML = '';
        files.forEach((f, i) => {
            const ph = document.createElement('div');
            ph.className = 'ph';
            if (/^image\/(jpeg|png|webp|gif|svg\+xml)$/.test(f.type)) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.onload = () => URL.revokeObjectURL(img.src);
                ph.appendChild(img);
            } else {
                ph.innerHTML = '<div class="noprev"><i class="bi bi-file-earmark-image fs-3"></i></div>';
                ph.querySelector('.noprev').append(f.name);
            }
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-sm btn-light ph-remove';
            rm.innerHTML = '<i class="bi bi-x-lg"></i>';
            rm.onclick = () => { files.splice(i, 1); sync(); };
            ph.appendChild(rm);
            previews.appendChild(ph);
        });
    };

    const add = input => {
        error.textContent = '';
        for (const f of input.files) {
            if (files.length >= max) { error.textContent = piText.tooMany.replace(':max', max); break; }
            if (f.size > maxBytes) { error.textContent = piText.tooBig.replace(':name', f.name).replace(':mb', box.dataset.maxMb); continue; }
            files.push(f);
        }
        input.value = '';
        sync();
    };

    box.closest('form')?.addEventListener('submit', e => {
        if (box.dataset.required === '1' && files.length === 0) {
            e.preventDefault();
            e.stopImmediatePropagation();
            error.textContent = piText.required;
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);

    box.querySelector('.pi-camera').onchange = e => add(e.target);
    box.querySelector('.pi-gallery').onchange = e => add(e.target);
});
</script>
@endpush
@endonce
