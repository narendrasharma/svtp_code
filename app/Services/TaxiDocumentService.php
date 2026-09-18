<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Taxi fleet document storage (12A.1).
 *
 * Mirrors the vendor-KYC safety pattern: private disk, random filenames,
 * masked numbers only (last 4 visible). Raw document numbers never
 * persist. Verification workflow stays minimal (pending/verified/
 * rejected + reviewer stamp).
 */
class TaxiDocumentService
{
    public const DISK = 'vendor_kyc';

    public const ALLOWED_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    public const MAX_KB = 5120;

    public const BLOCKED_MIMES = ['application/x-php', 'application/x-sh', 'text/x-php', 'application/x-executable'];

    public function storeDriverDocument(
        Driver $driver,
        string $documentType,
        UploadedFile $file,
        ?string $documentNumber = null,
        ?string $issueDate = null,
        ?string $expiryDate = null,
    ): DriverDocument {
        return $driver->documents()->create([
            'document_type' => $documentType,
            'document_number_masked' => self::maskNumber($documentNumber),
            ...$this->storeFile($driver->id, 'drivers', $file),
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate,
            'status' => 'pending',
        ]);
    }

    public function storeVehicleDocument(
        Vehicle $vehicle,
        string $documentType,
        UploadedFile $file,
        ?string $documentNumber = null,
        ?string $issueDate = null,
        ?string $expiryDate = null,
    ): VehicleDocument {
        return $vehicle->documents()->create([
            'document_type' => $documentType,
            'document_number_masked' => self::maskNumber($documentNumber),
            ...$this->storeFile($vehicle->id, 'vehicles', $file),
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate,
            'status' => 'pending',
        ]);
    }

    public function verify(object $document, User $reviewer, bool $approved, ?string $note = null): object
    {
        $document->update([
            'status' => $approved ? 'verified' : 'rejected',
            'verified_by' => $reviewer->id,
            'verified_at' => now(),
            'review_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
        ]);

        return $document->refresh();
    }

    /**
     * @return array{original_filename: string, storage_path: string, mime_type: string, file_size: int}
     */
    protected function storeFile(int $ownerId, string $prefix, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_MIMES, true)) {
            abort(422, 'File type not allowed.');
        }

        if ($file->getSize() > self::MAX_KB * 1024) {
            abort(422, 'File exceeds maximum size.');
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';

        if (in_array($mime, self::BLOCKED_MIMES, true)) {
            abort(422, 'Executable files are not allowed.');
        }

        $path = "taxi/{$prefix}/{$ownerId}/".Str::random(32).'.'.$extension;
        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        return [
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
        ];
    }

    public static function maskNumber(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $input);

        if ($digits === '' || $digits === null) {
            return '***';
        }

        if (strlen($digits) <= 4) {
            return 'XXXX-'.$digits;
        }

        return 'XXXX-XXXX-'.substr($digits, -4);
    }
}
