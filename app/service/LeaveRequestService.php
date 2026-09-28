<?php
namespace App\Service;

use App\Entity\LeaveRequest;
use App\Repository\EmployeeRepository;
use App\Repository\LeaveRequestRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class LeaveRequestService {
    private const TIMEZONE = 'Asia/Manila';

    private const LEAVE_TYPES = [
        'vacation',
        'sick',
        'maternity',
        'paternity',
        'bereavement',
        'unpaid',
    ];

    private const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    private const BLOCKING_STATUSES = ['pending', 'approved'];

    private const MAX_REASON_LENGTH = 1000;

    private LeaveRequestRepository $repository;
    private EmployeeRepository $employees;

    public function __construct(
        ?LeaveRequestRepository $repository = null,
        ?EmployeeRepository $employees = null
    ) {
        $this->repository = $repository ?? new LeaveRequestRepository();
        $this->employees  = $employees  ?? new EmployeeRepository();
    }

    public function getAll(): array {
        return $this->repository->findAll();
    }

    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }

    public function getByEmployee(int $employeeId): array {
        return $this->repository->findByEmployee($employeeId);
    }

    public function getByStatus(string $status): array {
        $this->assertValidStatus($status);
        return $this->repository->findByStatus($status);
    }

    public function getByDateRange(string $start, string $end): array {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        return $this->repository->findByDateRange($start, $end);
    }

    public function create(array $data, int $actorId): int {
        $clean = $this->validate($data, false);
        $this->requireEmployee($clean['employee_id']);

        $this->assertNoOverlap(
            $clean['employee_id'],
            $clean['start_date'],
            $clean['end_date'],
            null
        );

        $clean['status']      = 'pending';
        $clean['approved_by'] = null;
        $clean['approved_at'] = null;

        return $this->repository->create(LeaveRequest::fromArray($clean));
    }

    public function update(int $id, array $data): bool {
        $existing = $this->requireRequest($id);

        if ($existing['status'] !== 'pending') {
            throw new DomainException("Only pending requests can be edited (current: {$existing['status']}).");
        }

        $clean = $this->validate($data, true);

        $start = $clean['start_date'] ?? $existing['start_date'];
        $end   = $clean['end_date']   ?? $existing['end_date'];

        $this->assertDateOrder($start, $end);
        $this->assertNoOverlap((int) $existing['employee_id'], $start, $end, $id);

        $clean['start_date'] = $start;
        $clean['end_date']   = $end;

        return $this->repository->update($id, LeaveRequest::fromArray($clean));
    }

    public function delete(int $id): bool {
        $existing = $this->requireRequest($id);

        if ($existing['status'] !== 'pending') {
            throw new DomainException("Only pending requests can be deleted (current: {$existing['status']}).");
        }

        return $this->repository->delete($id);
    }

    public function approve(int $id, int $approvedBy): bool {
        $existing = $this->requireRequest($id);

        if ($existing['status'] !== 'pending') {
            throw new DomainException("Only pending requests can be approved (current: {$existing['status']}).");
        }

        $this->assertNoOverlap(
            (int) $existing['employee_id'],
            $existing['start_date'],
            $existing['end_date'],
            $id,
            ['approved']
        );

        return $this->repository->approve($id, $approvedBy);
    }

    public function reject(int $id, int $approvedBy, ?string $reason = null): bool {
        $existing = $this->requireRequest($id);

        if ($existing['status'] !== 'pending') {
            throw new DomainException("Only pending requests can be rejected (current: {$existing['status']}).");
        }

        if ($reason !== null) {
            $reason = trim($reason);
            if ($reason === '') {
                $reason = null;
            } elseif (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
                throw new InvalidArgumentException(
                    'Rejection reason must be ' . self::MAX_REASON_LENGTH . ' characters or fewer.'
                );
            }
        }

        return $this->repository->reject($id, $approvedBy, $reason);
    }

    public function cancel(int $id): bool {
        $existing = $this->requireRequest($id);

        if (!in_array($existing['status'], ['pending', 'approved'], true)) {
            throw new DomainException("Only pending or approved requests can be cancelled (current: {$existing['status']}).");
        }

        return $this->repository->cancel($id);
    }

    public function hasApprovedLeave(int $employeeId, string $date): bool {
        $this->assertValidDate($date, 'date');
        return $this->repository->hasApprovedLeave($employeeId, $date);
    }

    public function getLeaveDays(int $id): int {
        $request = $this->repository->findById($id);
        if (!$request) {
            return 0;
        }

        $start = new DateTimeImmutable($request['start_date'], new DateTimeZone(self::TIMEZONE));
        $end   = new DateTimeImmutable($request['end_date'],   new DateTimeZone(self::TIMEZONE));

        return (int) $start->diff($end)->days + 1;
    }

    public function countByEmployeeAndYear(int $employeeId, int $year): int {
        $this->assertValidYear($year);
        return $this->repository->countByEmployeeAndYear($employeeId, $year);
    }

    public function countByTypeForEmployee(int $employeeId, int $year): array {
        $this->assertValidYear($year);
        return $this->repository->countByEmployeeYearAndType($employeeId, $year);
    }

    public function checkOverlap(int $employeeId, string $start, string $end, ?int $exceptId = null): bool {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        $this->assertNoOverlap($employeeId, $start, $end, $exceptId);
        return false;
    }

    private function validate(array $data, bool $isUpdate): array {
        $errors = [];

        $employeeId = $this->validateEmployeeId($data, $isUpdate, $errors);
        $leaveType  = $this->validateLeaveType($data, $isUpdate, $errors);
        $startDate  = $this->validateStartDate($data, $isUpdate, $errors);
        $endDate    = $this->validateEndDate($data, $isUpdate, $errors);
        $reason     = $this->validateReason($data, $errors);

        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            $errors['end_date'] = 'End date must be on or after the start date.';
        }

        if ($errors) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }

        $clean = array_filter([
            'employee_id' => $employeeId,
            'leave_type'  => $leaveType,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'reason'      => $reason,
        ], fn($v) => $v !== null);

        if ($isUpdate && !$clean) {
            throw new InvalidArgumentException('No updatable fields provided.');
        }

        return $clean;
    }

    private function validateEmployeeId(array $data, bool $isUpdate, array &$errors): ?int {
        if ($isUpdate && !array_key_exists('employee_id', $data)) {
            return null;
        }

        $raw = $data['employee_id'] ?? null;
        if ($raw === null || $raw === '') {
            $errors['employee_id'] = 'Employee is required.';
            return null;
        }

        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $errors['employee_id'] = 'Employee ID must be a positive integer.';
            return null;
        }

        return (int) $id;
    }

    private function validateLeaveType(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('leave_type', $data)) {
            return null;
        }

        $type = trim((string) ($data['leave_type'] ?? ''));
        if ($type === '') {
            $errors['leave_type'] = 'Leave type is required.';
            return null;
        }
        if (!in_array($type, self::LEAVE_TYPES, true)) {
            $errors['leave_type'] = 'Leave type must be one of: ' . implode(', ', self::LEAVE_TYPES) . '.';
            return null;
        }

        return $type;
    }

    private function validateStartDate(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('start_date', $data)) {
            return null;
        }

        $raw = trim((string) ($data['start_date'] ?? ''));
        if ($raw === '') {
            $errors['start_date'] = 'Start date is required.';
            return null;
        }
        if (!$this->isValidDate($raw)) {
            $errors['start_date'] = 'Start date must be a valid YYYY-MM-DD.';
            return null;
        }

        return $raw;
    }

    private function validateEndDate(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('end_date', $data)) {
            return null;
        }

        $raw = trim((string) ($data['end_date'] ?? ''));
        if ($raw === '') {
            $errors['end_date'] = 'End date is required.';
            return null;
        }
        if (!$this->isValidDate($raw)) {
            $errors['end_date'] = 'End date must be a valid YYYY-MM-DD.';
            return null;
        }

        return $raw;
    }

    private function validateReason(array $data, array &$errors): ?string {
        if (!array_key_exists('reason', $data)) {
            return null;
        }

        $reason = $data['reason'];
        if ($reason === null) {
            return null;
        }

        $reason = trim((string) $reason);
        if ($reason === '') {
            return null;
        }
        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            $errors['reason'] = 'Reason must be ' . self::MAX_REASON_LENGTH . ' characters or fewer.';
            return null;
        }

        return $reason;
    }

    private function assertNoOverlap(
        int $employeeId,
        string $start,
        string $end,
        ?int $exceptId,
        array $statuses = self::BLOCKING_STATUSES
    ): void {
        if ($this->repository->hasOverlappingRequest($employeeId, $start, $end, $statuses, $exceptId)) {
            throw new DomainException(sprintf(
                'The date range %s to %s overlaps an existing %s leave.',
                $start,
                $end,
                implode('/', $statuses)
            ));
        }
    }

    private function requireRequest(int $id): array {
        $request = $this->repository->findById($id);
        if (!$request) {
            throw new DomainException("Leave request not found: {$id}");
        }
        return $request;
    }

    private function requireEmployee(int $employeeId): void {
        if (!$this->employees->exists($employeeId)) {
            throw new DomainException("Employee not found: {$employeeId}");
        }
    }

    private function assertValidDate(string $date, string $field): void {
        if (!$this->isValidDate($date)) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }

    private function isValidDate(string $date): bool {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TIMEZONE));
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }

    private function assertDateOrder(string $start, string $end): void {
        if ($start > $end) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }

    private function assertValidStatus(string $status): void {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Status must be one of: ' . implode(', ', self::STATUSES) . '.');
        }
    }

    private function assertValidYear(int $year): void {
        if ($year < 2000 || $year > (int) date('Y') + 1) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }
    }
}