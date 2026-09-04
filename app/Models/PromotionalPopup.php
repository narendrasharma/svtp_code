<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionalPopup extends Model
{
    protected $fillable = ['image_path', 'title', 'cta_url', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
