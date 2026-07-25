<?php

declare(strict_types=1);

namespace App\Domain\Administration\Services;

use App\Domain\Administration\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluates feature flags with owner/scope/activation-window/rollback semantics
 * (FR-ADM-005). The marketplace master gate is honored here (decision D-005,
 * SDD ADR-020). Config defaults apply until DB flags exist, so the platform is
 * safe before ADM seeding.
 */
final class FeatureFlagEvaluator
{
    public function isEnabled(string $key, mixed $actor = null): bool
    {
        // DB flag wins when present; otherwise fall back to config defaults.
        $flag = $this->resolve($key);

        if ($flag === null) {
            return (bool) config("iocms.features.{$key}", false);
        }

        if ($flag->status !== 'active') {
            return false;
        }

        $now = now();
        if ($flag->starts_at && $now->lt($flag->starts_at)) {
            return false;
        }
        if ($flag->ends_at && $now->gt($flag->ends_at)) {
            return false;
        }

        return (bool) $flag->is_enabled;
    }

    /**
     * @return array<string, bool>
     */
    public function allForActor(mixed $actor = null): array
    {
        $result = [];
        foreach ((array) config('iocms.features', []) as $key => $default) {
            $result[$key] = $this->isEnabled($key, $actor);
        }

        if ($this->tableExists()) {
            foreach (FeatureFlag::query()->pluck('key') as $key) {
                $result[$key] = $this->isEnabled($key, $actor);
            }
        }

        return $result;
    }

    private function resolve(string $key): ?FeatureFlag
    {
        if (! $this->tableExists()) {
            return null;
        }

        return Cache::remember("feature_flag:{$key}", 30, function () use ($key): ?FeatureFlag {
            return FeatureFlag::query()->where('key', $key)->first();
        });
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('feature_flags');
        } catch (\Throwable) {
            return false;
        }
    }
}
