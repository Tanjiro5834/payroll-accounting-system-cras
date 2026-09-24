<?php
namespace App\Repository;

use App\Entity\Holiday;

class HolidayRepository extends BaseRepository{
    public function create(array $data) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "INSERT INTO holidays 
                (holiday_date, name, type) VALUES (?, ?, ?)"
            );

            $stmt->execute([
                'holiday_date' => $data['holiday_date'],
                'name' => $data['name'],
                'type' => $data['type']
            ]);

            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Holiday creation failed" . $e->getMessage());
            throw $e;
        }
    }

    public function update(int $id, array $data) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE holidays
                SET holiday_date = ?,
                name = ?,
                type = ?
                WHERE id = :id"
            );

            $stmt->execute([
                $data['holiday_date'],
                $data['name'],
                $data['type']
            ]);

            $this->db->commit();
            return true;
        }catch(Exception $e){
            if(!$this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to update holiday" . $e->getMessage());
            throw $e;
        }
    }

    public function delete(int $id) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->execute([$id]);

            $deleted = $stmt->rowCount();
            $this->db->commit();

            return $deleted;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to delete holiday" . $e->getMessage());
            throw $e;
        }
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM holidays WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByDate(string $date) {
        $stmt = $this->db->prepare("SELECT * FROM holidays WHERE holiday_date = ?");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findAll() {
        $stmt = $this->db->prepare("SELECT * FROM holidays");
        $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByYear(int $year): array{
        try {
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-01-01', $year + 1);

            $stmt = $this->db->prepare(
                "SELECT id,
                        holiday_date,
                        name,
                        type,
                        created_at
                FROM holidays
                WHERE holiday_date >= ?
                AND holiday_date <  ?
                ORDER BY holiday_date ASC"
            );

            $stmt->execute([$start, $end]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching holidays for year {$year}: " . $e->getMessage());
            throw $e;
        }
    }

    public function findByType(string $type) {
        $stmt = $this-db->prepare("SELECT * FROM holidays WHERE type = ?");
        $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByDateRange(string $start, string $end) {
        $stmt = $this->db->prepare(
            "SELECT id, holiday_date, name, type, created_at 
        FROM holidays WHERE holiday_date >= :start 
        AND holiday_date <= :end ORDER BY holiday_date DESC");
        $stmt->execute([$start, $end]);
        $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isHoliday(string $date) {
        $stmt = $this->db->prepare("SELECT id, holiday_date, name, type, created_at 
        FROM holidays WHERE holiday_date = ? LIMIT 1");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getHolidayType(string $date) {
        $stmt = $this->db->prepare("SELECT id, holiday_date, name, type, created_at
        FROM holidays WHERE type = ? ORDER BY holiday_date DESC");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}