<?php
namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

// Which periods are valid pay periods for each frequency, and where a payday falls in its month.
// biweekly: paid every other Saturday. biweekly_anchor (system_settings) is any one of those paydays;
// a period is the 14 days ending on a payday (Sun → Sat, 12 Mon–Sat working days).
class PayCycleService {
    public const ANCHOR_KEY = 'biweekly_anchor';
    private const TIMEZONE  = 'Asia/Manila';

    private SystemSettingService $settings;

    public function __construct(?SystemSettingService $settings = null) {
        $this->settings = $settings ?? new SystemSettingService();
    }

    // null = period fits this frequency, otherwise the reason it doesn't.
    public function periodError(string $frequency, string $start, string $end): ?string {
        $s = $this->date($start);
        $e = $this->date($end);

        switch ($frequency) {
            case 'biweekly':
                if (!$this->isBiweeklyPayday($e)) {
                    return "{$end} is not a bi-weekly payday. Next payday: " . $this->nextBiweeklyPayday($e)->format('Y-m-d') . '.';
                }
                $expected = $e->modify('-13 days')->format('Y-m-d');
                return $start === $expected ? null : "A bi-weekly period ending {$end} must start on {$expected}.";

            case 'kinsenas':
                $first  = $s->format('Y-m-01');
                $last   = $s->format('Y-m-t');
                $mid    = $s->format('Y-m-15');
                $second = $s->format('Y-m-16');
                return ($start === $first && $end === $mid) || ($start === $second && $end === $last)
                    ? null : 'Kinsenas periods are the 1st–15th or the 16th–end of the month.';

            case 'monthly':
                return $start === $s->format('Y-m-01') && $end === $s->format('Y-m-t')
                    ? null : 'Monthly periods run from the 1st to the last day of the month.';

            default:
                return null; // weekly: any range
        }
    }

    /** [paydays in the month of $periodEnd, which one $periodEnd is (1-based)] */
    public function biweeklySlot(string $periodEnd): array {
        $end   = $this->date($periodEnd);
        $day   = $this->nextBiweeklyPayday($end->modify('first day of this month')->modify('-1 day'));
        $month = $end->format('Y-m');

        $count = 0;
        $index = 1;
        while ($day->format('Y-m') === $month) {
            $count++;
            if ($day <= $end) {
                $index = $count;
            }
            $day = $day->modify('+14 days');
        }
        return [max($count, 1), $index];
    }

    // First bi-weekly payday after $from (strictly later).
    public function nextBiweeklyPayday(DateTimeImmutable $from): DateTimeImmutable {
        $diff = $this->daysFromAnchor($from);
        $add  = 14 - ((($diff % 14) + 14) % 14);
        return $from->modify("+{$add} days");
    }

    public function isBiweeklyPayday(DateTimeImmutable $date): bool {
        return $this->daysFromAnchor($date) % 14 === 0;
    }

    private function daysFromAnchor(DateTimeImmutable $date): int {
        $anchor = $this->anchor();
        $days   = (int) $anchor->diff($date)->format('%a');
        return $date < $anchor ? -$days : $days;
    }

    private function anchor(): DateTimeImmutable {
        $value  = (string) $this->settings->getValue(self::ANCHOR_KEY, '');
        $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::TIMEZONE));
        if (!$anchor || $anchor->format('Y-m-d') !== $value) {
            throw new DomainException('Set biweekly_anchor in system_settings to one bi-weekly payday (YYYY-MM-DD).');
        }
        return $anchor;
    }

    private function date(string $value): DateTimeImmutable {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::TIMEZONE));
        if (!$d || $d->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Invalid date: {$value}");
        }
        return $d;
    }
}