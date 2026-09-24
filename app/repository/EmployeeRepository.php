<?php

namespace App\Repository;

use App\Entity\Employee;
use PDO;

class EmployeeRepository extends BaseRepository{
    public function findAll() : array {
        $stmt = $this->db->prepare("SELECT * FROM employees");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => Employee::fromArray($row), $rows);
    }

    public function findAllActive() : array {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE is_active = 1 ORDER BY full_name");
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => Employee::fromArray($row), $rows);
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Employee::fromArray($row) : null;
    }

    public function findByRole(string $role) : array {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE role = :role");
        $stmt->execute(['role' => $role]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => Employee::fromArray($row), $rows);
    }

    public function create(){
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "INSERT INTO employees
                (full_name, role, profile_photo_url, sss_number, philhealth_number, pagibig_number,
                tin_number, hourly_rate, monthly_rate, pay_frequency, date_hired, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $employee->getFullName(), $employee->getRole(), $employee->getProfilePhotoUrl(),
                $employee->getSssNumber(), $employee->getPhilhealthNumber(), $employee->getPagibigNumber(),
                $employee->getTinNumber(), $employee->getHourlyRate(), $employee->getMonthlyRate(),
                $employee->getPayFrequency(), $employee->getDateHired(), $employee->getIsActive() ? 1 : 0
            ]);

            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $newId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Employee creation failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function update(Employee $employee){
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE employees SET
                    full_name = ?,
                    role = ?,
                    profile_photo_url = ?,
                    sss_number = ?,
                    philhealth_number = ?,
                    pagibig_number = ?,
                    tin_number = ?,
                    hourly_rate = ?,
                    monthly_rate = ?,
                    pay_frequency = ?,
                    date_hired = ?,
                    is_active = ?
                WHERE id = ?"
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
                $employee->getId()
            ]);

            $this->db->commit();

            return $stmt->rowCount();

        } catch(\Throwable $e){
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Employee update failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function findByUsername(string $username) :?array{
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE username = :username");
        $stmt->execute([$username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function deactivate(int $id): bool {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE employees SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $this->db->commit();
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function reactivate(int $id): bool {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("UPDATE employees SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}