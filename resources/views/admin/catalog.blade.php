@extends('layouts.app')

@section('title', __('Dřeva'))

@section('content')
<h1 class="h4 fw-bold mb-1">{{ __('Dřeva') }}</h1>
<p class="text-muted small mb-3">
    {{ __('Použité dřevo nejde smazat – nepotřebné vypněte a přestane se nabízet u nových zakázek.') }}
    {{ __('Fotka dřeva se kremaci ukazuje při zadávání zakázky, výchozí dřevo je předvybrané.') }}
    {{ __('Slovenský název se ukáže těm, kdo mají prostředí ve slovenštině.') }}
</p>

@error('photo')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                @foreach($woodTypes as $item)
                    <form method="POST" action="{{ route('catalog.wood.update', $item) }}" enctype="multipart/form-data"
                          class="row g-2 align-items-center {{ $loop->last ? '' : 'border-bottom pb-3' }} mb-3">
                        @csrf @method('PUT')
                        <div class="col-auto">
                            @if($item->photo_path)
                                <a href="{{ $item->photoUrl('large') }}" data-zoom="{{ $item->photoUrl('large') }}" data-zoom-title="{{ $item->label }}"><img src="{{ $item->photoUrl() }}" alt="" class="wood-thumb-sm"></a>
                            @else
                                <div class="wood-thumb-sm d-flex align-items-center justify-content-center text-muted" style="background: var(--sand)"><i class="bi bi-image"></i></div>
                            @endif
                        </div>
                        <div class="col"><input name="name" value="{{ $item->name }}" class="form-control form-control-sm" required title="{{ __('Název česky') }}" placeholder="CS"></div>
                        <div class="col"><input name="name_sk" value="{{ $item->name_sk }}" class="form-control form-control-sm" title="{{ __('Název slovensky') }}" placeholder="SK"></div>
                        <div class="col-2"><input type="number" name="sort_order" value="{{ $item->sort_order }}" class="form-control form-control-sm" min="0" title="{{ __('Pořadí') }}"></div>
                        <div class="col-auto">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" class="form-check-input" @checked($item->active) title="{{ __('Aktivní') }}">
                        </div>
                        <div class="col-auto"><button class="btn btn-sm btn-outline-brown"><i class="bi bi-check-lg"></i></button></div>

                        <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                            <input type="file" name="photo" accept="image/*,.heic,.heif" class="form-control form-control-sm w-auto flex-grow-1"
                                   title="{{ $item->photo_path ? __('Vyměnit fotku') : __('Nahrát fotku') }}">
                            <div class="form-check small text-nowrap mb-0" title="{{ __('Předvybrané u nové zakázky') }}">
                                <input type="checkbox" name="is_default" value="1" class="form-check-input" id="default{{ $item->id }}" @checked($item->is_default)>
                                <label class="form-check-label" for="default{{ $item->id }}">{{ __('Výchozí') }}</label>
                            </div>
                            @if($item->photo_path)
                                <div class="form-check small text-nowrap mb-0">
                                    <input type="checkbox" name="remove_photo" value="1" class="form-check-input" id="rmphoto{{ $item->id }}">
                                    <label class="form-check-label" for="rmphoto{{ $item->id }}">{{ __('Smazat fotku') }}</label>
                                </div>
                            @endif
                            <span class="small text-muted">{{ trans_choice('{0} :count zakázek|{1} :count zakázka|[2,4] :count zakázky|[5,*] :count zakázek', $item->orders_count) }}</span>
                        </div>
                    </form>
                @endforeach

                <hr>
                <form method="POST" action="{{ route('catalog.wood.store') }}" enctype="multipart/form-data" class="row g-2 align-items-center">
                    @csrf
                    <div class="col"><input name="name" class="form-control form-control-sm" placeholder="{{ __('Nové dřevo (česky)') }}" required></div>
                    <div class="col"><input name="name_sk" class="form-control form-control-sm" placeholder="{{ __('slovensky') }}"></div>
                    <div class="col-2"><input type="number" name="sort_order" class="form-control form-control-sm" min="0" placeholder="{{ __('pořadí') }}"></div>
                    <div class="col-auto"><button class="btn btn-sm btn-brown"><i class="bi bi-plus-lg"></i></button></div>
                    <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                        <input type="file" name="photo" accept="image/*,.heic,.heif" class="form-control form-control-sm w-auto flex-grow-1" title="{{ __('Fotka dřeva (nepovinná)') }}">
                        <div class="form-check small mb-0">
                            <input type="checkbox" name="is_default" value="1" class="form-check-input" id="default-new">
                            <label class="form-check-label" for="default-new">{{ __('Výchozí') }}</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>.wood-thumb-sm { width: 56px; height: 56px; object-fit: cover; border-radius: .375rem; }</style>
@endpush
@endsection
