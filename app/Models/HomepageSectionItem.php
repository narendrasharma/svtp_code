<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'homepage_section_id',
        'entity_type',
        'entity_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'homepage_section_id' => 'integer',
            'entity_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }
}
