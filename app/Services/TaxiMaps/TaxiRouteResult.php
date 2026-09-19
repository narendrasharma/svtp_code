<?php

namespace App\Services\TaxiMaps;

/**
 * Provider-neutral route result (Phase 12A.6).
 *
 * Raw provider payloads never leave the adapter — controllers and Vue
 * components only ever see this shape. A polyline plus a decoded,
 * capped path are both carried so the frontend stays dumb.
 */
class TaxiRouteResult
{
    /**
     * @param  array<int, array{lat: float, lng: float}>  $path
     * @param  array{ne: array{lat: float, lng: float}, sw: array{lat: float, lng: float}}|null  $bounds
     */
    public function __construct(
        public readonly string $provider,
        public readonly bool $available,
        public readonly ?string $reason,
        public readonly ?int $distanceMeters,
        public readonly ?int $durationSeconds,
        public readonly ?string $polyline,
        public readonly array $path,
        public readonly ?array $bounds,
        public readonly \DateTimeInterface $calculatedAt,
        public readonly bool $cached = false,
    ) {}

    public static function unavailable(string $provider, string $reason): self
    {
        return new self(
            provider: $provider,
            available: false,
            reason: $reason,
            distanceMeters: null,
            durationSeconds: null,
            polyline: null,
            path: [],
            bounds: null,
            calculatedAt: now(),
        );
    }

    /**
     * Human ETA for operational display, e.g. "≈25 min".
     */
    public function etaLabel(): ?string
    {
        if ($this->durationSeconds === null) {
            return null;
        }

        $minutes = (int) round($this->durationSeconds / 60);

        return $minutes < 1 ? 'Less than a minute' : '≈'.$minutes.' min';
    }

    /**
     * @return array{provider:string, available:bool, reason:?string, distance_meters:?int, duration_seconds:?int, eta:?string, polyline:?string, path:array<int, array{lat:float, lng:float}>, bounds:?array{ne:array{lat:float, lng:float}, sw:array{lat:float, lng:float}}, calculated_at:string, cached:bool}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'available' => $this->available,
            'reason' => $this->reason,
            'distance_meters' => $this->distanceMeters,
            'duration_seconds' => $this->durationSeconds,
            'eta' => $this->etaLabel(),
            'polyline' => $this->polyline,
            'path' => $this->path,
            'bounds' => $this->bounds,
            'calculated_at' => $this->calculatedAt->toISOString(),
            'cached' => $this->cached,
        ];
    }
}
