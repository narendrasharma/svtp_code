<?php

namespace App\AI\DTOs;

final readonly class AIExecutionContext
{
    /** @param list<string> $permissions */
    public function __construct(
        public ?int $userId = null,
        public ?string $role = null,
        public array $permissions = [],
        public ?int $vendorId = null,
        public ?string $locale = null,
        public ?string $currency = null,
    ) {}
}
