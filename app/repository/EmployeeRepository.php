<?php
namespace App\Repository;

use App\Entity\Employee;
use PDO;

class EmployeeRepository extends BaseRepository {
    private const LIST_COLUMNS = 'id, full_name, role, profile_photo_url, hourly_rate, monthly_rate,
                                  pay_frequency, date_hired, is_active, created_at, updated_at';

    private const FULL_COLUMNS = 'id, full_name, role, profile_photo_url, sss_number,
                                  philhealth_number, pagibig_number, tin_number,
                                  hourly_rate, monthly_rate, pay_frequency, date_hired,
                                  is_active, created_at, updated_at';

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::LIST_COLUMNS . " FROM employees ORDER BY full_name"
        );
        $stmt->execute();

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllActive(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::LIST_COLUMNS . " FROM employees WHERE is_active = 1 ORDER BY full_name"
        );
        $stmt->execute();

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Employee {
        $stmt = $this->db->prepare(
            "SELECT " . self::FULL_COLUMNS . " FROM employees WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Employee::fromArray($row) : null;
    }

    public function findByRole(string $role): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::LIST_COLUMNS . " FROM employees WHERE role = ? ORDER BY full_name"
        );
        $stmt->execute([$role]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findActiveByRole(string $role): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::LIST_COLUMNS . " FROM employees
             WHERE role = ? AND is_active = 1
             ORDER BY full_name"
        );
        $stmt->execute([$role]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(Employee $employee): int {
        $stmt = $this->db->prepare(
            "INSERT INTO employees
                (full_name, role, profile_photo_url, sss_number, philhealth_number,
                 pagibig_number, tin_number, hourly_rate, monthly_rate, pay_frequency,
                 date_hired, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $employee->getFullName(),
            $employee->getRole(),
            $employee->getProfilePhotoUrl(),
            $employee->getSssNumber(),
            $employee->getPhilhealthNumber(),
            $employee->getPagibigNumber(),
            $employee->getTinNumber(),
            $employee->getHourlyRate(),
            $employee->getMonthlyRate(),
            $employee->getPayFrequency(),
            $employee->getDateHired(),
            $employee->getIsActive() ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(Employee $employee): bool {
        $stmt = $this->db->prepare(
            "UPDATE employees SET
                full_name         = :full_name,
                role              = :role,
                profile_photo_url = :profile_photo_url,
                sss_number        = :sss_number,
                philhealth_number = :philhealth_number,
                pagibig_number    = :pagibig_number,
                tin_number        = :tin_number,
                hourly_rate       = :hourly_rate,
                monthly_rate      = :monthly_rate,
                pay_frequency     = :pay_frequency,
                date_hired        = :date_hired,
                is_active         = :is_active
             WHERE id = :id"
        );
        $stmt->execute([
            ':full_name'         => $employee->getFullName(),
            ':role'              => $employee->getRole(),
            ':profile_photo_url' => $employee->getProfilePhotoUrl(),
            ':sss_number'        => $employee->getSssNumber(),
            ':philhealth_number' => $employee->getPhilhealthNumber(),
            ':pagibig_number'    => $employee->getPagibigNumber(),
            ':tin_number'        => $employee->getTinNumber(),
            ':hourly_rate'       => $employee->getHourlyRate(),
            ':monthly_rate'      => $employee->getMonthlyRate(),
            ':pay_frequency'     => $employee->getPayFrequency(),
            ':date_hired'        => $employee->getDateHired(),
            ':is_active'         => $employee->getIsActive() ? 1 : 0,
            ':id'                => $employee->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool {
        $stmt = $this->db->prepare("UPDATE employees SET is_active = 0 WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function reactivate(int $id): bool {
        $stmt = $this->db->prepare("UPDATE employees SET is_active = 1 WHERE id = ? AND is_active = 0");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function existsActive(int $id): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM employees WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$id]);

        return $stmt->fetchColumn() !== false;
    }

    public function exists(int $id): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM employees WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        return $stmt->fetchColumn() !== false;
    }

    public function countActive(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM employees WHERE is_active = 1");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findActiveIds(): array {
        $stmt = $this->db->prepare("SELECT id FROM employees WHERE is_active = 1 ORDER BY id");
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function hydrateAll(array $rows): array {
        return array_map(fn(array $row) => Employee::fromArray($row), $rows);
    }
}