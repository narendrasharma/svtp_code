<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->booking_reference_id,
            'package' => $this->whenLoaded('package', fn () => $this->package->title),
            'travel_date' => $this->travel_date?->toDateString(),
            'total_adults' => $this->total_adults,
            'total_children' => $this->total_children,
            'total_amount' => $this->total_amount,
            'payment_status' => $this->payment_status,
            'booking_status' => $this->booking_status,
            'qr_code_string' => $this->qr_code_string,
        ];
    }
}
