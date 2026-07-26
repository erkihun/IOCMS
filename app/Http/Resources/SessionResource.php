<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Identity\Models\UserSession;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe public shape for a user session (LLD §3.2: never serialize the model
 * directly; expose the public id, not the internal key or token hash).
 *
 * @mixin UserSession
 */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'last_active_at' => $this->last_active_at instanceof CarbonInterface
                ? $this->last_active_at->toIso8601String()
                : null,
            'is_current' => hash('sha256', (string) $request->session()->getId()) === $this->session_token_hash,
        ];
    }
}
