<?php
namespace App\Service;

use App\Repository\DashboardRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class DashboardService {
    private const TIMEZONE  = 'Asia/Manila';
    private const MAX_LIMIT = 100;

    private const EMPLOYEE_FIELDS = ['id', 'full_name', 'role', 'profile_photo_url'];

    private DashboardRepository $repository;
    private SystemSettingService $settings;

    public function __construct(
        ?DashboardRepository $repository = null,
        ?SystemSettingService $settings = null
    ) {
        $this->repository = $repository ?? new DashboardRepository();
        $this->settings   = $settings   ?? new SystemSettingService();
    }

    public function getKpiSummary(?string $date = null): array {
        $day       = $this->resolveDate($date);
        $cutoff    = $this->lateCutoff();
        $isWorkDay = $this->isWorkDay($day);

        $kpi = $this->repository->getKpiSummary($day->format('Y-m-d'), $cutoff);

        if (!$isWorkDay) {
            $kpi['absent'] = 0;
        }

        $expected = $kpi['total_active'] - $kpi['on_leave'];

        $kpi['is_work_day']     = $isWorkDay;
        $kpi['late_cutoff']     = $cutoff;
        $kpi['attendance_rate'] = ($isWorkDay && $expected > 0)
            ? min(100.0, round($kpi['present'] / $expected * 100, 1))
            : null;

        return $kpi;
    }

    public function getTodayActivity(?string $date = null, int $limit = 20): array {
        $d = $this->resolveDate($date)->format('Y-m-d');
        $activity = $this->repository->getTodayActivity($d, $this->clampLimit($limit));

        $buckets = [];
        for ($h = 0; $h < 24; $h++) {
            $key = sprintf('%02d', $h);
            $buckets[$key] = $activity['buckets'][$key] ?? 0;
        }

        $activity['buckets'] = $buckets;
        $activity['total']   = array_sum($buckets);
        return $activity;
    }

    public function getFlaggedPunches(?string $date = null): array {
        return $this->repository->getFlaggedPunches($this->resolveDate($date)->format('Y-m-d'));
    }

    public function getEmployeesPunchedIn(?string $date = null): array {
        $rows = $this->repository->getEmployeesPunchedIn($this->resolveDate($date)->format('Y-m-d'));
        return array_map('intval', array_column($rows, 'id'));
    }

    public function getEmployeesNotPunchedIn(?string $date = null): array {
        $day = $this->resolveDate($date);
        if (!$this->isWorkDay($day)) {
            return [];
        }

        $rows = $this->repository->getEmployeesNotPunchedIn($day->format('Y-m-d'));
        return array_map(fn(array $r) => $this->publicEmployee($r), $rows);
    }

    public function getPayrollPending(): array {
        return $this->repository->getPayrollPending();
    }

    public function getRecentActivity(int $limit = 20): array {
        return $this->repository->getRecentActivity($this->clampLimit($limit));
    }

    private function resolveDate(?string $date): DateTimeImmutable {
        $tz    = new DateTimeZone(self::TIMEZONE);
        $today = new DateTimeImmutable('today', $tz);

        if ($date === null || $date === '') {
            return $today;
        }

        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date: {$date} (expected YYYY-MM-DD).");
        }
        if ($dt > $today) {
            throw new InvalidArgumentException("Date cannot be in the future: {$date}");
        }

        return $dt;
    }

    private function lateCutoff(): string {
        $start = DateTimeImmutable::createFromFormat('!H:i', $this->settings->getWorkStart());
        return $start->modify('+' . $this->settings->getLateThreshold() . ' minutes')->format('H:i:s');
    }

    private function isWorkDay(DateTimeImmutable $day): bool {
        return in_array(strtolower($day->format('D')), $this->settings->getWorkDays(), true);
    }

    private function clampLimit(int $limit): int {
        return max(1, min(self::MAX_LIMIT, $limit));
    }

    private function publicEmployee(array $row): array {
        return array_intersect_key($row, array_flip(self::EMPLOYEE_FIELDS));
    }
}