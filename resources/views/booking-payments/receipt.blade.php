<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt {{ $receipt['reference'] }}</title>
    <style>
        body { font-family: sans-serif; color: #111; max-width: 640px; margin: 24px auto; padding: 0 16px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        td, th { text-align: left; padding: 6px 4px; border-bottom: 1px solid #ddd; font-size: 14px; }
        .total td { font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h2>{{ $site['name'] }}</h2>
    <p style="font-size: 13px; color: #444;">
        @if($site['email']){{ $site['email'] }}@endif
        @if($site['email'] && $site['phone']) | @endif
        @if($site['phone']){{ $site['phone'] }}@endif
        @if($site['address'])<br>{{ $site['address'] }}@endif
    </p>
    <hr>
    <h3>Payment Receipt — {{ $receipt['reference'] }}</h3>
    <table>
        <tr><td>Booking</td><td><strong>{{ $receipt['booking']['reference'] }}</strong></td></tr>
        <tr><td>Customer</td><td>{{ $receipt['booking']['customer_name'] }}</td></tr>
        <tr><td>Tour</td><td>{{ $receipt['booking']['tour'] ?? '—' }}</td></tr>
        <tr><td>Travel date</td><td>{{ $receipt['booking']['travel_date'] ?? '—' }}</td></tr>
        <tr><td>Amount received</td><td><strong>{{ $receipt['balance']['currency'] }} {{ $receipt['amount'] }}</strong></td></tr>
        <tr><td>Payment method</td><td>{{ $receipt['method'] }}</td></tr>
        <tr><td>Date</td><td>{{ $receipt['paid_at'] }}</td></tr>
        @if($receipt['external_reference'])<tr><td>External reference</td><td>{{ $receipt['external_reference'] }}</td></tr>@endif
        @if($receipt['note'])<tr><td>Note</td><td>{{ $receipt['note'] }}</td></tr>@endif
        <tr><td>Collected by</td><td>{{ $receipt['received_by'] ?? '—' }}</td></tr>
    </table>
    <h4>Outstanding balance</h4>
    <table>
        <tr><td>Booking total</td><td>{{ $receipt['balance']['currency'] }} {{ $receipt['balance']['total'] }}</td></tr>
        <tr><td>Total paid</td><td>{{ $receipt['balance']['currency'] }} {{ $receipt['balance']['paid'] }}</td></tr>
        @if((float) $receipt['balance']['refunded'])<tr><td>Refunded</td><td>{{ $receipt['balance']['currency'] }} {{ $receipt['balance']['refunded'] }}</td></tr>@endif
        <tr class="total"><td>Amount due</td><td>{{ $receipt['balance']['currency'] }} {{ $receipt['balance']['due'] }}</td></tr>
    </table>
    <p class="no-print"><button onclick="window.print()">Print receipt</button></p>
</body>
</html>
