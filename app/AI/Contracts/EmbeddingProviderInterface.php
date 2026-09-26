<?php

namespace App\AI\Contracts;

interface EmbeddingProviderInterface
{
    public function key(): string;

    /** @return list<float> */
    public function embed(string $text, string $model, string $purpose = 'document', ?string $title = null): array;
}
