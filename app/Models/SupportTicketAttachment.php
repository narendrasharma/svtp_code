<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAttachment extends Model
{
    use HasFactory;

    /**
     * Never allow executables / scripts through ticket uploads.
     *
     * @var array<int, string>
     */
    public const BLOCKED_MIMES = [
        'application/x-php',
        'application/x-sh',
        'application/x-executable',
        'application/x-msdos-program',
        'application/x-msdownload',
        'text/x-php',
        'text/html',
    ];

    /**
     * @var array<int, string>
     */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/jpg',
    ];

    public const MAX_SIZE_KB = 5120;

    protected $fillable = [
        'message_id', 'disk', 'path', 'original_name',
        'mime', 'size', 'is_internal', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'message_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
