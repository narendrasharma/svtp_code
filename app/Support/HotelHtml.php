<?php

namespace App\Support;

/**
 * Minimal HTML sanitizer for property rich text (12B.1).
 *
 * Descriptions render with v-html on public pages, so stored HTML is
 * allow-listed here: safe structural tags only, event-handler
 * attributes and javascript: URLs stripped. Plain-text fields
 * (policies, contact) never pass through this — they render escaped.
 */
class HotelHtml
{
    public const ALLOWED_TAGS = '<p><br><strong><em><ul><ol><li><h3><h4><a>';

    public static function clean(?string $html, int $maxLength = 10000): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = strip_tags($html, self::ALLOWED_TAGS);
        // Drop event handlers: onclick="...", onload='...', unquoted.
        $text = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $text);
        // Neutralize javascript:/data: links, keep the anchor text.
        $text = preg_replace('/<a\b([^>]*?)\bhref\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\'|"\s*data:[^"]*"|\'\s*data:[^\']*\')([^>]*)>/i', '<a$1$3>', $text);
        $text = trim((string) $text);

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }

        return $text === '' ? null : $text;
    }
}
