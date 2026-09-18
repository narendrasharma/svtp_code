<?php

namespace App\Services;

use App\Enums\VendorDocumentStatus;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\VendorDocument;
use App\Models\VendorVerification;
use App\Notifications\AdminAlert;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorDocumentService
{
    use NotifiesAdmins;

    public function store(
        VendorVerification $verification,
        string $documentType,
        UploadedFile $file,
        ?string $documentNumberMasked = null,
    ): VendorDocument {
        $catalog = config('vendor_kyc.document_catalog', []);
        if (! array_key_exists($documentType, $catalog)) {
            abort(422, 'Invalid document type.');
        }

        $allowedMimes = config('vendor_kyc.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']);
        $maxKb = config('vendor_kyc.max_file_size_kb', 5120);

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $allowedMimes, true)) {
            abort(422, 'File type not allowed.');
        }

        if ($file->getSize() > $maxKb * 1024) {
            abort(422, 'File exceeds maximum size.');
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $blockedMimes = ['application/x-php', 'application/x-sh', 'text/x-php', 'application/x-executable'];
        if (in_array($mime, $blockedMimes, true)) {
            abort(422, 'Executable files are not allowed.');
        }

        // Aadhaar safety: store only last 4 digits masked.
        $maskedNumber = null;
        if ($documentNumberMasked !== null && $documentNumberMasked !== '') {
            $maskedNumber = $this->maskDocumentNumber($documentType, $documentNumberMasked);
        }

        $disk = config('vendor_kyc.disk', 'vendor_kyc');
        $prefix = config('vendor_kyc.storage_path_prefix', 'kyc');
        $filename = Str::random(32).'.'.$extension;
        $path = $prefix.'/'.$verification->id.'/'.$filename;

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        // Replace previous pending/rejected of same type? Keep history, just create new.
        // But if a rejected document exists, allow replacement; old stays for audit.

        return tap(VendorDocument::create([
            'vendor_verification_id' => $verification->id,
            'document_type' => $documentType,
            'document_number_masked' => $maskedNumber,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
            'status' => VendorDocumentStatus::Pending,
        ]), function () use ($verification): void {
            $this->notifyAdmins(new AdminAlert('kyc_submitted', [
                'vendor' => $verification->user?->name ?? 'A vendor',
                'verification_id' => $verification->id,
            ]));
        });
    }

    public function maskDocumentNumber(string $documentType, string $input): ?string
    {
        $input = trim($input);

        if ($documentType === 'masked_aadhaar') {
            // Accept masked form like XXXX XXXX 1234 or **** **** 1234 or just 1234
            // Strip non-digits
            $digits = preg_replace('/\D/', '', $input);
            if ($digits === '' || $digits === null) {
                return null;
            }
            // If full 12 digits supplied, only keep last 4
            if (strlen($digits) === 12) {
                $last4 = substr($digits, -4);

                return 'XXXX-XXXX-'.$last4;
            }
            if (strlen($digits) === 4) {
                return 'XXXX-XXXX-'.$digits;
            }
            // If contains X's, extract last 4 digits
            if (strlen($digits) > 4) {
                $last4 = substr($digits, -4);

                return 'XXXX-XXXX-'.$last4;
            }

            // Fallback: mask whatever provided, keep last 4 chars
            return 'XXXX-XXXX-'.substr($digits, -4);
        }

        // For other doc types, store masked last 4 if looks like number, else raw truncated
        if (strlen($input) > 20) {
            return substr($input, 0, 20).'…';
        }

        return $input;
    }

    public function secureDownload(VendorDocument $document)
    {
        $disk = config('vendor_kyc.disk', 'vendor_kyc');

        if (! Storage::disk($disk)->exists($document->storage_path)) {
            abort(404);
        }

        return Storage::disk($disk)->download($document->storage_path, $document->original_filename);
    }
}
