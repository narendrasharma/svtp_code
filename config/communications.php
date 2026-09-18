<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS provider
    |--------------------------------------------------------------------------
    | Null when unset: SMS options are disabled across the UI and any
    | forced send fails loudly instead of pretending delivery. Future
    | adapters (MSG91, Twilio, ...) register a class implementing
    | App\Services\Comms\SmsProviderInterface here.
    */
    'sms_provider' => env('SMS_PROVIDER'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp provider
    |--------------------------------------------------------------------------
    | Default 'manual': staff-driven wa.me share links, no credentials.
    | A future API adapter registers its class here.
    */
    'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp default country code
    |--------------------------------------------------------------------------
    | Used only when a stored phone has 10 digits and no country code
    | (the common Indian-tourism case). Full international numbers are
    | always used verbatim.
    */
    'whatsapp_country_code' => env('WHATSAPP_COUNTRY_CODE', '91'),

];
