<?php

namespace App\Support;

use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Reusable dynamic-content translation behavior (Phase 13A).
 *
 * Design: single polymorphic `content_translations` table keyed by
 * (type, id, locale, field). Each model whitelists its own
 * translatable fields — requests can never translate arbitrary
 * columns (no mass-assignment / ownership / price mutation).
 *
 * Fallback: requested locale → default locale → original column.
 */
trait HasTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(ContentTranslation::class, 'translatable');
    }

    /**
     * Whitelisted dynamic fields allowed for translation.
     *
     * @return array<int, string>
     */
    public static function translatableFields(): array
    {
        return [];
    }

    public static function isTranslatableField(string $field): bool
    {
        return in_array($field, static::translatableFields(), true);
    }

    /**
     * Resolve a field for a locale with default-locale + original fallback.
     */
    public function translated(string $field, ?string $locale = null): ?string
    {
        if (! static::isTranslatableField($field)) {
            return $this->getAttribute($field);
        }

        $locale = $locale ?: Localization::currentLocale();
        $default = Localization::defaultLocale();

        $loaded = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : null;

        $find = function (?string $forLocale) use ($field, $loaded): ?string {
            if ($forLocale === null || $forLocale === '') {
                return null;
            }

            if ($loaded !== null) {
                $match = $loaded->first(fn ($row) => $row->locale === $forLocale && $row->field === $field);

                $value = $match?->value;

                return is_string($value) && trim($value) !== '' ? $value : null;
            }

            $value = ContentTranslation::query()
                ->where('translatable_type', static::class)
                ->where('translatable_id', $this->getKey())
                ->where('locale', $forLocale)
                ->where('field', $field)
                ->value('value');

            return is_string($value) && trim($value) !== '' ? $value : null;
        };

        return $find($locale) ?? $find($default) ?? $this->getAttribute($field);
    }

    /**
     * Persist one whitelisted translation. Empty values delete the row
     * (fall back to original) instead of storing blanks.
     *
     * @throws \InvalidArgumentException
     */
    public function setTranslation(string $locale, string $field, ?string $value): void
    {
        if (! static::isTranslatableField($field)) {
            throw new \InvalidArgumentException("Field [{$field}] is not translatable.");
        }

        $locale = Localization::normalizeCode($locale);
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            ContentTranslation::query()
                ->where('translatable_type', static::class)
                ->where('translatable_id', $this->getKey())
                ->where('locale', $locale)
                ->where('field', $field)
                ->delete();

            return;
        }

        ContentTranslation::updateOrCreate(
            [
                'translatable_type' => static::class,
                'translatable_id' => $this->getKey(),
                'locale' => $locale,
                'field' => $field,
            ],
            ['value' => $value]
        );
    }

    /**
     * Eager-load only current + fallback locale rows (avoids N+1 and
     * avoids loading every enabled language on listings).
     */
    public function scopeWithLocaleTranslations($query, ?string $locale = null)
    {
        $locale = $locale ?: Localization::currentLocale();
        $locales = array_values(array_unique(array_filter([$locale, Localization::defaultLocale()])));

        return $query->with(['translations' => fn ($q) => $q->whereIn('locale', $locales)]);
    }

    /**
     * All stored translations grouped as [locale => [field => value]].
     * Used by the admin translation editor (needs all locales).
     *
     * @return array<string, array<string, ?string>>
     */
    public function translationsMap(): array
    {
        $rows = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->get();

        $map = [];

        foreach ($rows as $row) {
            $map[$row->locale][$row->field] = $row->value;
        }

        return $map;
    }
}
