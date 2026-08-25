<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Scope of work §9.3 — every entry is logged with the user, the date and the
 * time. Models using this trait record their own creates, updates and deletes
 * without each service having to remember to.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->record('created', $model, newValues: $model->auditableAttributes());
        });

        static::updated(function ($model) {
            $changed = array_keys($model->getChanges());
            $tracked = array_diff($changed, ['updated_at']);

            if ($tracked === []) {
                return;
            }

            app(AuditLogger::class)->record(
                'updated',
                $model,
                oldValues: array_intersect_key($model->getOriginal(), array_flip($tracked)),
                newValues: array_intersect_key($model->getChanges(), array_flip($tracked)),
            );
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->record('deleted', $model, oldValues: $model->auditableAttributes());
        });
    }

    /** Attributes worth keeping in the log — secrets and noise are dropped. */
    public function auditableAttributes(): array
    {
        $hidden = array_merge($this->getHidden(), ['password', 'remember_token', 'created_at', 'updated_at']);

        return collect($this->getAttributes())
            ->except($hidden)
            ->all();
    }

    /** Human label used in the audit trail listing. */
    public function auditLabel(): string
    {
        foreach (['reference', 'number', 'code', 'name'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return '#'.$this->getKey();
    }
}
