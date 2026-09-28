<?php
namespace App\Service;

use App\Entity\TimePunch;
use App\Helper\DateTimeHelper;
use App\Helper\IpHelper;
use App\Repository\AuditTrailRepository;
use App\Repository\TimePunchRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class TimePunchService {
    private const TIMEZONE = 'Asia/Manila';

    private const PUNCH_SEQUENCE = ['AM_IN', 'AM_OUT', 'PM_IN', 'PM_OUT', 'OT_IN', 'OT_OUT'];

    private const PUNCH_LABELS = [
        'AM_IN'  => 'Morning In',
        'AM_OUT' => 'Morning Out',
        'PM_IN'  => 'Afternoon In',
        'PM_OUT' => 'Afternoon Out',
        'OT_IN'  => 'Overtime In',
        'OT_OUT' => 'Overtime Out',
    ];

    private TimePunchRepository $repository;
    private AuditTrailRepository $audit;

    public function __construct(
        ?TimePunchRepository $repository = null,
        ?AuditTrailRepository $audit = null
    ) {
        $this->repository = $repository ?? new TimePunchRepository();
        $this->audit      = $audit      ?? new AuditTrailRepository();
    }

    public function record(int $employeeId, string $punchType, array $locationData = []): int {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
        if (!in_array($punchType, self::PUNCH_SEQUENCE, true)) {
            throw new InvalidArgumentException("Unknown punch type: {$punchType}");
        }

        $now      = DateTimeHelper::now();
        $workDate = DateTimeHelper::today();

        $error = $this->validatePunch($employeeId, $punchType, $workDate);
        if ($error !== null) {
            throw new DomainException($error);
        }

        $punch = TimePunch::fromArray([
            'employee_id'        => $employeeId,
            'work_date'          => $workDate,
            'punch_type'         => $punchType,
            'punch_time'         => $now,
            'ip_address'         => $locationData['ip_address']         ?? IpHelper::getClientIp(),
            'gps_lat'            => $locationData['gps_lat']            ?? null,
            'gps_lng'            => $locationData['gps_lng']            ?? null,
            'gps_accuracy'       => $locationData['gps_accuracy']       ?? null,
            'device_fingerprint' => $locationData['device_fingerprint'] ?? null,
        ]);

        $id = $this->repository->create($punch);

        $this->audit->log($employeeId, 'PUNCH', [
            'punch_type' => $punchType,
            'punch_id'   => $id,
            'work_date'  => $workDate,
        ], $punch->getIpAddress());

        return $id;
    }

    public function validatePunch(int $employeeId, string $punchType, string $date): ?string {
        if ($employeeId < 1) {
            return 'Invalid employee.';
        }
        if (!in_array($punchType, self::PUNCH_SEQUENCE, true)) {
            return "Unknown punch type: {$punchType}";
        }

        $last = $this->repository->getLastPunchOfDay($employeeId, $date);
        if ($last === null) {
            return $punchType === 'AM_IN' ? null : 'The first punch of the day must be AM_IN.';
        }

        $lastType = $last->getPunchType();
        if ($lastType === $punchType) {
            return "Duplicate punch: {$punchType} was already recorded today.";
        }

        $lastIdx = array_search($lastType, self::PUNCH_SEQUENCE, true);
        $newIdx  = array_search($punchType, self::PUNCH_SEQUENCE, true);

        if ($lastIdx === false || $newIdx === false) {
            return 'Unknown punch type in stored data.';
        }

        if ($newIdx === $lastIdx + 1) {
            return null;
        }

        $expected = self::PUNCH_SEQUENCE[$lastIdx + 1] ?? null;

        if ($expected === null) {
            return 'No further punches are allowed today.';
        }

        if ($newIdx <= $lastIdx) {
            return "Out of order: {$lastType} was already recorded. Expected {$expected} next.";
        }

        return "Missing punch: {$expected} must be recorded before {$punchType}.";
    }

    public function getTodayStatus(int $employeeId): array {
        $date    = DateTimeHelper::today();
        $punches = $this->repository->findByEmployeeAndDate($employeeId, $date);

        $byType = [];
        foreach ($punches as $p) {
            $byType[$p->getPunchType()] = $p;
        }

        $next = $this->calculateNextPunchType($employeeId, $date);

        $slots = [];
        foreach (self::PUNCH_SEQUENCE as $type) {
            $punch = $byType[$type] ?? null;

            $slots[] = [
                'punch_type'         => $type,
                'label'              => self::PUNCH_LABELS[$type],
                'punched'            => $punch !== null,
                'punch_time'         => $punch?->getPunchTime(),
                'punch_time_display' => $punch ? DateTimeHelper::formatLong($punch->getPunchTime()) : null,
                'is_flagged'         => $punch?->isFlagged() ?? false,
                'flag_reason'        => $punch?->getFlagReason(),
                'is_next'            => $type === $next,
            ];
        }

        return [
            'work_date'  => $date,
            'next_punch' => $next,
            'slots'      => $slots,
            'completed'  => $next === null,
        ];
    }

    public function getPunchHistory(int $employeeId, string $start, string $end): array {
        $this->assertDateRange($start, $end);

        $punches = $this->repository->findByEmployeeAndDateRange($employeeId, $start, $end);

        $grouped = [];
        foreach ($punches as $p) {
            $date = $p->getWorkDate();
            $grouped[$date][] = $this->presentPunch($p);
        }

        return [
            'employee_id' => $employeeId,
            'start'       => $start,
            'end'         => $end,
            'days'        => $grouped,
            'total'       => count($punches),
        ];
    }

    public function getLatestPunch(int $employeeId): ?array {
        $punch = $this->repository->findLatestByEmployee($employeeId);
        return $punch ? $this->presentPunch($punch) : null;
    }

    public function canPunch(int $employeeId, string $punchType): bool {
        if ($employeeId < 1) {
            return false;
        }
        if (!in_array($punchType, self::PUNCH_SEQUENCE, true)) {
            return false;
        }

        return $this->validatePunch($employeeId, $punchType, DateTimeHelper::today()) === null;
    }

    public function calculateNextPunchType(int $employeeId, string $date): ?string {
        $last = $this->repository->getLastPunchOfDay($employeeId, $date);
        if ($last === null) {
            return self::PUNCH_SEQUENCE[0];
        }

        $lastIdx = array_search($last->getPunchType(), self::PUNCH_SEQUENCE, true);
        if ($lastIdx === false) {
            return null;
        }

        return self::PUNCH_SEQUENCE[$lastIdx + 1] ?? null;
    }

    public function reversePunch(int $punchId, int $requestedBy): bool {
        if ($punchId < 1) {
            throw new InvalidArgumentException('Invalid punch ID.');
        }
        if ($requestedBy < 1) {
            throw new InvalidArgumentException('Invalid requester ID.');
        }

        $punch = $this->repository->findById($punchId);
        if ($punch === null) {
            throw new DomainException("Punch #{$punchId} not found.");
        }

        $this->audit->log($punch->getEmployeeId(), 'PUNCH_REVERSE', [
            'punch_id'     => $punchId,
            'requested_by' => $requestedBy,
            'work_date'    => $punch->getWorkDate(),
            'punch_type'   => $punch->getPunchType(),
            'punch_time'   => $punch->getPunchTime(),
            'ip_address'   => $punch->getIpAddress(),
        ], $punch->getIpAddress());

        return $this->repository->deleteById($punchId);
    }

    public function getFlaggedPunches(?string $date = null): array {
        $rows = $this->repository->findFlagged($date);

        return array_map(function (array $row) {
            return [
                'id'                 => (int) $row['id'],
                'employee_id'        => (int) $row['employee_id'],
                'employee_name'      => $row['full_name'] ?? null,
                'work_date'          => $row['work_date'],
                'punch_type'         => $row['punch_type'],
                'label'              => self::PUNCH_LABELS[$row['punch_type']] ?? $row['punch_type'],
                'punch_time'         => $row['punch_time'],
                'punch_time_display' => DateTimeHelper::formatLong((string) $row['punch_time']),
                'ip_address'         => $row['ip_address'],
                'flag_reason'        => $row['flag_reason'],
                'is_flagged'         => (bool) $row['is_flagged'],
                'reviewed_by'        => $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
                'reviewed_at'        => $row['reviewed_at'],
            ];
        }, $rows);
    }

    private function presentPunch(TimePunch $punch): array {
        return [
            'id'                 => $punch->getId(),
            'employee_id'        => $punch->getEmployeeId(),
            'work_date'          => $punch->getWorkDate(),
            'punch_type'         => $punch->getPunchType(),
            'label'              => self::PUNCH_LABELS[$punch->getPunchType()] ?? $punch->getPunchType(),
            'punch_time'         => $punch->getPunchTime(),
            'punch_time_display' => DateTimeHelper::formatLong((string) $punch->getPunchTime()),
            'ip_address'         => $punch->getIpAddress(),
            'is_flagged'         => $punch->isFlagged(),
            'flag_reason'        => $punch->getFlagReason(),
        ];
    }

    private function assertDateRange(string $start, string $end): void {
        $tz = new DateTimeZone(self::TIMEZONE);

        $s = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $tz);
        if (!$s || $s->format('Y-m-d') !== $start) {
            throw new InvalidArgumentException("Invalid start date: {$start}");
        }

        $e = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $tz);
        if (!$e || $e->format('Y-m-d') !== $end) {
            throw new InvalidArgumentException("Invalid end date: {$end}");
        }

        if ($s > $e) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }
}