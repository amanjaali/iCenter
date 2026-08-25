<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** The side on which a balance in this account is positive. */
    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::Asset, self::Expense => NormalBalance::Debit,
            self::Liability, self::Equity, self::Revenue => NormalBalance::Credit,
        };
    }

    /** Balance sheet accounts carry forward; P&L accounts reset each year. */
    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }

    public function isProfitAndLoss(): bool
    {
        return ! $this->isBalanceSheet();
    }

    public static function fromCode(string $code): self
    {
        return match (substr($code, 0, 1)) {
            '1' => self::Asset,
            '2' => self::Liability,
            '3' => self::Equity,
            '4' => self::Revenue,
            default => self::Expense,
        };
    }
}
