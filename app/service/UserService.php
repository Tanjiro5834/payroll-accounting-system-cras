<?php
namespace App\Service;

use App\Entity\User;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class UserService {
    private const TIMEZONE = 'Asia/Manila';

    private const ALLOWED_ROLES = ['owner', 'admin', 'employee'];
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 72; // bcrypt hard limit
    private const MAX_USERNAME_LENGTH = 50;

    private UserRepository $repository;
    private EmployeeRepository $employees;

    public function __construct(
        ?UserRepository $repository = null,
        ?EmployeeRepository $employees = null
    ) {
        $this->repository = $repository ?? new UserRepository();
        $this->employees  = $employees  ?? new EmployeeRepository();
    }

    public function getAll(): array {
        return $this->repository->findAll();
    }

    public function getAllActive(): array {
        return $this->repository->findAllActive();
    }

    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }

    public function getByUsername(string $username): ?array {
        $username = trim($username);
        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        return $this->repository->findByUsername($username);
    }

    public function getByEmployeeId(int $employeeId): ?array {
        return $this->repository->findByEmployeeId($employeeId);
    }

    public function search(string $query, int $limit = 100): array {
        return $this->repository->search($query, $limit);
    }

    public function countByRole(string $role): int {
        $this->assertRole($role);
        return $this->repository->countByRole($role);
    }

    public function countAll(): int {
        return $this->repository->countAll();
    }

    public function countAllActive(): int {
        return $this->repository->countActive();
    }

    public function create(array $data): User {
        $username = $this->validateUsername($data['username'] ?? null);
        $password = $this->validatePassword($data['password'] ?? null);
        $role     = $this->validateRole($data['role'] ?? null);
        $empId    = $this->validateEmployeeId($data['employee_id'] ?? null);
        $isActive = $this->validateIsActive($data['is_active'] ?? true);

        if ($this->repository->usernameExists($username)) {
            throw new DomainException("Username '{$username}' is already taken.");
        }
        if ($empId !== null) {
            $this->requireEmployeeExists($empId);
            if ($this->repository->employeeIdExists($empId)) {
                throw new DomainException("Employee #{$empId} is already linked to a user.");
            }
        }

        $user = User::fromArray([
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'employee_id'   => $empId,
            'last_login'    => null,
            'is_active'     => $isActive ? 1 : 0,
        ]);

        $id = $this->repository->create($user);
        $user->setId($id);

        return $user;
    }

    public function update(int $id, array $data): bool {
        $existing = $this->requireUser($id);

        $username = array_key_exists('username', $data)
            ? $this->validateUsername($data['username'])
            : (string) $existing['username'];

        $role = array_key_exists('role', $data)
            ? $this->validateRole($data['role'])
            : (string) $existing['role'];

        $empId = array_key_exists('employee_id', $data)
            ? $this->validateEmployeeId($data['employee_id'])
            : ($existing['employee_id'] !== null ? (int) $existing['employee_id'] : null);

        $isActive = array_key_exists('is_active', $data)
            ? $this->validateIsActive($data['is_active'])
            : (bool) $existing['is_active'];

        $passwordHash = (string) $existing['password_hash'];
        if (!empty($data['password'])) {
            $passwordHash = password_hash($this->validatePassword($data['password']), PASSWORD_DEFAULT);
        }

        if ($username !== $existing['username'] && $this->repository->usernameExists($username, $id)) {
            throw new DomainException("Username '{$username}' is already taken.");
        }
        if ($empId !== null && $empId !== (int) $existing['employee_id']) {
            $this->requireEmployeeExists($empId);
            if ($this->repository->employeeIdExists($empId, $id)) {
                throw new DomainException("Employee #{$empId} is already linked to a user.");
            }
        }

        $user = User::fromArray([
            'id'            => $id,
            'username'      => $username,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'employee_id'   => $empId,
            'last_login'    => $existing['last_login'] ?? null,
            'is_active'     => $isActive ? 1 : 0,
        ]);

        return $this->repository->update($user);
    }

    public function updateRole(int $id, string $role): bool {
        $this->requireUser($id);
        $this->assertRole($role);
        return $this->repository->updateRole($id, $role);
    }

    public function updateStatus(int $id, bool $isActive): bool {
        $this->requireUser($id);
        return $this->repository->updateStatus($id, $isActive);
    }

    public function changePassword(int $id, string $currentPassword, string $newPassword): bool {
        $existing = $this->requireUser($id);

        if (!password_verify($currentPassword, (string) $existing['password_hash'])) {
            throw new DomainException('Current password is incorrect.');
        }

        $newPassword = $this->validatePassword($newPassword);

        return $this->repository->updatePassword($id, password_hash($newPassword, PASSWORD_DEFAULT));
    }

    public function resetPassword(int $id, string $newPassword): bool {
        $this->requireUser($id);
        $newPassword = $this->validatePassword($newPassword);
        return $this->repository->updatePassword($id, password_hash($newPassword, PASSWORD_DEFAULT));
    }

    public function updateLastLogin(int $id): bool {
        $this->requireUser($id);
        return $this->repository->updateLastLogin($id);
    }

    public function delete(int $id): bool {
        $this->requireUser($id);
        return $this->repository->delete($id);
    }

    public function deactivate(int $id): bool {
        $this->requireUser($id);
        return $this->repository->deactivate($id);
    }

    public function reactivate(int $id): bool {
        $this->requireUser($id);
        return $this->repository->reactivate($id);
    }

    public function linkToEmployee(int $userId, int $employeeId): bool {
        $this->requireUser($userId);
        $this->requireEmployeeExists($employeeId);

        if ($this->repository->employeeIdExists($employeeId, $userId)) {
            throw new DomainException("Employee #{$employeeId} is already linked to a user.");
        }

        return $this->repository->linkToEmployee($userId, $employeeId);
    }

    public function unlinkFromEmployee(int $userId): bool {
        $this->requireUser($userId);
        return $this->repository->unlinkFromEmployee($userId);
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool {
        return $this->repository->usernameExists($username, $exceptId);
    }

    public function employeeIdExists(int $employeeId, ?int $exceptId = null): bool {
        return $this->repository->employeeIdExists($employeeId, $exceptId);
    }

    private function requireUser(int $id): array {
        $user = $this->repository->findById($id);
        if (!$user) {
            throw new DomainException("User not found: {$id}");
        }
        return $user;
    }

    private function requireEmployeeExists(int $employeeId): void {
        if (!$this->employees->exists($employeeId)) {
            throw new DomainException("Employee not found: {$employeeId}");
        }
    }

    private function validateUsername(mixed $value): string {
        $username = trim((string) $value);
        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        if (mb_strlen($username) > self::MAX_USERNAME_LENGTH) {
            throw new InvalidArgumentException('Username must be ' . self::MAX_USERNAME_LENGTH . ' characters or fewer.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            throw new InvalidArgumentException('Username may contain letters, digits, dot, underscore, and dash only.');
        }
        return $username;
    }

    private function validatePassword(mixed $value): string {
        $password = (string) $value;
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
        }
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password must be ' . self::MAX_PASSWORD_LENGTH . ' characters or fewer.');
        }
        return $password;
    }

    private function validateRole(mixed $value): string {
        $role = trim((string) $value);
        $this->assertRole($role);
        return $role;
    }

    private function validateEmployeeId(mixed $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('employee_id must be a positive integer.');
        }
        return (int) $id;
    }

    private function validateIsActive(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new InvalidArgumentException('is_active must be a boolean.');
        }
        return $parsed;
    }

    private function assertRole(string $role): void {
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException('Role must be one of: ' . implode(', ', self::ALLOWED_ROLES) . '.');
        }
    }
}