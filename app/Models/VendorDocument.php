<?php

namespace App\Models;

use App\Enums\VendorDocumentStatus;
use App\Enums\VendorDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_verification_id',
        'document_type',
        'document_number_masked',
        'original_filename',
        'storage_path',
        'mime_type',
        'file_size',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'expires_at',
    ];

    protected $casts = [
        'document_type' => VendorDocumentType::class,
        'status' => VendorDocumentStatus::class,
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'storage_path',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(VendorVerification::class, 'vendor_verification_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isVerified(): bool
    {
        return $this->status === VendorDocumentStatus::Verified;
    }

    public function isMaskedAadhaar(): bool
    {
        return $this->document_type === VendorDocumentType::MaskedAadhaar;
    }
}
