<?php
namespace App\Service;

use App\Helper\DateTimeHelper;
use App\Repository\EmployeeRepository;
use App\Repository\SundayDutyRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

// Sunday duty roster: one team per Sunday, picked by the owner (a lead + their team).
// Sunday work is paid at 130% (PayrollService); punches on an unrostered Sunday get flagged.
class SundayDutyService {
    private const TIMEZONE  = 'Asia/Manila';
    private const MAX_NOTES = 255;
    private const MAX_TEAM  = 50;

    private SundayDutyRepository $repository;
    private EmployeeRepository $employees;
    private SystemSettingService $settings;
    private AuditService $audit;

    public function __construct(
        ?SundayDutyRepository $repository = null,
        ?EmployeeRepository $employees = null,
        ?SystemSettingService $settings = null,
        ?AuditService $audit = null
    ) {
        $this->repository = $repository ?? new SundayDutyRepository();
        $this->employees  = $employees  ?? new EmployeeRepository();
        $this->settings   = $settings   ?? new SystemSettingService();
        $this->audit      = $audit      ?? new AuditService();
    }

    public function getRange(string $start, string $end): array {
        $this->assertDate($start, 'start');
        $this->assertDate($end, 'end');
        if ($start > $end) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
        return $this->repository->findByDateRange($start, $end);
    }

    public function getUpcomingForEmployee(int $employeeId, int $limit = 4): array {
        return $this->repository->findUpcomingForEmployee($employeeId, DateTimeHelper::today(), $limit);
    }

    public function isAssigned(int $employeeId, string $date): bool {
        return $this->repository->isAssigned($employeeId, $date);
    }

    public function isDutyDay(string $date): bool {
        $day = strtolower((new DateTimeImmutable($date, new DateTimeZone(self::TIMEZONE)))->format('D'));
        return $day === $this->settings->getRestDay();
    }

    // Create or replace the roster for one Sunday. Returns the saved duty.
    public function save(array $data, int $actorId, ?int $id = null): array {
        $clean = $this->validate($data);

        $takenBy = $this->repository->findIdByDate($clean['duty_date']);
        if ($takenBy !== null && $takenBy !== $id) {
            throw new DomainException("{$clean['duty_date']} already has a duty team. Edit that one instead.");
        }
        if ($id !== null && !$this->repository->findById($id)) {
            throw new DomainException("Sunday duty not found: {$id}");
        }

        $savedId = $this->repository->transaction(function () use ($clean, $actorId, $id) {
            if ($id === null) {
                $id = $this->repository->create($clean['duty_date'], $clean['lead_employee_id'], $clean['notes'], $actorId ?: null);
            } else {
                $this->repository->update($id, $clean['duty_date'], $clean['lead_employee_id'], $clean['notes']);
            }
            $this->repository->replaceMembers($id, $clean['member_ids']);
            return $id;
        });

        $this->audit->record($id === null ? 'SUNDAY_DUTY_CREATE' : 'SUNDAY_DUTY_UPDATE', null, [
            'duty_id'   => $savedId,
            'duty_date' => $clean['duty_date'],
            'lead_id'   => $clean['lead_employee_id'],
            'members'   => $clean['member_ids'],
        ]);

        return $this->repository->findById($savedId);
    }

    public function delete(int $id): void {
        $duty = $this->repository->findById($id);
        if (!$duty) {
            throw new DomainException("Sunday duty not found: {$id}");
        }
        $this->repository->delete($id);
        $this->audit->record('SUNDAY_DUTY_DELETE', null, ['duty_id' => $id, 'duty_date' => $duty['duty_date']]);
    }

    private function validate(array $data): array {
        $date = trim((string) ($data['duty_date'] ?? ''));
        $this->assertDate($date, 'duty_date');
        if (!$this->isDutyDay($date)) {
            throw new InvalidArgumentException("{$date} is not a Sunday.");
        }

        $leadId = (int) ($data['lead_employee_id'] ?? 0);
        $this->requireActiveEmployee($leadId, 'Lead');

        $memberIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($data['member_ids'] ?? null) ? $data['member_ids'] : []),
            fn(int $v) => $v > 0
        )));
        if (count($memberIds) > self::MAX_TEAM) {
            throw new InvalidArgumentException('A duty team can have at most ' . self::MAX_TEAM . ' people.');
        }
        foreach ($memberIds as $memberId) {
            $this->requireActiveEmployee($memberId, 'Team member');
        }
        if (!in_array($leadId, $memberIds, true)) {
            array_unshift($memberIds, $leadId); // the lead is always on duty
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) > self::MAX_NOTES) {
            throw new InvalidArgumentException('Notes must be ' . self::MAX_NOTES . ' characters or fewer.');
        }

        return [
            'duty_date'        => $date,
            'lead_employee_id' => $leadId,
            'member_ids'       => $memberIds,
            'notes'            => $notes === '' ? null : $notes,
        ];
    }

    private function requireActiveEmployee(int $id, string $label): void {
        $employee = $id > 0 ? $this->employees->findById($id) : null;
        if (!$employee) {
            throw new InvalidArgumentException("{$label} not found.");
        }
        if (!$employee->getIsActive()) {
            throw new InvalidArgumentException("{$label} {$employee->getFullName()} is inactive.");
        }
    }

    private function assertDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid {$field}: use YYYY-MM-DD.");
        }
    }
}
