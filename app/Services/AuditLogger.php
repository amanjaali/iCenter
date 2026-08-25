<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Scope of work §9.3 — the audit trail.
 *
 * Writes are deliberately best-effort at the transport level (IP and user
 * agent may be absent in console runs) but never silent about the who and the
 * what: a log row is always written, even for a system-initiated action.
 */
class AuditLogger
{
    /** Set while a service wants a reason attached to whatever it writes next. */
    private ?string $pendingReason = null;

    private bool $enabled = true;

    public function record(
        string $event,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?string $reason = null,
    ): ?AuditLog {
        if (! $this->enabled) {
            return null;
        }

        $user = Auth::user();

        $log = AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description ?? $this->describe($event, $subject),
            'reason' => $reason ?? $this->pendingReason,
            'old_values' => $this->sanitise($oldValues),
            'new_values' => $this->sanitise($newValues),
            'ip_address' => $this->ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500) ?: null,
        ]);

        $this->pendingReason = null;

        return $log;
    }

    /** Attach a reason to the next audit row written — used by rate changes. */
    public function withReason(string $reason): self
    {
        $this->pendingReason = $reason;

        return $this;
    }

    /**
     * Suppress logging for the duration of a callback. Used by seeders, where
     * thousands of rows would otherwise drown the real trail.
     */
    public function withoutLogging(callable $callback): mixed
    {
        $previous = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $previous;
        }
    }

    private function describe(string $event, ?Model $subject): string
    {
        if (! $subject) {
            return ucfirst($event);
        }

        $label = method_exists($subject, 'auditLabel')
            ? $subject->auditLabel()
            : '#'.$subject->getKey();

        return ucfirst($event).' '.class_basename($subject).' '.$label;
    }

    private function ip(): ?string
    {
        return app()->runningInConsole() ? null : Request::ip();
    }

    private function sanitise(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return collect($values)
            ->except(['password', 'remember_token'])
            ->map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v)
            ->all();
    }
}
