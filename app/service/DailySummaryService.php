<?php
namespace App\Service;

use App\Entity\LeaveRequest;
use App\Repository\LeaveRequestRepository;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

class LeaveRequestService {
    private const ALLOWED_LEAVE_TYPES = [
        'vacation',
        'sick',
        'maternity',
        'paternity',
        'bereavement',
        'unpaid',
        'emergency',
    ];

    private const ALLOWED_STATUSES = [
        'pending',
        'approved',
        'rejected',
        'cancelled',
    ];

    private const BLOCKING_STATUSES = [
        'pending',
        'approved',
    ];

    private const MAX_REASON_LENGTH = 1000;

    private LeaveRequestRepository $repository;

    public function __construct(?LeaveRequestRepository $repository = null) {
        $this->repository = $repository ?? new LeaveRequestRepository();
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
        return $this->repository->findByStatus($status);
    }

    public function getByDateRange(string $start, string $end): array {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        return $this->repository->findByDateRange($start, $end);
    }

    public function create(array $data) {
        $errors = $this->validateLeaveRequestData($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException("Validation failed: " . implode(', ', $errors));
        }

        $leaveRequest = LeaveRequest::fromArray($data);
        return $this->repository->create($leaveRequest);
    }

    public function update(int $id, array $data): bool {
        $this->assertExists($id);

        $errors = $this->validateLeaveRequestData($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException("Validation failed: " . implode(', ', $errors));
        }

        return $this->repository->update($id, $data) > 0;
    }

    public function delete(int $id): bool {
        $this->assertExists($id);
        return $this->repository->delete($id);
    }

    public function approve(int $id, string $approvedBy): bool {
        $this->assertExists($id);
        return $this->repository->approveLeaveRequest($id, $approvedBy);
    }

    public function reject(int $id, string $approvedBy, ?string $reason = null): bool {
        $request = $this->getById($id);
        if (!$request) {
            throw new Exception("Leave request not found");
        }

        if ($request['status'] !== 'pending') {
            throw new Exception("Cannot reject — request is already {$request['status']}.");
        }

        if ($reason !== null && trim($reason) === '') {
            $reason = null;
        }

        return $this->repository->reject($id, $approvedBy, $reason);
    }

    public function cancel(int $id): bool {
        $this->assertExists($id);
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

        $start = new DateTimeImmutable($request['start_date']);
        $end   = new DateTimeImmutable($request['end_date']);

        return (int) $start->diff($end)->days + 1;
    }

    public function countByEmployeeAndYear(int $employeeId, string $year): int {
        if (!preg_match('/^\d{4}$/', $year)) {
            throw new InvalidArgumentException("Year must be a 4-digit string, got: {$year}");
        }

        return $this->repository->countByEmployeeAndYear($employeeId, $year);
    }

    public function checkOverlap(int $employeeId, string $start, string $end, ?int $exceptId = null): bool {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        $existing = $this->repository->findByEmployee($employeeId);

        foreach ($existing as $row) {
            if ($exceptId !== null && (int) $row['id'] === $exceptId) {
                continue;
            }

            if (!in_array($row['status'], self::BLOCKING_STATUSES, true)) {
                continue;
            }

            if ($this->rangesOverlap($start, $end, $row['start_date'], $row['end_date'])) {
                throw new Exception(sprintf(
                    'Date range %s to %s overlaps with existing %s leave (%s to %s).',
                    $start,
                    $end,
                    $row['status'],
                    $row['start_date'],
                    $row['end_date']
                ));
            }
        }

        return false;
    }

    private function assertExists(int $id): void {
        if ($this->getById($id) === null) {
            throw new Exception("Leave request not found");
        }
    }

