<?php

namespace App\Services\Accounting;

use RuntimeException;

/**
 * Raised whenever the ledger refuses an entry: an unbalanced journal, a closed
 * period, a header account, a missing tag. Every one of these is a rule the
 * scope of work asks the system to enforce rather than trust the operator on.
 */
class PostingException extends RuntimeException
{
    public static function unbalanced(float $debit, float $credit): self
    {
        return new self(sprintf(
            'The journal does not balance: debits %s, credits %s (difference %s).',
            number_format($debit, 2),
            number_format($credit, 2),
            number_format(abs($debit - $credit), 2),
        ));
    }

    public static function periodClosed(string $period): self
    {
        return new self("Period {$period} is not open, so nothing can be posted into it. Reopen the period or post to the current one.");
    }

    public static function noPeriod(string $date): self
    {
        return new self("No accounting period exists for {$date}. Create the fiscal year first.");
    }

    public static function notPostable(string $account): self
    {
        return new self("Account {$account} is a heading and cannot be posted to directly.");
    }

    public static function alreadyPosted(string $reference): self
    {
        return new self("Journal {$reference} has already been posted.");
    }

    public static function alreadyReversed(string $reference): self
    {
        return new self("Journal {$reference} has already been reversed.");
    }

    public static function emptyJournal(): self
    {
        return new self('A journal must have at least two lines.');
    }

    public static function missingTag(string $account, string $tag): self
    {
        return new self("Account {$account} requires a {$tag}. Scope of work §7: every expense entry carries an account, a cost centre and a project.");
    }

    public static function bothSides(int $lineNo): self
    {
        return new self("Line {$lineNo} has both a debit and a credit. Split it into two lines.");
    }

    public static function zeroLine(int $lineNo): self
    {
        return new self("Line {$lineNo} has no amount.");
    }
}
