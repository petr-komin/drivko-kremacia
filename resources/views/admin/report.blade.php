@extends('layouts.app')

@section('title', __('Přehled'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">{{ __('Přehled kusů') }} – {{ $month->translatedFormat('F Y') }}</h1>
    <a href="{{ route('report.csv', ['mesic' => $month->format('Y-m'), 'podle' => $basis]) }}" class="btn btn-outline-brown">
        <i class="bi bi-filetype-csv me-1"></i>{{ __('Export CSV') }}
    </a>
</div>

<form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3">
    <div>
        <label class="form-label small mb-1" for="mesic">{{ __('Měsíc') }}</label>
        <input type="month" name="mesic" id="mesic" value="{{ $month->format('Y-m') }}" class="form-control">
    </div>
    <div>
        <label class="form-label small mb-1" for="podle">{{ __('Počítat podle') }}</label>
        <select name="podle" id="podle" class="form-select">
            @foreach($bases as $key => $label)
                <option value="{{ $key }}" @selected($basis === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-brown">{{ __('Zobrazit') }}</button>
    <div class="ms-auto d-flex gap-1">
        <a class="btn btn-sm btn-light" href="{{ route('report.index', ['mesic' => $month->copy()->subMonth()->format('Y-m'), 'podle' => $basis]) }}"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-sm btn-light" href="{{ route('report.index', ['mesic' => $month->copy()->addMonth()->format('Y-m'), 'podle' => $basis]) }}"><i class="bi bi-chevron-right"></i></a>
    </div>
</form>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">{{ $bases[$basis] }} {{ __('kusů') }}</div>
                <div class="display-6 fw-bold">{{ $orders->count() }}</div>
                <div class="text-muted small mt-2">{{ __('Celkem') }}</div>
                <div class="fs-4 fw-bold">{{ \App\Models\Order::formatEur($total) }}</div>
                @if($orders->contains(fn ($o) => $o->price_eur === null))
                    <div class="small text-warning">{{ __('Některé zakázky nemají cenu (dřevo nemělo vyplněný ceník).') }}</div>
                @endif
                @if($summary->isNotEmpty())
                    <hr>
                    <table class="table table-sm small mb-0">
                        @foreach($summary as $label => $count)
                            <tr><td>{{ $label }}</td><td class="text-end fw-semibold">{{ $count }}</td></tr>
                        @endforeach
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>{{ __('Zakázka') }}</th><th>{{ __('Název') }}</th><th>{{ __('Případ') }}</th><th>{{ __('Dřevo') }} · {{ __('objem') }}</th><th class="text-end">{{ __('Cena') }}</th><th>{{ __('Zaplaceno') }}</th><th>{{ $bases[$basis] }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td><a href="{{ route('orders.show', $order) }}" class="text-brown">{{ $order->number }}</a></td>
                                <td>{{ $order->pet_name }}</td>
                                <td>{{ $order->case_number }}</td>
                                <td>{{ $order->woodType->label }} · {{ $order->volume_label }}</td>
                                <td class="text-end text-nowrap">{{ \App\Models\Order::formatEur($order->price_eur) }}</td>
                                <td class="text-nowrap">{{ $order->paid_at?->format('j. n. Y') ?? '–' }}</td>
                                <td>{{ $order->{$basis}->format('j. n. Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ __('V tomto měsíci nic.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
