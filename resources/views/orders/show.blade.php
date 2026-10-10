@extends('layouts.app')

@section('title', "{$order->number} {$order->pet_name}")

@php
    $isAdmin = auth()->user()->isAdmin();
    $canEdit = $isAdmin || $order->status === \App\Models\Order::STATUS_NEW;
@endphp

@section('content')
<a href="{{ route('orders.index') }}" class="text-brown small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ __('Zakázky') }}</a>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">{{ $order->pet_name }} @include('partials.status-badge')
            @if($isAdmin && ! $order->notify)
                <span class="badge text-bg-light border" title="{{ __('K této zakázce se neposílají e-maily.') }}"><i class="bi bi-bell-slash me-1"></i>{{ __('bez e-mailů') }}</span>
            @endif
        </h1>
        <div class="text-muted small">
            {{ $order->number }}@if($order->case_number) · {{ __('případ') }} <strong>{{ $order->case_number }}</strong>@endif
            · {{ __('zadal(a)') }} {{ $order->author->name }} {{ $order->created_at->format('j. n. Y H:i') }}
        </div>
    </div>
    <div class="d-flex gap-2">
        @if($canEdit)
            <a href="{{ route('orders.edit', $order) }}" class="btn btn-sm btn-outline-brown"><i class="bi bi-pencil me-1"></i>{{ __('Upravit') }}</a>
        @endif
        @if($isAdmin && $order->photos()->exists())
            <a href="{{ route('orders.zip', $order) }}" class="btn btn-sm btn-outline-brown"><i class="bi bi-file-zip me-1"></i>{{ __('Stáhnout fotky') }}</a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        {{-- Zadání --}}
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">{{ __('Dřevo') }}</dt><dd class="col-7 fw-semibold">
                        @if($order->woodType->photo_path)
                            <a href="{{ $order->woodType->photoUrl('large') }}" data-zoom="{{ $order->woodType->photoUrl('large') }}" data-zoom-title="{{ $order->woodType->label }}"><img
                               src="{{ $order->woodType->photoUrl() }}" alt="" class="rounded me-1" style="width:28px;height:28px;object-fit:cover"></a>
                        @endif
                        {{ $order->woodType->label }}</dd>
                    <dt class="col-5 text-muted fw-normal">{{ __('Objem') }}</dt><dd class="col-7 fw-semibold">{{ $order->volume_label }}</dd>
                    <dt class="col-5 text-muted fw-normal">{{ __('Cena') }}</dt><dd class="col-7 fw-semibold">{{ \App\Models\Order::formatEur($order->price_eur) }}
                        @if($order->price_eur !== null && $order->hasEngraving())<span class="small text-muted fw-normal">({{ __('vč. gravírování') }})</span>@endif</dd>
                    @if($order->hasInnerDimensions())
                        <dt class="col-5 text-muted fw-normal">{{ __('Vnitřní rozměry') }}</dt>
                        <dd class="col-7 fw-semibold">{{ $order->dimensionsLabel() }}
                            <div class="small text-muted fw-normal">{{ __('vnější cca') }} {{ $order->dimensionsLabel(outer: true) }}</div></dd>
                    @endif
                    <dt class="col-5 text-muted fw-normal">{{ __('Otisk tlapky') }}</dt><dd class="col-7 fw-semibold">{{ $order->with_paw ? __('ano') : __('ne') }}</dd>
                    @if($order->hasUrnText())
                        <dt class="col-5 text-muted fw-normal">{{ __('Text na urnu') }}</dt>
                        <dd class="col-7 fw-semibold">
                            @foreach($order->urn_lines as $line)
                                <div style="font-size: {{ \App\Models\Order::TEXT_SIZES[$line['size']]['ratio'] ?? 1 }}em; line-height: 1.2"
                                     title="{{ \App\Models\Order::textSizeLabel($line['size']) }}">{{ $line['text'] }}</div>
                            @endforeach
                        </dd>
                    @endif
                    @if($order->layout_label)
                        <dt class="col-5 text-muted fw-normal">{{ __('Rozložení') }}</dt><dd class="col-7">{{ $order->layout_label }}</dd>
                    @endif
                    <dt class="col-5 text-muted fw-normal">{{ __('Zaplaceno') }}</dt>
                    <dd class="col-7 {{ $order->isPaid() ? 'fw-semibold text-success' : '' }}">{{ $order->isPaid() ? $order->paid_at->format('j. n. Y H:i') : __('ne') }}</dd>
                    @foreach(['started_at' => __('Ve výrobě od'), 'finished_at' => __('Dokončeno'), 'handed_over_at' => __('Předáno'), 'cancelled_at' => __('Zrušeno')] as $col => $label)
                        @if($order->{$col})
                            <dt class="col-5 text-muted fw-normal">{{ $label }}</dt><dd class="col-7">{{ $order->{$col}->format('j. n. Y') }}</dd>
                        @endif
                    @endforeach
                </dl>
                @if($order->volume_ml)
                    @php
                        // Bez rozměrů ilustrační krychle podle objemu (jako ve formuláři)
                        $iw = $order->hasInnerDimensions() ? (float) $order->inner_width_cm : pow($order->volume_ml, 1 / 3);
                        $ih = $order->hasInnerDimensions() ? (float) $order->inner_height_cm : $iw;
                    @endphp
                    <hr>
                    @include('partials.urn-3d', [
                        'state' => [
                            'innerW' => $iw * 10, 'innerH' => $ih * 10,
                            'woodName' => $order->woodType->name, 'woodPhoto' => $order->woodType->photoUrl('large'),
                            'withPaw' => $order->with_paw, 'lines' => $order->urn_lines ?? [], 'layout' => $order->layout,
                        ],
                        'caption' => $order->hasInnerDimensions() ? __('Vnější cca') . ' ' . $order->dimensionsLabel(outer: true) : __('Tvar jen ilustrační – rozměry zvolí dílna'),
                    ])
                @endif
                @if($order->note)
                    <hr>
                    <div class="small" style="white-space: pre-line;">{{ $order->note }}</div>
                @endif
            </div>
        </div>

        {{-- Podklady – u urny bez tlapky jen když nějaké fotky přesto přišly --}}
        @if($order->with_paw || $order->pawPhotos->isNotEmpty())
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold"><i class="bi bi-camera me-1"></i>{{ __('Fotky tlapky') }} <span class="text-muted fw-normal">({{ $order->pawPhotos->count() }})</span></h2>
                @include('partials.photo-grid', ['photos' => $order->pawPhotos, 'deletable' => true])
            </div>
        </div>
        @endif

        {{-- Hotový kus --}}
        @if($order->finishedPhotos->isNotEmpty())
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 fw-bold"><i class="bi bi-check2-circle me-1"></i>{{ __('Hotová urna') }}</h2>
                    @include('partials.photo-grid', ['photos' => $order->finishedPhotos, 'deletable' => true])
                </div>
            </div>
        @endif

        {{-- Dílna: změna stavu --}}
        @if($isAdmin)
            <div class="card mb-3 border-start border-4" style="border-color: var(--brown) !important;">
                <div class="card-body">
                    <h2 class="h6 fw-bold"><i class="bi bi-hammer me-1"></i>{{ __('Dílna') }}</h2>
                    <form method="POST" action="{{ route('orders.notify', $order) }}" class="form-check form-switch mb-3">
                        @csrf
                        <input type="hidden" name="notify" value="0">
                        <input type="checkbox" class="form-check-input" role="switch" name="notify" value="1" id="notify-switch"
                               @checked($order->notify) onchange="this.form.submit()">
                        <label class="form-check-label small" for="notify-switch">{{ __('Posílat e-maily k této zakázce') }}</label>
                    </form>

                    {{-- Zaplaceno: nezávislé na stavu; zaškrtnutí zapíše teď, datum jde opravit --}}
                    <form method="POST" action="{{ route('orders.paid', $order) }}" class="mb-3">
                        @csrf
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="form-check mb-0">
                                <input type="hidden" name="paid" value="0">
                                <input type="checkbox" class="form-check-input" name="paid" value="1" id="paid-check"
                                       @checked($order->isPaid()) onchange="this.form.submit()">
                                <label class="form-check-label fw-semibold" for="paid-check">{{ __('Zaplaceno') }}</label>
                            </div>
                            @if($order->isPaid())
                                <a href="#paid-edit" class="small text-brown" data-bs-toggle="collapse">{{ $order->paid_at->format('j. n. Y H:i') }} <i class="bi bi-pencil"></i></a>
                            @endif
                        </div>
                        @if($order->isPaid())
                            <div class="collapse mt-2" id="paid-edit">
                                <div class="input-group input-group-sm">
                                    <input type="datetime-local" name="paid_at" class="form-control" value="{{ $order->paid_at->format('Y-m-d\TH:i') }}">
                                    <button class="btn btn-outline-brown">{{ __('Uložit datum') }}</button>
                                </div>
                            </div>
                        @endif
                    </form>
                    <form method="POST" action="{{ route('orders.status', $order) }}" enctype="multipart/form-data" data-uploading="{{ __('Ukládám…') }}">
                        @csrf
                        <label class="form-label small" for="status">{{ __('Stav') }}</label>
                        <select name="status" id="status" class="form-select mb-3">
                            @foreach($statuses as $code => [$label])
                                <option value="{{ $code }}" @selected(old('status', $order->status) === $code)>{{ __($label) }}</option>
                            @endforeach
                        </select>

                        <label class="form-label small">{{ __('Fotky hotového kusu') }} <span class="text-muted">({{ __('nutné pro „Hotovo“') }})</span></label>
                        @include('partials.photo-input', ['id' => 'finished-photos'])

                        <label class="form-label small mt-3" for="status-body">{{ __('Vzkaz pro kremaci') }}</label>
                        <textarea name="body" id="status-body" rows="2" class="form-control mb-3" maxlength="5000">{{ old('body') }}</textarea>

                        <button type="submit" class="btn btn-brown w-100">{{ __('Uložit') }}</button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Vlákno --}}
    <div class="col-lg-7" id="zpravy">
        <div class="card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-chat-dots me-1"></i>{{ __('Zprávy') }}</h2>

                <div class="d-flex flex-column gap-2 mb-3">
                    @forelse($order->messages as $message)
                        @if($message->is_system)
                            <div class="msg-system">
                                <i class="bi bi-info-circle me-1"></i>{{ $message->text() }} · {{ $message->created_at->format('j. n. H:i') }}
                                @if($changes = $message->changes())
                                    <ul class="list-unstyled small mb-0 mt-1">
                                        @foreach($changes as $change)
                                            <li><span class="fw-semibold">{{ $change['label'] }}:</span>
                                                <span class="text-decoration-line-through">{{ $change['old'] }}</span> → {{ $change['new'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @else
                            <div class="msg {{ $message->user_id === auth()->id() ? 'msg-mine' : 'msg-other border' }}">
                                <div class="small text-muted mb-1">
                                    <strong>{{ $message->user?->name ?? __('Smazaný uživatel') }}</strong>
                                    · {{ $message->created_at->format('j. n. H:i') }}
                                </div>
                                @if($message->body)
                                    <div style="white-space: pre-line;">{{ $message->body }}</div>
                                @endif
                                @if($message->photos->isNotEmpty())
                                    <div class="mt-2">@include('partials.photo-grid', ['photos' => $message->photos])</div>
                                @endif
                            </div>
                        @endif
                    @empty
                        <p class="text-muted small mb-0">{{ __('Zatím žádné zprávy. Cokoli k zakázce pište sem – zůstane to u ní.') }}</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('messages.store', $order) }}" enctype="multipart/form-data" data-uploading="{{ __('Odesílám…') }}">
                    @csrf
                    <textarea name="body" rows="3" class="form-control mb-2" maxlength="5000" placeholder="{{ __('Napište zprávu…') }}">{{ old('body') }}</textarea>
                    @include('partials.photo-input', ['id' => 'message-photos'])
                    <button type="submit" class="btn btn-brown mt-2"><i class="bi bi-send me-1"></i>{{ __('Odeslat') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
