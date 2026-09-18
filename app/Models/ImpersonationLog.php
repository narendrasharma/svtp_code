<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'impersonated_user_id',
        'started_at',
        'ended_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }
}
