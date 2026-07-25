<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/** Purpose of an MFA challenge (FR-IAM-005, NFR-SE-011 step-up). */
enum MfaChallengePurpose: string
{
    case Login = 'login';
    case StepUp = 'step_up';
    case Enrollment = 'enrollment';
}
