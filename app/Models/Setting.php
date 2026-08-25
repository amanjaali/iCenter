<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use Auditable;

    protected $fillable = ['key', 'value', 'group', 'cast', 'label', 'description', 'updated_by'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Value converted to the type declared in the `cast` column. */
    public function typedValue(): mixed
    {
        return match ($this->cast) {
            'int' => (int) $this->value,
            'decimal' => (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }
}
