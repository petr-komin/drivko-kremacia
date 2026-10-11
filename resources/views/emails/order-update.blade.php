<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; color:#3d2b10; background:#f5f1ea; padding:24px;">
<div style="max-width:560px; margin:0 auto; background:#fff; border-radius:8px; padding:24px;">
    <p style="margin:0 0 4px; color:#8a6d47; font-size:13px;">{{ __('Zakázka') }} {{ $order->number }}@if($order->case_number) · {{ __('případ') }} {{ $order->case_number }}@endif</p>
    <h2 style="margin:0 0 16px; font-size:20px;">{{ $order->pet_name }} – {{ $headline }}</h2>

    <p style="margin:0 0 16px; font-size:14px;">
        {{ $order->woodType->label }} · {{ $order->volume_label }}@if($order->price_eur !== null) · {{ \App\Models\Order::formatEur($order->price_eur) }}@endif @if($order->hasInnerDimensions()) · {{ __('vnitřní') }} {{ $order->dimensionsLabel() }}@endif<br>
        {{ $order->with_paw ? __('S otiskem tlapky') : __('Bez tlapky') }}{{ $order->with_paw && $order->pawSizeLabel() ? ' (' . $order->pawSizeLabel() . ')' : '' }}@if($order->layout_label) · {{ $order->layout_label }}@endif<br>
        @if($order->hasUrnText())
            {{ __('Text na urnu') }}:<br>
            @foreach($order->urn_lines as $line)
                <strong style="font-size:{{ round(14 * (\App\Models\Order::TEXT_SIZES[$line['size']]['ratio'] ?? 1)) }}px">{{ $line['text'] }}</strong><br>
            @endforeach
        @endif
        {{ __('Stav') }}: <strong>{{ $order->status_label }}</strong>
    </p>

    @if($bodyText)
        <div style="white-space:pre-line; background:#f5f1ea; border-radius:6px; padding:12px 16px; margin:0 0 20px; font-size:14px;">{{ $bodyText }}</div>
    @endif

    <a href="{{ route('orders.show', $order) }}"
       style="display:inline-block; background:#5c3d1e; color:#fff; text-decoration:none; padding:10px 20px; border-radius:6px;">
        {{ __('Otevřít zakázku') }}
    </a>
</div>
</body>
</html>
