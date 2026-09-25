<?php

namespace App\Domain\Core\Money;

/**
 * ISO 4217 currencies seeded into the group-wide currencies table.
 * Entities choose which ones they transact in.
 */
final class CurrencyList
{
    /**
     * @return array<string, array{0: string, 1: int}> code => [name, decimals]
     */
    public static function iso(): array
    {
        return [
            'AED' => ['UAE Dirham', 2],
            'AUD' => ['Australian Dollar', 2],
            'BDT' => ['Bangladeshi Taka', 2],
            'CAD' => ['Canadian Dollar', 2],
            'CHF' => ['Swiss Franc', 2],
            'CNY' => ['Chinese Yuan', 2],
            'DKK' => ['Danish Krone', 2],
            'ETB' => ['Ethiopian Birr', 2],
            'EUR' => ['Euro', 2],
            'GBP' => ['Pound Sterling', 2],
            'HKD' => ['Hong Kong Dollar', 2],
            'INR' => ['Indian Rupee', 2],
            'JPY' => ['Japanese Yen', 0],
            'KES' => ['Kenyan Shilling', 2],
            'LKR' => ['Sri Lankan Rupee', 2],
            'MYR' => ['Malaysian Ringgit', 2],
            'NOK' => ['Norwegian Krone', 2],
            'NZD' => ['New Zealand Dollar', 2],
            'OMR' => ['Omani Rial', 3],
            'PKR' => ['Pakistani Rupee', 2],
            'QAR' => ['Qatari Riyal', 2],
            'RWF' => ['Rwandan Franc', 0],
            'SAR' => ['Saudi Riyal', 2],
            'SEK' => ['Swedish Krona', 2],
            'SGD' => ['Singapore Dollar', 2],
            'THB' => ['Thai Baht', 2],
            'TZS' => ['Tanzanian Shilling', 2],
            'UGX' => ['Ugandan Shilling', 0],
            'USD' => ['US Dollar', 2],
            'ZAR' => ['South African Rand', 2],
        ];
    }
}
