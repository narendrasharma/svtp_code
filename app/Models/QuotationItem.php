<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    public const TYPE_TOUR = 'tour';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_GUIDE = 'guide';

    public const TYPE_MEAL = 'meal';

    public const TYPE_STAY = 'stay';

    public const TYPE_ACTIVITY = 'activity';

    public const TYPE_MANUAL = 'manual';

    protected $fillable = [
        'quotation_id', 'item_type', 'product_id', 'description',
        'quantity', 'unit_price', 'tax_amount', 'discount_amount',
        'total_amount', 'metadata', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_TOUR,
            self::TYPE_TRANSFER,
            self::TYPE_GUIDE,
            self::TYPE_MEAL,
            self::TYPE_STAY,
            self::TYPE_ACTIVITY,
            self::TYPE_MANUAL,
        ];
    }
}
