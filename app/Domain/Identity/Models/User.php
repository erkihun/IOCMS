<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Enums\UserType;
use App\Domain\Identity\Services\AuthorizationScopeResolver;
use App\Support\Uuid\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * UserAccount aggregate (IAM, LLD §5.1). Owns identity state and invariants;
 * mutation is only through approved Actions. Authorization helpers here are used
 * for UI affordance and middleware convenience — the authoritative decision is
 * made in Policies via {@see AuthorizationScopeResolver}.
 *
 * @property int $id
 * @property string $public_id
 * @property UserType $type
 * @property UserStatus $status
 * @property bool $mfa_enrolled
 * @property string|null $display_name
 * @property string|null $password_hash
 * @property int $lock_version
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $phone_verified_at
 */
final class User extends Authenticatable implements MustVerifyEmail
{
    use HasPublicId;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'public_id', 'type', 'status', 'display_name', 'email', 'phone',
        'locale', 'password_hash', 'mfa_enrolled', 'email_verified_at',
        'phone_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'status' => UserStatus::class,
            'mfa_enrolled' => 'boolean',
            'email_verified_at' => 'immutable_datetime',
            'phone_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    /** Laravel auth reads the password from `password_hash`. */
    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    // --- Relationships -----------------------------------------------------

    /** @return HasMany<UserIdentifier, $this> */
    public function identifiers(): HasMany
    {
        return $this->hasMany(UserIdentifier::class);
    }

    /** @return HasMany<UserRole, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** @return HasMany<Authenticator, $this> */
    public function authenticators(): HasMany
    {
        return $this->hasMany(Authenticator::class);
    }

    // --- Authorization helpers (UI affordance; not the authorization boundary)

    /**
     * @return string[]
     */
    public function effectivePermissionCodes(): array
    {
        return app(AuthorizationScopeResolver::class)
            ->permissionCodesFor($this);
    }

    /**
     * @return array<int, array{type: string, id: string|null}>
     */
    public function scopeDescriptors(): array
    {
        return $this->roleAssignments()
            ->get()
            ->map(fn (UserRole $r): array => ['type' => $r->scope_type, 'id' => $r->scope_id])
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->effectivePermissionCodes(), true);
    }

    public function hasScopeType(string $scopeType): bool
    {
        return $this->roleAssignments()->where('scope_type', $scopeType)->exists()
            || $this->roleAssignments()->where('scope_type', 'global')->exists();
    }

    public function hasVerifiedContact(): bool
    {
        return $this->email_verified_at !== null || $this->phone_verified_at !== null;
    }

    /**
     * Whether the user's MFA requirement is satisfied. The session-confirmed
     * flag is passed in from the middleware layer so the domain model stays free
     * of HTTP dependencies (boundary rule, SDD §15.3).
     */
    public function mfaSatisfied(bool $sessionConfirmed): bool
    {
        return ! $this->mfa_enrolled || $sessionConfirmed;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
