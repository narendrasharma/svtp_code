<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Invoice {{ $booking->booking_reference_id }}</title></head>
<body style="font-family: sans-serif;">
    <h2>Shree Vrindavan Tour Packages</h2>
    <p>Mathura, Uttar Pradesh | info@shreevrindavantourpackages.com</p>
    <hr>
    <h3>Invoice — {{ $booking->booking_reference_id }}</h3>
    <p><strong>Package:</strong> {{ $booking->package->title }}</p>
    <p><strong>Traveler:</strong> {{ $booking->user->name }}</p>
    <p><strong>Travel Date:</strong> {{ $booking->travel_date->format('d M Y') }}</p>
    <p><strong>Adults:</strong> {{ $booking->total_adults }} &nbsp; <strong>Children:</strong> {{ $booking->total_children }}</p>
    <p><strong>Total Amount:</strong> ₹{{ number_format($booking->total_amount, 2) }}</p>
    <p><strong>Payment Status:</strong> {{ ucfirst($booking->payment_status) }}</p>
    <div>{!! $qrSvg !!}</div>
    <p style="font-size: 11px; color: #666;">Show this QR code at check-in for verification.</p>
</body>
</html>
