<?php

namespace App\Services\Comms;

use App\Models\CommunicationTemplate;
use Illuminate\Validation\ValidationException;

/**
 * Whitelist placeholder renderer. Plain str_replace — Blade/PHP from
 * admin-entered templates can never execute. Unknown placeholders are
 * left intact at render time (safe) and rejected at template save time.
 */
class TemplateService
{
    /**
     * @param  array<string, string|int|float|null>  $data
     */
    public function renderText(?string $body, array $data): ?string
    {
        if ($body === null) {
            return null;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m): string => array_key_exists($m[1], $data) && $data[$m[1]] !== null
                ? (string) $data[$m[1]]
                : $m[0],
            $body
        );
    }

    /**
     * @return array<int, string> unknown placeholders found
     */
    public function unknownPlaceholders(?string $body): array
    {
        if ($body === null || $body === '') {
            return [];
        }

        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $body, $matches);

        return array_values(array_unique(array_diff(
            $matches[1],
            CommunicationTemplate::allowedPlaceholders()
        )));
    }

    /**
     * @param  array<int, string|null>  $bodies
     *
     * @throws ValidationException
     */
    public function assertPlaceholdersValid(array $bodies): void
    {
        foreach ($bodies as $body) {
            $unknown = $this->unknownPlaceholders($body);

            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'template' => 'Unknown placeholder(s): '.implode(', ', array_map(fn ($p): string => "{{{$p}}}", $unknown)).'. Allowed: '.implode(', ', CommunicationTemplate::allowedPlaceholders()).'.',
                ]);
            }
        }
    }
}
