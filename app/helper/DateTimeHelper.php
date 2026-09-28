<?php
namespace App\Helper;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

class DateTimeHelper
{
    private const TZ = 'Asia/Manila';
    private const STORAGE_FORMAT = 'Y-m-d H:i:s';
    private const DATE_FORMAT = 'M d, Y';
    private const LONG_DISPLAY_FORMAT = 'F d, Y | g:ia';

    private static function make(string $datetime): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($datetime, new DateTimeZone(self::TZ));
        } catch (Exception $e) {
            return null;
        }
    }

    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))
            ->format(self::STORAGE_FORMAT);
    }

    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))
            ->format('Y-m-d');
    }

    public static function format(string $datetime, string $format = 'h:i A'): string
    {
        $dt = self::make($datetime);
        return $dt ? $dt->format($format) : '';
    }

    public static function formatDate(string $date): string
    {
        $dt = self::make($date);
        return $dt ? $dt->format(self::DATE_FORMAT) : '';
    }

    public static function formatLong(string $datetime): string
    {
        $dt = self::make($datetime);
        return $dt ? $dt->format(self::LONG_DISPLAY_FORMAT) : '';
    }

    public static function minutesAgo(int $minutes): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))
            ->modify("-{$minutes} minutes")
            ->format(self::STORAGE_FORMAT);
    }

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

    public static function startOfWeek(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('monday this week')->format('Y-m-d');
    }

    public static function endOfWeek(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('sunday this week')->format('Y-m-d');
    }

    public static function startOfMonth(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('first day of this month')->format('Y-m-d');
    }

    public static function endOfMonth(string $date): string
    {
        $dt = self::make($date);
        if ($dt === null) {
            return '';
        }

        return $dt->modify('last day of this month')->format('Y-m-d');
    }

    public static function startOfYear(int $year): string
    {
        return sprintf('%04d-01-01', $year);
    }

    public static function endOfYear(int $year): string
    {
        return sprintf('%04d-12-31', $year);
    }

    public static function isWeekend(string $date): bool
    {
        $dt = self::make($date);
        if ($dt === null) {
            return false;
        }

        $dow = (int) $dt->format('N'); // 1 = Mon ... 7 = Sun
        return $dow >= 6;
    }

    public static function isSunday(string $date): bool
    {
        $dt = self::make($date);
        if ($dt === null) {
            return false;
        }

        return $dt->format('N') === '7';
    }

    public static function getMonthName(int $month): string
    {
        if ($month < 1 || $month > 12) {
            return '';
        }
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