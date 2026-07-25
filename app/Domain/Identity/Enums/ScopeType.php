<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/** Record-scope dimension for authorization (FR-IAM-012, BR-026). */
enum ScopeType: string
{
    case Global = 'global';
    case Warehouse = 'warehouse';
    case Seller = 'seller';
    case DeliveryZone = 'delivery_zone';
}
