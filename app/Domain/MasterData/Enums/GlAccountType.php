<?php

namespace App\Domain\MasterData\Enums;

enum GlAccountType: string
{
    case Capex = 'capex';
    case Opex = 'opex';
    case BalanceSheet = 'balance_sheet';
    case Revenue = 'revenue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Capex => __('Capex'),
            self::Opex => __('OpEx'),
            self::BalanceSheet => __('Balance sheet'),
            self::Revenue => __('Revenue'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
