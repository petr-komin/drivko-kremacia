{{-- Mřížka fotek s náhledem; klik otevře velkou verzi. Parametry: $photos, $deletable (bool) --}}
<div class="photo-grid">
    @foreach($photos as $photo)
        <div class="ph">
            @if($photo->has_preview)
                <a href="{{ $photo->url('large') }}" target="_blank" title="{{ $photo->original_name }}">
                    <img src="{{ $photo->url('thumb') }}" alt="{{ $photo->original_name }}" loading="lazy">
                </a>
            @else
                <a href="{{ $photo->downloadUrl() }}" class="noprev text-decoration-none" title="{{ __('Stáhnout') }}">
                    <i class="bi bi-file-earmark-arrow-down fs-3"></i>
                    <span class="text-truncate w-100">{{ $photo->original_name }}</span>
                </a>
            @endif
            @if($photo->has_preview)
                <a href="{{ $photo->downloadUrl() }}" class="btn btn-sm btn-light position-absolute bottom-0 start-0 m-1" title="{{ __('Stáhnout originál') }}">
                    <i class="bi bi-download"></i>
                </a>
            @endif
            @if(($deletable ?? false) && (auth()->user()->isAdmin() || $photo->user_id === auth()->id()))
                <form method="POST" action="{{ route('photos.destroy', $photo) }}" class="ph-remove"
                      onsubmit="return confirm(@js(__('Opravdu smazat tuto fotku?')))">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-light" title="{{ __('Smazat') }}"><i class="bi bi-trash"></i></button>
                </form>
            @endif
        </div>
    @endforeach
</div>
