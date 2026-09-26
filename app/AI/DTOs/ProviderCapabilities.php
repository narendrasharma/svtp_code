<?php

namespace App\AI\DTOs;

final readonly class ProviderCapabilities
{
    /** @param list<string> $supported */
    public function __construct(public array $supported) {}

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->supported, true);
    }
}
