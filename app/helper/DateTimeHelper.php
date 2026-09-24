<?php
namespace App\Helper;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

class DateTimeHelper
{
    /**
     * Application timezone. Override via env if needed.
     */
    private const TZ = 'Asia/Manila';

    /**
     * Standard storage format (MySQL DATETIME).
     */
    private const STORAGE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Short date format used for display of dates without time.
     */
    private const DATE_FORMAT = 'M d, Y';

    /**
     * Safely construct a DateTimeImmutable in the app timezone.
     */
    private static function make(string $datetime): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($datetime, new DateTimeZone(self::TZ));
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Current datetime as 'Y-m-d H:i:s' in the application timezone.
     */
    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))
            ->format(self::STORAGE_FORMAT);
    }

    /**
     * Today's date as 'Y-m-d'.
     */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))
            ->format('Y-m-d');
    }

    /**
     * Format a datetime string with a custom format. Defaults to 12-hour time.
     */
    public static function format(string $datetime, string $format = 'h:i A'): string
    {
        $dt = self::make($datetime);
        return $dt ? $dt->format($format) : '';
    }

    /**
     * Format a date string in a friendly display format (e.g. "Sep 23, 2026").
     */
    public static function formatDate(string $date): string
    {
        $dt = self::make($date);
        return $dt ? $dt->format(self::DATE_FORMAT) : '';
    }

    /**
     * Difference in hours between two datetimes as a float.
     * Positive when $end is after $start, negative otherwise.
     */
    public static function diffInHours(string $start, string $end): float
    {
        $a = self::make($start);
        $b = self::make($end);

        if ($a === null || $b === null) {
            return 0.0;
        }

        $seconds = $b->getTimestamp() - $a->getTimestamp();
        return round($seconds / 3600, 2);
    }

    /**
     * Difference in whole minutes between two datetimes.
     */
    public static function diffInMinutes(string $start, string $end): int
    {
        $a = self::make($start);
        $b = self::make($end);

        if ($a === null || $b === null) {
            return 0;
        }

        $seconds = $b->getTimestamp() - $a->getTimestamp();
        return (int) round($seconds / 60);
    }

    /**
     * Start of the ISO week (Monday) for a given date, as 'Y-m-d'.
     */
    public static function startOfWeek(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('monday this week')->format('Y-m-d');
    }

    /**
     * End of the ISO week (Sunday) for a given date, as 'Y-m-d'.
     */
    public static function endOfWeek(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('sunday this week')->format('Y-m-d');
    }

    /**
     * First day of the month for a given date, as 'Y-m-d'.
     */
    public static function startOfMonth(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('first day of this month')->format('Y-m-d');
    }

    /**
     * Last day of the month for a given date, as 'Y-m-d'.
     */
    public static function endOfMonth(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('last day of this month')->format('Y-m-d');
    }

    /**
     * First day of the given year, as 'Y-m-d'.
     */
    public static function startOfYear(int $year): string
    {
        return sprintf('%04d-01-01', $year);
    }

    /**
     * Last day of the given year, as 'Y-m-d'.
     */
    public static function endOfYear(int $year): string
    {
        return sprintf('%04d-12-31', $year);
    }

    /**
     * Whether the date falls on Saturday or Sunday.
     */
    public static function isWeekend(string $date): bool
    {
        $dt = self::make($date);
        if ($dt === null) {
            return false;
        }

        $dow = (int) $dt->format('N'); // 1 = Mon ... 7 = Sun
        return $dow >= 6;
    }

    /**
     * Whether the date is a Sunday.
     */
    public static function isSunday(string $date): bool
    {
        $dt = self::make($date);
        if ($dt === null) {
            return false;
        }

        return $dt->format('N') === '7';
    }

    /**
     * Human-readable month name for a 1..12 month number.
     */
    public static function getMonthName(int $month): string
    {
        if ($month < 1 || $month > 12) {
            return '';
        }

        // DateTimeImmutable handles localization-friendly month names via format('F'),
        // but for a plain English name we use the switch — deterministic and dependency-free.
        static $names = [
            1  => 'January',
            2  => 'February',
            3  => 'March',
            4  => 'April',
            5  => 'May',
            6  => 'June',
            7  => 'July',
            8  => 'August',
            9  => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];

        return $names[$month] ?? '';
    }
}