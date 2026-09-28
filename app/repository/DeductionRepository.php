<?php
namespace App\Repository;

use App\Entity\Deduction;
use PDO;

class DeductionRepository extends BaseRepository {
    private const COLUMNS = 'id, code, name, type, value, is_mandatory, is_active, created_at, updated_at';

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM deductions ORDER BY name"
        );
        $stmt->execute();

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllActive(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM deductions WHERE is_active = 1 ORDER BY name"
        );
        $stmt->execute();

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Deduction {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM deductions WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Deduction::fromArray($row) : null;
    }

    public function findByCode(string $code): ?Deduction {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM deductions WHERE code = ? LIMIT 1"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Deduction::fromArray($row) : null;
    }

    public function findByType(string $type): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM deductions WHERE type = ? ORDER BY name"
        );
        $stmt->execute([$type]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByTypeActive(string $type): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM deductions WHERE type = ? AND is_active = 1 ORDER BY name"
        );
        $stmt->execute([$type]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllMandatory(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM deductions WHERE is_mandatory = 1 AND is_active = 1 ORDER BY name"
        );
        $stmt->execute();

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(Deduction $deduction): int {
        $stmt = $this->db->prepare(
            "INSERT INTO deductions (code, name, type, value, is_mandatory, is_active)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $deduction->getCode(),
            $deduction->getName(),
            $deduction->getType(),
            $deduction->getValue(),
            $deduction->getIsMandatory() ? 1 : 0,
            $deduction->getIsActive() ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, Deduction $deduction): bool {
        $stmt = $this->db->prepare(
            "UPDATE deductions SET
                code         = :code,
                name         = :name,
                type         = :type,
                value        = :value,
                is_mandatory = :is_mandatory,
                is_active    = :is_active
             WHERE id = :id"
        );
        $stmt->execute([
            ':code'         => $deduction->getCode(),
            ':name'         => $deduction->getName(),
            ':type'         => $deduction->getType(),
            ':value'        => $deduction->getValue(),
            ':is_mandatory' => $deduction->getIsMandatory() ? 1 : 0,
            ':is_active'    => $deduction->getIsActive() ? 1 : 0,
            ':id'           => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool {
        $stmt = $this->db->prepare("UPDATE deductions SET is_active = 0 WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function reactivate(int $id): bool {
        $stmt = $this->db->prepare("UPDATE deductions SET is_active = 1 WHERE id = ? AND is_active = 0");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM deductions WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function codeExists(string $code, ?int $exceptId = null): bool {
        $sql    = "SELECT 1 FROM deductions WHERE code = ?";
        $params = [$code];

        if ($exceptId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function countActive(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM deductions WHERE is_active = 1");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function hydrateAll(array $rows): array {
        return array_map(fn(array $row) => Deduction::fromArray($row), $rows);
    }
}