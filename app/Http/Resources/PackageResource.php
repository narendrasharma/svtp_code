<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'city' => $this->city?->name,
            'duration_days' => $this->duration_days,
            'duration_nights' => $this->duration_nights,
            'price' => $this->price,
            'discounted_price' => $this->discounted_price,
            'effective_price' => $this->effective_price,
            'overview' => $this->overview,
            'day_wise_itinerary' => $this->day_wise_itinerary,
            'inclusions' => $this->inclusions,
            'exclusions' => $this->exclusions,
            'cover_image' => $this->cover_image,
            'gallery' => $this->gallery,
            'is_featured' => $this->is_featured,
        ];
    }
}
