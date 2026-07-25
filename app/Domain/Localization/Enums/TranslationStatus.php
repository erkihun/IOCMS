<?php

declare(strict_types=1);

namespace App\Domain\Localization\Enums;

/** Translation review state (FR-LOC translation review). */
enum TranslationStatus: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Published = 'published';
}
