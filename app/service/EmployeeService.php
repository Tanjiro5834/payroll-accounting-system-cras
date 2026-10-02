<?php
namespace App\Service;

use App\Entity\Employee;
use App\Helper\FileHelper;
use App\Middleware\AuthMiddleware;
use App\Repository\EmployeeRepository;
use App\Repository\RateHistoryRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class EmployeeService {
    private const TIMEZONE = 'Asia/Manila';

    private const ALLOWED_ROLES = ['owner', 'admin', 'employee'];
    private const ALLOWED_PAY_FREQUENCIES = ['weekly', 'kinsenas', 'monthly'];

    private const MAX_NAME_LENGTH = 100;
    private const MAX_ROLE_LENGTH = 50; 
    private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    private const UPLOAD_SUBDIR = 'uploads/employees/';

    // Set once at hiring. After that only the owner can change a value that is already filled in;
    // an admin may still fill a field that was left blank (one-time entry).
    public const IDENTITY_FIELDS = [
        'full_name'         => 'Full name',
        'role'              => 'Job title',
        'date_hired'        => 'Date hired',
        'sss_number'        => 'SSS number',
        'philhealth_number' => 'PhilHealth number',
        'pagibig_number'    => 'Pag-IBIG number',
        'tin_number'        => 'TIN',
    ];

    private const RATE_FIELDS = ['pay_frequency', 'hourly_rate', 'monthly_rate'];

    private EmployeeRepository $repository;
    private AuditService $audit;
    private RateHistoryRepository $rates;

    public function __construct(
        ?EmployeeRepository $repository = null,
        ?AuditService $audit = null,
        ?RateHistoryRepository $rates = null
    ) {
        $this->repository = $repository ?? new EmployeeRepository();
        $this->audit      = $audit      ?? new AuditService();
        $this->rates      = $rates      ?? new RateHistoryRepository();
    }

    // Which identity fields this actor may NOT change for this employee (filled in + not owner).
    public function lockedFields(Employee $employee, bool $canEditIdentity): array {
        if ($canEditIdentity) {
            return [];
        }
        $data = $employee->toArray();
        return array_values(array_filter(
            array_keys(self::IDENTITY_FIELDS),
            fn(string $f) => ($data[$f] ?? null) !== null && (string) $data[$f] !== ''
        ));
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
        $id = $this->repository->create(Employee::fromArray($clean));
        (new StatutoryContributionService())->enroll($id, $clean['date_hired'] ?? date('Y-m-d'));

        $this->rates->record(
            $id,
            $clean['pay_frequency'],
            $clean['hourly_rate'] ?? null,
            $clean['monthly_rate'] ?? null,
            $clean['date_hired'] ?? $this->today(),
            $this->actorId()
        );

        $this->audit->record('EMPLOYEE_CREATE', $id, array_intersect_key($clean, array_flip([
            'full_name', 'role', 'pay_frequency', 'hourly_rate', 'monthly_rate', 'date_hired',
        ])));
        return $id;
    }

    public function update(int $id, array $data, bool $canEditIdentity = false): bool {
        $employee = $this->requireEmployee($id);
        $current  = $employee->toArray();
        $clean    = $this->validateAndNormalize($data, true);
        $merged   = array_replace($current, $clean, ['id' => $id]);

        $this->assertIdentityUnchanged($employee, $merged, $canEditIdentity);

        if (empty($merged['hourly_rate']) && empty($merged['monthly_rate'])) {
            throw new InvalidArgumentException('Validation failed: Provide an hourly or a monthly rate.');
        }

        $this->repository->update(Employee::fromArray($merged));

        if (AuditService::diff(
            array_intersect_key($current, array_flip(self::RATE_FIELDS)),
            array_intersect_key($merged, array_flip(self::RATE_FIELDS))
        )) {
            $this->rates->record(
                $id,
                (string) $merged['pay_frequency'],
                $merged['hourly_rate'] ?: null,
                $merged['monthly_rate'] ?: null,
                $this->today(),
                $this->actorId()
            );
        }

        $changes = AuditService::diff($current, $merged, ['id', 'profile_photo_url']);
        if ($changes) {
            $this->audit->record('EMPLOYEE_UPDATE', $id, ['changes' => $changes]);
        }
        return true;
    }

    public function deactivate(int $id): bool {
        $employee = $this->requireEmployee($id);
        $ok = $this->repository->deactivate($id);
        if ($ok) {
            $this->audit->record('EMPLOYEE_DEACTIVATE', $id, ['full_name' => $employee->getFullName()]);
        }
        return $ok;
    }

    public function reactivate(int $id): bool {
        $employee = $this->requireEmployee($id);
        $ok = $this->repository->reactivate($id);
        if ($ok) {
            $this->audit->record('EMPLOYEE_REACTIVATE', $id, ['full_name' => $employee->getFullName()]);
        }
        return $ok;
    }

    public function delete(int $id): bool {
        $employee = $this->requireEmployee($id);
        $ok = $this->repository->delete($id);
        if ($ok) {
            // employee_id NULL: the row is gone, so a FK on audit_trail.employee_id would reject it
            $this->audit->record('EMPLOYEE_DELETE', null, ['employee_id' => $id, 'full_name' => $employee->getFullName()]);
        }
        return $ok;
    }

    // $canReplace = false: admins may add a photo once, but not swap or remove an existing one.
    public function uploadProfilePhoto(int $employeeId, array $file, bool $canReplace = true): string {
        $employee = $this->requireEmployee($employeeId);
        $this->assertPhotoEditable($employee, $canReplace);

        $check = FileHelper::validateImage($file, self::MAX_UPLOAD_BYTES);
        if (!$check['valid']) {
            throw new InvalidArgumentException($check['error'] ?? 'Invalid image upload.');
        }

        $absoluteDir = $this->projectRoot() . '/' . self::UPLOAD_SUBDIR;
        $absolute = FileHelper::upload($file, $absoluteDir, 'emp_' . $employeeId);

        if ($absolute === null) {
            throw new DomainException('Failed to store the uploaded photo.');
        }

        $relative = self::UPLOAD_SUBDIR . basename($absolute);
        $old = $employee->getProfilePhotoUrl();
        $this->replaceProfilePhoto($employee, $relative);
        if ($old) {
            FileHelper::delete($this->projectRoot() . '/' . $old);
        }

        $this->audit->record('EMPLOYEE_PHOTO_UPDATE', $employeeId, ['photo' => $relative]);
        return $relative;
    }

    public function deleteProfilePhoto(int $employeeId, bool $canReplace = true): bool {
        $employee = $this->requireEmployee($employeeId);
        $this->assertPhotoEditable($employee, $canReplace);
        $current  = $employee->getProfilePhotoUrl();

        if ($current === null || $current === '') {
            return false;
        }

        FileHelper::delete($this->projectRoot() . '/' . $current);

        $ok = $this->replaceProfilePhoto($employee, null);
        if ($ok) {
            $this->audit->record('EMPLOYEE_PHOTO_DELETE', $employeeId, ['photo' => $current]);
        }
        return $ok;
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

        $clean = [
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
        ];

        // Keep only fields the caller sent: on update, an absent field stays as stored,
        // while a field sent empty is cleared (NULL).
        $sent = array_flip(array_keys($data));
        $clean = $isUpdate ? array_intersect_key($clean, $sent) : array_filter($clean, fn($v) => $v !== null);

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

        if (mb_strlen($role) > self::MAX_ROLE_LENGTH) {
            $errors['role'] = 'Role must be ' . self::MAX_ROLE_LENGTH . ' characters or fewer.';
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

    private function assertIdentityUnchanged(Employee $employee, array $merged, bool $canEditIdentity): void {
        $locked = $this->lockedFields($employee, $canEditIdentity);
        if (!$locked) {
            return;
        }

        $current = $employee->toArray();
        $changed = AuditService::diff(
            array_intersect_key($current, array_flip($locked)),
            array_intersect_key($merged, array_flip($locked))
        );
        if ($changed) {
            $labels = array_map(fn(string $f) => self::IDENTITY_FIELDS[$f], array_keys($changed));
            throw new DomainException('Locked after hiring — only the owner can change: ' . implode(', ', $labels) . '.');
        }
    }

    private function assertPhotoEditable(Employee $employee, bool $canReplace): void {
        $photo = $employee->getProfilePhotoUrl();
        if (!$canReplace && $photo !== null && $photo !== '') {
            throw new DomainException('Locked after hiring — only the owner or the employee can change the profile photo.');
        }
    }

    private function actorId(): ?int {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $user = AuthMiddleware::user();
        return isset($user['id']) ? (int) $user['id'] : null;
    }

    private function today(): string {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
    }

    private function requireEmployee(int $id): Employee {
        $employee = $this->repository->findById($id);
        if (!$employee) {
            throw new DomainException("Employee not found: {$id}");
        }
        return $employee;
    }

    private function projectRoot(): string {
        return dirname(__DIR__, 2);
    }
}