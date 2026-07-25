<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/** Brand aggregate (CAT). @property string $slug */
final class Brand extends Model
{
    use HasPublicId;

    protected $table = 'brands';

    protected $fillable = ['public_id', 'slug', 'status', 'logo_path'];
}
