<?php
namespace App\Helper;

class MoneyHelper{
    private const CURRENCIES = [
        'PHP' => ['symbol' => '₱', 'decimals' => 2],
        'USD' => ['symbol' => '$', 'decimals' => 2],
        'EUR' => ['symbol' => '€', 'decimals' => 2],
        'GBP' => ['symbol' => '£', 'decimals' => 2],
        'JPY' => ['symbol' => '¥', 'decimals' => 0], // no minor unit
        'KRW' => ['symbol' => '₩', 'decimals' => 0],
    ];

    /**
     * Format an amount with the correct symbol and thousands separators.
     */
    public static function format(float $amount, string $currency = 'PHP'): string {
        $currency = strtoupper($currency);
        $meta = self::CURRENCIES[$currency] ?? ['symbol' => $currency . ' ', 'decimals' => 2];

        $formatted = number_format(
            $amount,
            $meta['decimals'],
            '.',
            ','
        );

        return $meta['symbol'] . $formatted;
    }
    /**
     * Round an amount to a given number of decimals using "half away from zero"
     * (banker-friendly for invoices).
     */
    public static function round(float $amount, int $decimals = 2): float {
        if ($decimals < 0) {
            $decimals = 0;
        }

        $rounded = round(abs($amount), $decimals, PHP_ROUND_HALF_UP);
        return $amount < 0 ? -$rounded : $rounded;
    }

    /**
     * Convert a decimal amount to the smallest currency unit (e.g. centavos).
     */
    public static function toCents(float $amount): int {
        return (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Convert an integer number of cents back to a decimal amount.
     */
    public static function fromCents(int $cents): float {
        return $cents / 100;
    }

    /**
     * Calculate a percentage of an amount, rounded to 2 decimals.
     * Example: percentage(1000.00, 12.0) => 120.00
     */
    public static function percentage(float $amount, float $percent): float {
        return self::round($amount * ($percent / 100), 2);
    }
}