<?php
namespace App\Repository;

class DeductionRepository extends BaseRepository{
    public function findAllActive(): array {
        $stmt = $this->db->prepare("SELECT * FROM deductions WHERE is_active = 1 ORDER BY employee_id");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => Deduction::fromArray($row), $rows);
    }
    
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM deductions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByType(string $type): array {
        $stmt = $this->db->prepare("SELECT * FROM deductions WHERE type = ?");
        $stmt->execute([$type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int {
        try{
            $stmt = $this->db->prepare(
                "INSERT INTO deductions
                (code, name, type, value, is_mandatory, is_active, created_at, updated_at)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                'code' => $data['code'],
                'name' => $data['name'],
                'value' => $data['value'],
                'is_mandatory' => $data['is_mandatory'],
                'is_active' => $data['is_active'],
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at']
            ]);

            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();

            return $newId;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE deductions
                    SET code         = :code,
                        name         = :name,
                        type         = :type,
                        is_mandatory = :is_mandatory,
                        is_active    = :is_active,
                        created_at   = :created_at,
                        updated_at   = :updated_at
                    WHERE id = :id");

            $stmt->execute([
                'id'           => $id,
                'code'         => $data['code'],
                'name'         => $data['name'],
                'type'         => $data['type'],
                'is_mandatory' => $data['is_mandatory'] ?? 0,
                'is_active'    => $data['is_active']    ?? 1,
                'created_at'   => $data['created_at']   ?? date('Y-m-d H:i:s'),
                'updated_at'   => $data['updated_at']   ?? date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();
            return true;
        }catch(Exception $e){
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function deactivate(int $id): bool {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE deductions SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }
}