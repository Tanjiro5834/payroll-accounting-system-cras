<?php
namespace App\Service;

use App\Entity\Employee;
use App\Helper\FileHelper;
use App\Repository\EmployeeRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class EmployeeService {
    private const TIMEZONE = 'Asia/Manila';

    private const ALLOWED_ROLES = ['owner', 'admin', 'employee'];
    private const ALLOWED_PAY_FREQUENCIES = ['weekly', 'kinsenas', 'monthly'];

    private const MAX_NAME_LENGTH = 100;
    private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    private const UPLOAD_SUBDIR = '/uploads/employees/';

    private EmployeeRepository $repository;

    public function __construct(?EmployeeRepository $repository = null) {
        $this->repository = $repository ?? new EmployeeRepository();
    }

    public function getAll(): array {
        return $this->repository->findAll();
    }

    public function getAllActive(): array {
        return $this->repository->findAllActive();
    }

    public function getById(int $id): ?Employee {
        return $this->repository->findById($id);
    }

    public function create(array $data): int {
        $clean = $this->validateAndNormalize($data, false);
        return $this->repository->create(Employee::fromArray($clean));
    }

    public function update(int $id, array $data): bool {
        $this->requireEmployee($id);

        $clean = $this->validateAndNormalize($data, true);
        $clean['id'] = $id;

        return $this->repository->update(Employee::fromArray($clean));
    }

    public function deactivate(int $id): bool {
        $this->requireEmployee($id);
        return $this->repository->deactivate($id);
    }

    public function reactivate(int $id): bool {
        $this->requireEmployee($id);
        return $this->repository->reactivate($id);
    }

    public function delete(int $id): bool {
        $this->requireEmployee($id);
        return $this->repository->delete($id);
    }

    public function uploadProfilePhoto(int $employeeId, array $file): string {
        $employee = $this->requireEmployee($employeeId);

        $check = FileHelper::validateImage($file, self::MAX_UPLOAD_BYTES);
        if (!$check['valid']) {
            throw new InvalidArgumentException($check['error'] ?? 'Invalid image upload.');
        }

        $absoluteDir = $this->publicPath() . self::UPLOAD_SUBDIR;
        $absolute    = FileHelper::upload($file, $absoluteDir, 'emp_' . $employeeId);

        if ($absolute === null) {
            throw new DomainException('Failed to store the uploaded photo.');
        }

        $relative = self::UPLOAD_SUBDIR . basename($absolute);

        $this->replaceProfilePhoto($employee, $relative);

        return $relative;
    }

    public function deleteProfilePhoto(int $employeeId): bool {
        $employee = $this->requireEmployee($employeeId);
        $current  = $employee->getProfilePhotoUrl();

        if ($current === null || $current === '') {
            return false;
        }

        FileHelper::delete($this->publicPath() . $current);

        return $this->replaceProfilePhoto($employee, null);
    }

    public function search(string $query): array {
        $query = trim($query);
        if ($query === '') {
            return $this->repository->findAll();
        }

        return array_values(array_filter(
            $this->repository->findAll(),
            fn(Employee $e) => stripos($e->getFullName(), $query) !== false
                || stripos($e->getRole(), $query) !== false
        ));
    }

    public function countByRole(): array {
        $counts = [];
        foreach ($this->repository->findAll() as $employee) {
            $role = $employee->getRole();
            $counts[$role] = ($counts[$role] ?? 0) + 1;
        }
        return $counts;
    }

    public function generateInitials(string $fullName): string {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return '';
        }

        $initials = array_map(fn(string $p) => mb_strtoupper(mb_substr($p, 0, 1)), $parts);
        return implode('', array_slice($initials, 0, 2));
    }

    private function validateAndNormalize(array $data, bool $isUpdate): array {
        $errors = [];

        $fullName = $this->validateFullName($data, $isUpdate, $errors);
        $role     = $this->validateRole($data, $isUpdate, $errors);
        $payFreq  = $this->validatePayFrequency($data, $isUpdate, $errors);
        $hired    = $this->validateDateHired($data, $isUpdate, $errors);
        $rates    = $this->validateRates($data, $isUpdate, $errors);
        $ids      = $this->validateGovernmentIds($data, $errors);
        $photo    = $this->validateProfilePhotoUrl($data, $errors);

        if ($errors) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }

        $clean = array_filter([
            'full_name'         => $fullName,
            'role'              => $role,
            'pay_frequency'     => $payFreq,
            'date_hired'        => $hired,
            'hourly_rate'       => $rates['hourly_rate']  ?? null,
            'monthly_rate'      => $rates['monthly_rate'] ?? null,
            'sss_number'        => $ids['sss_number']        ?? null,
            'philhealth_number' => $ids['philhealth_number'] ?? null,
            'pagibig_number'    => $ids['pagibig_number']    ?? null,
            'tin_number'        => $ids['tin_number']        ?? null,
            'profile_photo_url' => $photo,
        ], fn($v) => $v !== null);

        if (array_key_exists('is_active', $data)) {
            $clean['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        return $clean;
    }

    private function validateFullName(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('full_name', $data)) {
            return null;
        }

        $name = trim((string) ($data['full_name'] ?? ''));
        if ($name === '') {
            $errors['full_name'] = 'Full name is required.';
            return null;
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['full_name'] = 'Full name must be ' . self::MAX_NAME_LENGTH . ' characters or fewer.';
            return null;
        }

        return $name;
    }

    private function validateRole(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('role', $data)) {
            return null;
        }

        $role = trim((string) ($data['role'] ?? ''));
        if ($role === '') {
            $errors['role'] = 'Role is required.';
            return null;
        }
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $errors['role'] = 'Role must be one of: ' . implode(', ', self::ALLOWED_ROLES) . '.';
            return null;
        }

        return $role;
    }

    private function validatePayFrequency(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('pay_frequency', $data)) {
            return null;
        }

        $freq = trim((string) ($data['pay_frequency'] ?? ''));
        if ($freq === '') {
            $errors['pay_frequency'] = 'Pay frequency is required.';
            return null;
        }
        if (!in_array($freq, self::ALLOWED_PAY_FREQUENCIES, true)) {
            $errors['pay_frequency'] = 'Pay frequency must be one of: ' . implode(', ', self::ALLOWED_PAY_FREQUENCIES) . '.';
            return null;
        }

        return $freq;
    }

    private function validateDateHired(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('date_hired', $data)) {
            return null;
        }

        $date = trim((string) ($data['date_hired'] ?? ''));
        if ($date === '') {
            return null;
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            $errors['date_hired'] = 'Date hired must be a valid YYYY-MM-DD.';
            return null;
        }
        if ($dt > new DateTimeImmutable('today', $tz)) {
            $errors['date_hired'] = 'Date hired cannot be in the future.';
            return null;
        }

        return $date;
    }

    private function validateRates(array $data, bool $isUpdate, array &$errors): array {
        $out = [];

        foreach (['hourly_rate', 'monthly_rate'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $raw = $data[$field];
            if ($raw === null || $raw === '') {
                $out[$field] = null;
                continue;
            }

            if (!is_numeric($raw) || (float) $raw < 0) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a non-negative number.';
                continue;
            }

            $out[$field] = bcadd((string) $raw, '0', 2);
        }

        if (!$isUpdate
            && empty($out['hourly_rate'])
            && empty($out['monthly_rate'])) {
            $errors['hourly_rate'] = 'Provide an hourly or a monthly rate.';
        }

        return $out;
    }

    private function validateGovernmentIds(array $data, array &$errors): array {
        $rules = [
            'sss_number'        => ['digits' => 10,     'label' => 'SSS number'],
            'philhealth_number' => ['digits' => 12,     'label' => 'PhilHealth number'],
            'pagibig_number'    => ['digits' => 12,     'label' => 'Pag-IBIG number'],
            'tin_number'        => ['digits' => [9, 12], 'label' => 'TIN'],
        ];

        $out = [];
        foreach ($rules as $field => $rule) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $raw = trim((string) ($data[$field] ?? ''));
            if ($raw === '') {
                $out[$field] = null;
                continue;
            }

            $digits  = preg_replace('/\D/', '', $raw);
            $allowed = (array) $rule['digits'];

            if (!in_array(strlen($digits), $allowed, true)) {
                $expected = count($allowed) === 1 ? $allowed[0] : implode(' or ', $allowed);
                $errors[$field] = "{$rule['label']} must be {$expected} digits.";
                continue;
            }

            $out[$field] = $raw;
        }

        return $out;
    }

    private function validateProfilePhotoUrl(array $data, array &$errors): ?string {
        if (!array_key_exists('profile_photo_url', $data)) {
            return null;
        }

        $url = trim((string) ($data['profile_photo_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false && !str_starts_with($url, self::UPLOAD_SUBDIR)) {
            $errors['profile_photo_url'] = 'Profile photo URL is invalid.';
            return null;
        }

        return $url;
    }

    private function replaceProfilePhoto(Employee $employee, ?string $url): bool {
        $data = $employee->toArray();
        $data['profile_photo_url'] = $url;

        return $this->repository->update(Employee::fromArray($data));
    }

    private function requireEmployee(int $id): Employee {
        $employee = $this->repository->findById($id);
        if (!$employee) {
            throw new DomainException("Employee not found: {$id}");
        }
        return $employee;
    }

    private function publicPath(): string {
        return dirname(__DIR__, 2) . '/public';
    }
}