    private function assertValidDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }

    private function assertDateOrder(string $start, string $end): void {
        if ($start > $end) {
            throw new InvalidArgumentException("Start date must be before or equal to end date.");
        }
    }

    private function rangesOverlap(string $startA, string $endA, string $startB, string $endB): bool {
        return $startA <= $endB && $endA >= $startB;
    }

    private function validateLeaveRequestData(array $data, bool $isUpdate = false): array {
        $errors = [];

        $this->validateEmployeeId($data, $isUpdate, $errors);
        $this->validateLeaveType($data, $isUpdate, $errors);
        $this->validateStartDate($data, $isUpdate, $errors);
        $this->validateEndDate($data, $isUpdate, $errors);
        $this->validateDateRange($data, $errors);
        $this->validateReason($data, $errors);
        $this->validateStatus($data, $isUpdate, $errors);
        $this->validateApprovedBy($data, $errors);
        $this->validateApprovedAt($data, $errors);
        $this->validateId($data, $errors);

        return $errors;
    }

    private function validateEmployeeId(array $data, bool $isUpdate, array &$errors): void {
        if ($isUpdate && !array_key_exists('employee_id', $data)) {
            return;
        }

        $employeeId = $data['employee_id'] ?? null;

        if ($employeeId === null || $employeeId === '') {
            $errors['employee_id'] = 'Employee is required.';
        } elseif (!$this->isPositiveInt($employeeId)) {
            $errors['employee_id'] = 'Employee ID must be a positive integer.';
        }
    }

    private function validateLeaveType(array $data, bool $isUpdate, array &$errors): void {
        if ($isUpdate && !array_key_exists('leave_type', $data)) {
            return;
        }

        $type = trim((string) ($data['leave_type'] ?? ''));

        if ($type === '') {
            $errors['leave_type'] = 'Leave type is required.';
        } elseif (!in_array($type, self::ALLOWED_LEAVE_TYPES, true)) {
            $errors['leave_type'] = 'Leave type must be one of: ' . implode(', ', self::ALLOWED_LEAVE_TYPES) . '.';
        }
    }

    private function validateStartDate(array $data, bool $isUpdate, array &$errors): void {
        if ($isUpdate && !array_key_exists('start_date', $data)) {
            return;
        }

        $startDate = $data['start_date'] ?? null;

        if (empty($startDate)) {
            $errors['start_date'] = 'Start date is required.';
        } elseif (!$this->isValidYmd((string) $startDate)) {
            $errors['start_date'] = 'Start date must be a valid YYYY-MM-DD.';
        }
    }

    private function validateEndDate(array $data, bool $isUpdate, array &$errors): void {
        if ($isUpdate && !array_key_exists('end_date', $data)) {
            return;
        }

        $endDate = $data['end_date'] ?? null;

        if (empty($endDate)) {
            $errors['end_date'] = 'End date is required.';
        } elseif (!$this->isValidYmd((string) $endDate)) {
            $errors['end_date'] = 'End date must be a valid YYYY-MM-DD.';
        }
    }

    private function validateDateRange(array $data, array &$errors): void {
        $startDate = $data['start_date'] ?? null;
        $endDate   = $data['end_date'] ?? null;

        if (empty($startDate) || empty($endDate)) {
            return;
        }

        if (isset($errors['start_date']) || isset($errors['end_date'])) {
            return;
        }

        if ($startDate > $endDate) {
            $errors['end_date'] = 'End date must be on or after the start date.';
        }
    }

    private function validateReason(array $data, array &$errors): void {
        if (!array_key_exists('reason', $data) || $data['reason'] === null) {
            return;
        }

        $reason = trim((string) $data['reason']);

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            $errors['reason'] = sprintf('Reason must not exceed %d characters.', self::MAX_REASON_LENGTH);
        } elseif ($reason === '') {
            $errors['reason'] = 'Reason cannot be empty if provided.';
        }
    }

    private function validateStatus(array $data, bool $isUpdate, array &$errors): void {
        if ($isUpdate && !array_key_exists('status', $data)) {
            return;
        }

        $status = trim((string) ($data['status'] ?? 'pending'));

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Status must be one of: ' . implode(', ', self::ALLOWED_STATUSES) . '.';
        }
    }

    private function validateApprovedBy(array $data, array &$errors): void {
        $approvedBy = $data['approved_by'] ?? null;
        $status     = $data['status'] ?? null;

        if ($approvedBy !== null && $approvedBy !== '') {
            if (!$this->isPositiveInt($approvedBy)) {
                $errors['approved_by'] = 'Approver ID must be a positive integer.';
            }
        }

        if (in_array($status, ['approved', 'rejected'], true) && empty($approvedBy)) {
            $errors['approved_by'] = 'Approver is required when status is approved or rejected.';
        }
    }

    private function validateApprovedAt(array $data, array &$errors): void {
        if (!array_key_exists('approved_at', $data)) {
            return;
        }

        $approvedAt = $data['approved_at'];

        if ($approvedAt === null || $approvedAt === '') {
            return;
        }

        if (!$this->isValidDateTime((string) $approvedAt)) {
            $errors['approved_at'] = 'Approved at must be a valid YYYY-MM-DD HH:MM:SS.';
        }
    }

    private function validateId(array $data, array &$errors): void {
        if (!array_key_exists('id', $data)) {
            return;
        }

        $id = $data['id'];

        if ($id === null || $id === '') {
            return;
        }

        if (!$this->isPositiveInt($id)) {
            $errors['id'] = 'ID must be a positive integer.';
        }
    }

    private function isPositiveInt(mixed $value): bool {
        return filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) !== false;
    }

    private function isValidYmd(string $value): bool {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    private function isValidDateTime(string $value): bool {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        return $dt !== false && $dt->format('Y-m-d H:i:s') === $value;
    }
}