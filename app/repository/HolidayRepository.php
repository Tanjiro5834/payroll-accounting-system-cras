<?php
namespace App\Repository;

use App\Entity\Holiday;
use PDO;

class HolidayRepository extends BaseRepository {
    private const COLUMNS = 'id, holiday_date, name, type, created_at';

    public function findById(int $id): ?Holiday {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM holidays WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Holiday::fromArray($row) : null;
    }

    public function findByDate(string $date): ?Holiday {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM holidays WHERE holiday_date = ? LIMIT 1"
        );
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Holiday::fromArray($row) : null;
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM holidays ORDER BY holiday_date DESC"
        );
        $stmt->execute();

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByType(string $type): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM holidays WHERE type = ? ORDER BY holiday_date DESC"
        );
        $stmt->execute([$type]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByYear(int $year): array {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM holidays
             WHERE holiday_date >= :start
               AND holiday_date <  :end
             ORDER BY holiday_date ASC"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $end,
        ]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByDateRange(string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM holidays
             WHERE holiday_date >= :start
               AND holiday_date <  :end
             ORDER BY holiday_date ASC"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $this->nextDay($end),
        ]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function isHoliday(string $date): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM holidays WHERE holiday_date = ? LIMIT 1"
        );
        $stmt->execute([$date]);

        return $stmt->fetchColumn() !== false;
    }

    public function getHolidayType(string $date): ?string {
        $stmt = $this->db->prepare(
            "SELECT type FROM holidays WHERE holiday_date = ? LIMIT 1"
        );
        $stmt->execute([$date]);
        $type = $stmt->fetchColumn();

        return $type === false ? null : (string) $type;
    }

    public function create(Holiday $holiday): int {
        $stmt = $this->db->prepare(
            "INSERT INTO holidays (holiday_date, name, type) VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $holiday->getHolidayDate(),
            $holiday->getName(),
            $holiday->getType(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, Holiday $holiday): bool {
        $stmt = $this->db->prepare(
            "UPDATE holidays SET
                holiday_date = :holiday_date,
                name         = :name,
                type         = :type
             WHERE id = :id"
        );
        $stmt->execute([
            ':holiday_date' => $holiday->getHolidayDate(),
            ':name'         => $holiday->getName(),
            ':type'         => $holiday->getType(),
            ':id'           => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM holidays WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function dateExists(string $date, ?int $exceptId = null): bool {
        $sql    = "SELECT 1 FROM holidays WHERE holiday_date = ?";
        $params = [$date];

        if ($exceptId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function countByYear(int $year): int {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM holidays WHERE holiday_date >= ? AND holiday_date < ?"
        );
        $stmt->execute([$start, $end]);

        return (int) $stmt->fetchColumn();
    }

    private function hydrateAll(array $rows): array {
        return array_map(fn(array $row) => Holiday::fromArray($row), $rows);
    }

    private function nextDay(string $date): string {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date: {$date}");
        }
        return $dt->modify('+1 day')->format('Y-m-d');
    }
}