<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentSequence extends Model
{
    protected $fillable = ['document_type', 'year', 'next_number'];

    /**
     * Issue the next number for a document type. Must run inside a
     * transaction: the row is locked so two concurrent postings cannot be
     * handed the same invoice number.
     */
    public static function next(string $type, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $format = config("allva.numbering.{$type}", ['prefix' => strtoupper($type).'-{year}-', 'pad' => 5]);

        $row = static::query()
            ->where('document_type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            // Another request may create the same row first; the unique index
            // turns that race into a retry rather than a duplicate.
            try {
                $row = static::create(['document_type' => $type, 'year' => $year, 'next_number' => 1]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $row = static::query()
                    ->where('document_type', $type)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $number = (int) $row->next_number;
        $row->update(['next_number' => $number + 1]);

        $prefix = str_replace('{year}', (string) $year, $format['prefix']);

        return $prefix.str_pad((string) $number, (int) $format['pad'], '0', STR_PAD_LEFT);
    }

    /** Peek without consuming, for preview fields on create forms. */
    public static function peek(string $type, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $format = config("allva.numbering.{$type}", ['prefix' => strtoupper($type).'-{year}-', 'pad' => 5]);
        $next = (int) (DB::table('document_sequences')
            ->where('document_type', $type)->where('year', $year)->value('next_number') ?? 1);

        return str_replace('{year}', (string) $year, $format['prefix'])
            .str_pad((string) $next, (int) $format['pad'], '0', STR_PAD_LEFT);
    }
}
