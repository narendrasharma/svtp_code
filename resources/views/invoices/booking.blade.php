<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Receipt {{ $receipt['booking']['booking_reference_id'] }}</title></head>
<body style="font-family: sans-serif; color: #111;">
    <h2>{{ $receipt['site']['name'] }}</h2>
    @if($receipt['site']['tagline'])<p>{{ $receipt['site']['tagline'] }}</p>@endif
    <p>
        @if($receipt['site']['email']){{ $receipt['site']['email'] }}@endif
        @if($receipt['site']['email'] && $receipt['site']['phone']) | @endif
        @if($receipt['site']['phone']){{ $receipt['site']['phone'] }}@endif
        @if($receipt['site']['address'])<br>{{ $receipt['site']['address'] }}@endif
    </p>
    <hr>
    <h3>Receipt — {{ $receipt['booking']['booking_reference_id'] }}</h3>
    <p><strong>Issued:</strong> {{ $receipt['booking']['created_at'] }} &nbsp; <strong>Status:</strong> {{ $receipt['booking']['booking_status'] }} / {{ $receipt['booking']['payment_status'] }}</p>
    <p><strong>Customer:</strong> {{ $receipt['customer']['name'] }}
        @if($receipt['customer']['email']) ({{ $receipt['customer']['email'] }})@endif
        @if($receipt['customer']['phone']) — {{ $receipt['customer']['phone'] }}@endif
    </p>
    <p><strong>Tour:</strong> {{ $receipt['tour']['title'] ?? '—' }}</p>
    <p><strong>Travel Date:</strong> {{ $receipt['booking']['travel_date'] ?? '—' }}</p>
    <p><strong>Adults:</strong> {{ $receipt['booking']['total_adults'] }} &nbsp; <strong>Children:</strong> {{ $receipt['booking']['total_children'] }}</p>
    <hr>
    <p><strong>Subtotal:</strong> {{ $receipt['booking']['currency'] }} {{ number_format($receipt['pricing']['subtotal'], 2) }}</p>
    @foreach($receipt['addons'] ?? [] as $line)
        <p>{{ $line['name'] }} × {{ $line['quantity'] }}: {{ $receipt['booking']['currency'] }} {{ number_format($line['total_amount'], 2) }} (₹{{ number_format($line['unit_price'], 2) }} each)</p>
    @endforeach
    @if((float) $receipt['pricing']['discount_amount'])
        <p><strong>Discount{{ ! empty($receipt['pricing']['coupon_code']) ? ' ('.$receipt['pricing']['coupon_code'].')' : '' }}:</strong> −{{ $receipt['booking']['currency'] }} {{ number_format($receipt['pricing']['discount_amount'], 2) }}</p>
    @endif
    @if((float) $receipt['pricing']['tax_amount'])
        <p><strong>Tax:</strong> {{ $receipt['booking']['currency'] }} {{ number_format($receipt['pricing']['tax_amount'], 2) }}</p>
    @endif
    <p><strong>Original Total:</strong> {{ $receipt['booking']['currency'] }} {{ number_format($receipt['pricing']['gross_amount'], 2) }}</p>
    @if((float) $receipt['refunds']['total'])
        <p><strong>Refunded:</strong> −{{ $receipt['booking']['currency'] }} {{ number_format($receipt['refunds']['total'], 2) }}</p>
        <p><strong>Net Paid:</strong> {{ $receipt['booking']['currency'] }} {{ number_format($receipt['refunds']['net'], 2) }}</p>
        @foreach($receipt['refunds']['history'] as $refund)
            <p style="font-size: 12px;">Refund {{ $refund['processed_at'] }} — {{ $refund['reason'] }}: −{{ $receipt['booking']['currency'] }} {{ number_format($refund['amount'], 2) }}</p>
        @endforeach
    @endif
    <div>{!! $qrSvg !!}</div>
    <p style="font-size: 11px; color: #666;">Show this QR code at check-in for verification.</p>
</body>
</html>
