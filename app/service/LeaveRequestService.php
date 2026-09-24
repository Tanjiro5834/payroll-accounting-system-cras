<?php
namespace App\Service;

use App\Entity\LeaveRequest;
use App\Repository\LeaveRequestRepository;

class LeaveRequestService{
    private LeaveRequestRepository $repository;

    public function __construct() {
        $this->repository = new LeaveRequestRepository();
    }

    public function getAll() {
        return $this->repository->findAll();
    }

    public function getById(int $id) {
        return $this->repository->findById($id);
    }

    public function getByEmployee(int $employeeId) {
        return $this->repository->findByEmployee($employeeId);
    }

    public function getByStatus(string $status) {   
        return $this->repository->findByStatus($status);
    }

    public function getByDateRange(string $start, string $end) {
        $startDate = assertValidDate($start, 'start');
        $endDate = assertValidDate($end, 'end');

        if($startDate > $endDate){
            throw new Exception('Start date must be before or equal to end date.');
        }
        return $this->repository->findByDateRange($start, $end);
    }

    public function create(array $data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            throw new Exception("Validation failed: " . implode(', ', $errors));
        }

        $leaveRequest = LeaveRequest::fromArray($data);
        return $this->repository->create($leaveRequest);
    }

    public function update(int $id, array $data) {
        if(!$this->checkLeaveRequestPresence($id)){
            throw new Exception("Holiday not found");
        }

        $errors = $this->validateLeaveRequestData($data);
        if (!empty($errors)) {
            throw new Exception("Validation failed: " . implode(', ', $errors));
        }

        return $this->repository->update($id, $data) > 0;
    }

    public function delete(int $id) {
        if(!$this->checkLeaveRequestPresence($id)){
            throw new Exception("Leave request not found");
        }

        return $this->repository->delete($id);
    }

    public function approve(int $id, string $approvedBy) {
        if(!$this->checkLeaveRequestPresence($id)){
            throw new Exception("Leave request not found");
        }

        return $this->repository->approveLeaveRequest($id, $approvedBy);
    }

    public function reject(int $id, string $approvedBy, string $reason = null): bool{
        $request = $this->getById($id);
        if(!$request) throw new Exception("Leave request not found");

        if(request['statis'] !== 'pending'){
            throw new Exception("Cannot reject — request is already {$request['status']}.");
        }

        if ($reason !== null && trim($reason) === '') $reason = null;

        $this->repository->reject($id, $approvedBy, $reason);
    }

    public function cancel(int $id) {
        if(!$this->checkLeaveRequestPresence($id)){
            throw new Exception("Leave request not found");
        }

        return $this->repository->cancel($id);
    }

    public function hasApprovedLeave(int $employeeId, string $date) {
        $this->assertValidDate($date, 'date');
        return $this->repository->hasApprovedLeave($employeeId, $date);
    }

    public function getLeaveDays(int $id) {
        $request = $this->repository->findById($id);
        if (!$request) return 0;

        $start = new DateTimeImmutable($request['start_date']);
        $end = new DateTimeImmutable($request['end_date']);

        return (int) $start->diff($end)->days + 1;
    }

    public function countByEmployeeAndYear(int $employeeId, string $year) {
        if (!preg_match('/^\d{4}$/', $year)) {
            throw new Exception("Year must be a 4-digit string, got: {$year}");
        }

        return $this->repository->countByEmployeeAndYear($employeeId, $year);
    }

    public function checkOverlap(int $employeeId, string $start, string $end, int $exceptId = null) {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');

        if ($start > $end) {
            throw new Exception('Start date must be on or before end date.');
        }

        // Pull the employee's active leave requests in a window that could
        // possibly overlap the requested range, then compare in PHP.
        // The query uses a superset range to keep the SQL simple.
        $existing = $this->repository->findByEmployee($employeeId);

        foreach ($existing as $row) {
            // Skip the row being updated.
            if ($exceptId !== null && (int) $row['id'] === $exceptId) continue;

            // Only 'pending' and 'approved' leaves block new requests.
            if (!in_array($row['status'], ['pending', 'approved'], true)) continue;

            // Overlap test: two ranges overlap iff
            //     start <= existing_end  AND  end >= existing_start
            if ($start <= $row['end_date'] && $end >= $row['start_date']) {
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

    private function assertValidDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new Exception("Invalid date for {$field}: {$date}");
        }
    }

    private function checkLeaveRequestPresence(int $id): bool{
        return $this->getById($id) !== null;
    }

    private function validateLeaveRequestData(array $data, bool $isUpdate = false): array {
        $errors = [];

        // ---------- employee_id ----------
        if (!$isUpdate || array_key_exists('employee_id', $data)) {
            $employeeId = $data['employee_id'] ?? null;
            if ($employeeId === null || $employeeId === '') {
                $errors['employee_id'] = 'Employee is required.';
            } elseif (!filter_var($employeeId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                $errors['employee_id'] = 'Employee ID must be a positive integer.';
            }
        }

        // ---------- leave_type ----------
        if (!$isUpdate || array_key_exists('leave_type', $data)) {
            $type = trim((string) ($data['leave_type'] ?? ''));
            $allowed = [
                'vacation',
                'sick',
                'maternity',
                'paternity',
                'bereavement',
                'unpaid',
                'emergency',
            ];
            if ($type === '') {
                $errors['leave_type'] = 'Leave type is required.';
            } elseif (!in_array($type, $allowed, true)) {
                $errors['leave_type'] = 'Leave type must be one of: ' . implode(', ', $allowed) . '.';
            }
        }

        // ---------- start_date ----------
        $startDate = $data['start_date'] ?? null;
        if (!$isUpdate || array_key_exists('start_date', $data)) {
            if (empty($startDate)) {
                $errors['start_date'] = 'Start date is required.';
            } else {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $startDate);
                if (!$dt || $dt->format('Y-m-d') !== (string) $startDate) {
                    $errors['start_date'] = 'Start date must be a valid YYYY-MM-DD.';
                }
            }
        }

        // ---------- end_date ----------
        $endDate = $data['end_date'] ?? null;
        if (!$isUpdate || array_key_exists('end_date', $data)) {
            if (empty($endDate)) {
                $errors['end_date'] = 'End date is required.';
            } else {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $endDate);
                if (!$dt || $dt->format('Y-m-d') !== (string) $endDate) {
                    $errors['end_date'] = 'End date must be a valid YYYY-MM-DD.';
                }
            }
        }

        // ---------- date range cross-check ----------
        if (!empty($startDate) && !empty($endDate)
            && empty($errors['start_date']) && empty($errors['end_date'])) {
            if ($startDate > $endDate) {
                $errors['end_date'] = 'End date must be on or after the start date.';
            }
        }

        // ---------- reason ----------
        if (array_key_exists('reason', $data) && $data['reason'] !== null) {
            $reason = trim((string) $data['reason']);
            if (mb_strlen($reason) > 1000) {
                $errors['reason'] = 'Reason must not exceed 1000 characters.';
            } elseif ($reason === '') {
                $errors['reason'] = 'Reason cannot be empty if provided.';
            }
        }

        // ---------- status ----------
        if (!$isUpdate || array_key_exists('status', $data)) {
            $status = trim((string) ($data['status'] ?? 'pending'));
            $allowed = ['pending', 'approved', 'rejected', 'cancelled'];
            if (!in_array($status, $allowed, true)) {
                $errors['status'] = 'Status must be one of: ' . implode(', ', $allowed) . '.';
            }
        }

        // ---------- approved_by ----------
        // Only meaningful when status is 'approved' or 'rejected'.
        if (array_key_exists('approved_by', $data) && $data['approved_by'] !== null && $data['approved_by'] !== '') {
            if (!filter_var($data['approved_by'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                $errors['approved_by'] = 'Approver ID must be a positive integer.';
            }
        }

        // Cross-check: approval fields go hand in hand with status.
        $status = $data['status'] ?? null;
        if (in_array($status, ['approved', 'rejected'], true)) {
            if (empty($data['approved_by'])) {
                $errors['approved_by'] = 'Approver is required when status is approved or rejected.';
            }
        }

        // ---------- approved_at ----------
        if (array_key_exists('approved_at', $data) && $data['approved_at'] !== null && $data['approved_at'] !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $data['approved_at']);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== (string) $data['approved_at']) {
                $errors['approved_at'] = 'Approved at must be a valid YYYY-MM-DD HH:MM:SS.';
            }
        }

        // ---------- id (only meaningful on update) ----------
        if (array_key_exists('id', $data) && $data['id'] !== null && $data['id'] !== '') {
            if (!filter_var($data['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                $errors['id'] = 'ID must be a positive integer.';
            }
        }

        return $errors;
    }
}