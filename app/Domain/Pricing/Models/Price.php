<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * A variant price with effective dating and approval (FR-PRI-001/002).
 * Amount is DECIMAL(20,4) + currency exposed as a Money value object.
 *
 * @property string $status
 */
final class Price extends Model
{
    protected $table = 'prices';

    protected $fillable = [
        'price_list_id', 'product_variant_id', 'amount', 'compare_at_amount',
        'currency', 'status', 'effective_from', 'effective_to', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
        ];
    }

    public function money(): Money
    {
        return Money::fromDecimalString((string) $this->amount, (string) $this->currency);
    }
}
