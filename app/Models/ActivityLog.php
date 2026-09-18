<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor_user_id', 'impersonator_user_id', 'event', 'module',
        'subject_type', 'subject_id', 'description',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }

    /**
     * Change diff safe for admin display. Values are already sanitized
     * at write time; this only shapes them for the UI.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    public function diff(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $out = [];

        foreach ($keys as $key) {
            $o = $old[$key] ?? null;
            $n = $new[$key] ?? null;

            if ($o !== $n) {
                $out[] = ['field' => (string) $key, 'old' => $o, 'new' => $n];
            }
        }

        return $out;
    }
}
