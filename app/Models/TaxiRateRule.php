<?php

namespace App\Models;

use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxiRateRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxi_rate_card_id', 'code', 'calculation_type', 'amount',
        'included_quantity', 'unit', 'configuration',
    ];

    protected function casts(): array
    {
        return [
            'code' => TaxiRateRuleCode::class,
            'calculation_type' => TaxiRateCalculationType::class,
            'amount' => 'decimal:4',
            'included_quantity' => 'decimal:2',
            'configuration' => 'array',
        ];
    }

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(TaxiRateCard::class, 'taxi_rate_card_id');
    }
}
