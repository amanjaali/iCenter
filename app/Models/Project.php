<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'name_ar', 'description', 'is_default',
        'is_overhead_pool', 'receives_overhead', 'started_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_overhead_pool' => 'boolean',
            'receives_overhead' => 'boolean',
            'is_active' => 'boolean',
            'started_on' => 'date',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }

    public static function overheadPool(): ?self
    {
        return static::where('is_overhead_pool', true)->first();
    }
}
