@extends('layouts.app')

@section('title', __('Zakázky'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">{{ __('Zakázky') }}</h1>
    <a href="{{ route('orders.create') }}" class="btn btn-brown"><i class="bi bi-plus-lg me-1"></i>{{ __('Nová zakázka') }}</a>
</div>

@php
    $tabCount = fn (string $key) => $key === 'all'
        ? $counts->sum()
        : collect(\App\Http\Controllers\OrderController::FILTERS[$key][1])->sum(fn ($s) => $counts[$s] ?? 0);
@endphp

<ul class="nav nav-tabs flex-nowrap overflow-auto mb-3" style="white-space:nowrap;">
    @foreach(\App\Http\Controllers\OrderController::FILTERS as $key => [$label])
        <li class="nav-item">
            <a class="nav-link {{ $filter === $key ? 'active' : '' }}" href="{{ route('orders.index', ['stav' => $key, 'q' => $search ?: null]) }}">
                {{ __($label) }} <span class="badge text-bg-light">{{ $tabCount($key) }}</span>
            </a>
        </li>
    @endforeach
</ul>

<form class="mb-3" method="GET">
    <input type="hidden" name="stav" value="{{ $filter }}">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('Název, číslo případu nebo zakázky') }}">
    </div>
</form>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse($orders as $order)
            @php $thumb = $order->pawPhotos->firstWhere('has_preview', true); @endphp
            <a href="{{ route('orders.show', $order) }}" class="list-group-item order-row d-flex gap-3 align-items-center py-3">
                @if($thumb)
                    <img src="{{ $thumb->url('thumb') }}" class="order-thumb flex-shrink-0" alt="" loading="lazy">
                @else
                    <div class="order-thumb flex-shrink-0 d-flex align-items-center justify-content-center"><i class="bi bi-image text-muted"></i></div>
                @endif
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <strong>{{ $order->pet_name }}</strong>
                        @include('partials.status-badge')
                        @if($order->missingPawPhoto())
                            <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('chybí fotka tlapky') }}</span>
                        @endif
                        @if($order->missingPawSize())
                            <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('chybí rozměr tlapky') }}</span>
                        @endif
                        @if($order->isPaid())
                            <span class="badge text-bg-light border text-success"><i class="bi bi-cash-coin me-1"></i>{{ __('zaplaceno') }}</span>
                        @endif
                    </div>
                    <div class="small text-muted text-truncate">
                        {{ $order->number }}@if($order->case_number) · {{ __('případ') }} {{ $order->case_number }}@endif
                        · {{ $order->woodType->label }} · {{ $order->volume_label }}
                    </div>
                </div>
                <div class="text-end small text-muted flex-shrink-0">
                    <div>{{ $order->last_activity_at?->format('j. n.') }}</div>
                    @if($order->messages_count)
                        <div><i class="bi bi-chat"></i> {{ $order->messages_count }}</div>
                    @endif
                </div>
            </a>
        @empty
            <div class="list-group-item text-center text-muted py-5">
                @if($search !== '')
                    {{ __('Nic nenalezeno.') }}
                @else
                    {{ __('Tady zatím nic není.') }}
                @endif
            </div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $orders->links() }}</div>
@endsection
