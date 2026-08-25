<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-student audit trail behind an invoice line: which student was billed,
 * for how many days, at what proration. Nothing is aggregated away.
 */
class InvoiceLineStudent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_line_id', 'student_id', 'student_enrollment_id', 'billable_days',
        'days_in_month', 'billable_units', 'amount', 'is_partial_month', 'note',
    ];

    protected function casts(): array
    {
        return [
            'billable_units' => 'decimal:4',
            'amount' => 'decimal:2',
            'is_partial_month' => 'boolean',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class, 'invoice_line_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
