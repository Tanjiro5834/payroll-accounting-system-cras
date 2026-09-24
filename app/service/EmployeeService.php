<?php
namespace App\Service;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use Exception;

class EmployeeService {
    private EmployeeRepository $repository;

    public function __construct() {
        $this->repository = new EmployeeRepository();
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
        $errors = $this->validateEmployeeData($data);
        if (!empty($errors)) {
            throw new Exception("Validation failed: " . implode(', ', $errors));
        }

        $employee = Employee::fromArray($data);
        return $this->repository->create($employee);
    }

    public function update(int $id, array $data): bool {
        $employee = $this->getById($id);
        if (!$employee) {
            throw new Exception("Employee not found");
        }

        $errors = $this->validateEmployeeData($data);
        if (!empty($errors)) {
            throw new Exception("Validation failed: " . implode(', ', $errors));
        }

        $data['id'] = $id;
        $updated = Employee::fromArray($data);
        return $this->repository->update($updated) > 0;
    }

    public function deactivate(int $id): bool {
        $employee = $this->getById($id);
        if (!$employee) {
            throw new Exception("Employee not found");
        }
        return $this->repository->deactivate($id);
    }

    public function reactivate(int $id): bool {
        $employee = $this->getById($id);
        if (!$employee) {
            throw new Exception("Employee not found");
        }
        return $this->repository->reactivate($id);
    }

    public function uploadProfilePhoto(int $employeeId, array $file): ?string {
        $employee = $this->getById($employeeId);
        if (!$employee) {
            throw new Exception("Employee not found");
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Invalid file type");
        }

        $uploadDir = __DIR__ . '/../../public/uploads/employees/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('emp_') . '_' . basename($file['name']);
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return null;
        }

        $url = '/uploads/employees/' . $filename;
        $data = $employee->toArray();
        $data['profile_photo_url'] = $url;
        $this->repository->update(Employee::fromArray($data));

        return $url;
    }

    public function deleteProfilePhoto(int $employeeId): bool {
        $employee = $this->getById($employeeId);
        if (!$employee || !$employee->getProfilePhotoUrl()) {
            return false;
        }

        $path = __DIR__ . '/../../public' . $employee->getProfilePhotoUrl();
        if (file_exists($path)) {
            unlink($path);
        }

        $data = $employee->toArray();
        $data['profile_photo_url'] = null;
        return $this->repository->update(Employee::fromArray($data)) > 0;
    }

    public function validateEmployeeData(array $data): array {
        $errors = [];

        if (empty($data['full_name'])) {
            $errors[] = "Full name is required";
        }
        if (empty($data['role'])) {
            $errors[] = "Role is required";
        }
        if (isset($data['hourly_rate']) && $data['hourly_rate'] < 0) {
            $errors[] = "Hourly rate cannot be negative";
        }
        if (isset($data['monthly_rate']) && $data['monthly_rate'] < 0) {
            $errors[] = "Monthly rate cannot be negative";
        }
        if (empty($data['pay_frequency'])) {
            $errors[] = "Pay frequency is required";
        }

        return $errors;
    }

    public function generateInitials(string $fullName): string {
        $parts = preg_split('/\s+/', trim($fullName));
        $initials = array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), $parts);
        return implode('', array_slice($initials, 0, 2));
    }

    public function search(string $query): array {
        return array_filter(
            $this->repository->findAll(),
            fn(Employee $e) => stripos($e->getFullName(), $query) !== false
                || stripos($e->getRole(), $query) !== false
        );
    }

    public function countByRole(): array {
        $counts = [];
        foreach ($this->repository->findAll() as $employee) {
            $role = $employee->getRole();
            $counts[$role] = ($counts[$role] ?? 0) + 1;
        }
        return $counts;
    }
}