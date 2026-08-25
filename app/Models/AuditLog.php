<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append only. The application never updates or deletes an audit row, so
 * timestamps are limited to created_at.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'event', 'auditable_type', 'auditable_id',
        'description', 'reason', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Invoice", "Rate card" — the model name without its namespace. */
    public function subjectLabel(): string
    {
        if (! $this->auditable_type) {
            return '—';
        }

        return ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($this->auditable_type))));
    }

    public function scopeForSubject(Builder $query, string $type, int|string $id): Builder
    {
        return $query->where('auditable_type', $type)->where('auditable_id', $id);
    }
}
