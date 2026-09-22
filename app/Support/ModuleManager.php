<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Central platform Module Manager (Phase 11.5A).
 *
 * Single source of truth for module availability/enabled state. Module
 * definitions live in config/modules.php; the enabled flag may be
 * overridden per module via the settings store
 * (key: "modules.{key}.enabled"). Modules flagged available=false
 * (taxi/hotels) are never reported enabled — they are future metadata.
 *
 * Shared platform systems (users, vendors, finance, settings, ...) are
 * intentionally NOT modules and are never gated by this manager.
 */
class ModuleManager
{
    public const TOURS = 'tours';

    public const TAXI = 'taxi';

    public const HOTELS = 'hotels';

    /**
     * Normalize the singular Hotel key used by older admin bundles to the
     * registered key used by hotel route gating and settings storage.
     */
    public function canonicalKey(string $module): string
    {
        return $module === 'hotel' ? self::HOTELS : $module;
    }

    /**
     * All registered modules with runtime state.
     *
     * @return array<int, array{key:string,name:string,description:string,available:bool,enabled:bool,order:int}>
     */
    public function all(): array
    {
        return collect(config('modules', []))
            ->map(fn (array $definition, string $key): array => [
                'key' => $definition['key'] ?? $key,
                'name' => $definition['name'] ?? ucfirst($key),
                'description' => $definition['description'] ?? '',
                'available' => (bool) ($definition['available'] ?? false),
                'enabled' => $this->isEnabled($key),
                'order' => (int) ($definition['order'] ?? 100),
            ])
            ->sortBy('order')
            ->values()
            ->all();
    }

    /**
     * Only modules that are both installed and switched on.
     *
     * @return array<int, string>
     */
    public function active(): array
    {
        return collect($this->all())
            ->where('enabled', true)
            ->pluck('key')
            ->values()
            ->all();
    }

    public function isKnown(string $module): bool
    {
        return array_key_exists($this->canonicalKey($module), config('modules', []));
    }

    public function isAvailable(string $module): bool
    {
        $module = $this->canonicalKey($module);

        return (bool) (config("modules.{$module}.available") ?? false);
    }

    public function isEnabled(string $module): bool
    {
        $module = $this->canonicalKey($module);

        if (! $this->isKnown($module)) {
            return false;
        }

        // Future modules are registered as metadata only and can never
        // be enabled until their implementation ships.
        if (! $this->isAvailable($module)) {
            return false;
        }

        $default = (bool) (config("modules.{$module}.enabled_by_default") ?? false);
        $stored = Setting::getValue("modules.{$module}.enabled", null);

        if ($stored === null || $stored === '') {
            return $default;
        }

        return in_array(strtolower((string) $stored), ['1', 'true', 'yes', 'on'], true);
    }

    public function isDisabled(string $module): bool
    {
        return ! $this->isEnabled($module);
    }

    /**
     * Switch a module on/off. Throws for unknown or not-yet-available
     * modules so the admin UI can never enable broken routes.
     *
     * @throws \InvalidArgumentException
     */
    public function setEnabled(string $module, bool $enabled): void
    {
        $module = $this->canonicalKey($module);

        if (! $this->isKnown($module)) {
            throw new \InvalidArgumentException("Unknown module [{$module}].");
        }

        if ($enabled && ! $this->isAvailable($module)) {
            throw new \InvalidArgumentException("Module [{$module}] is not installed yet and cannot be enabled.");
        }

        Setting::setValue("modules.{$module}.enabled", $enabled ? '1' : '0');
    }

    public function definitions(): Collection
    {
        return collect(config('modules', []));
    }
}
