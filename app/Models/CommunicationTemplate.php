<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'name',
        'email_subject', 'email_body',
        'sms_body', 'whatsapp_body',
        'in_app_title', 'in_app_body',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Every placeholder the renderer may substitute, anywhere.
     * Admin-entered templates are validated against this list —
     * unknown placeholders are rejected at save time, and the renderer
     * itself is a plain str_replace (Blade/PHP can never execute).
     *
     * @return array<int, string>
     */
    public static function allowedPlaceholders(): array
    {
        return [
            'customer_name',
            'booking_reference',
            'quotation_reference',
            'payment_reference',
            'ticket_reference',
            'ticket_subject',
            'amount',
            'amount_due',
            'travel_date',
            'valid_until',
            'secure_url',
            'site_name',
        ];
    }
}
