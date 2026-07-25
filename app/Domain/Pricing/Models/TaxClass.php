<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tax class with configured rules (FR-PRI-008; rates never hard-coded). */
final class TaxClass extends Model
{
    protected $table = 'tax_classes';

    protected $fillable = ['code', 'name', 'is_inclusive'];

    protected function casts(): array
    {
        return ['is_inclusive' => 'boolean'];
    }

    /** @return HasMany<TaxRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }
}
