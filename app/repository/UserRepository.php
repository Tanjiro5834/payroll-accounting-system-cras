<?php
namespace App\Repository;

use App\Entity\User;

class UserRepository extends BaseRepository{
    // Create
    public function create(User $user): int {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "INSERT INTO users
                (username, password_hash, role, employee_id, 
                last_login, is_active, created_at, updated_at)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                $user->getUsername(),
                $user->getPasswordHash(),
                $user->getRole(),
                $user->getEmployeeId(),
                $user->getLastLogin(),
                $user->isActive() ? 1 : 0,
                $user->getCreatedAt() ?? $now,
                $user->getUpdatedAt() ?? $now,
            ]);

            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            if ($e->getCode() === '23000') {
                throw new DomainException('Username already exists.');
            }
            throw $e;
        }
    }

    // Read - Single
    public function findById(int $id){
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByEmployeeId($employeeId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByUsernameOrEmail($username, $email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Read - Multiple
    public function findAll() {
        $stmt = $this->db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllActive() {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE is_active = ? ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllByRole($role) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE role = ?");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllByEmployeeIds(array $employeeIds): array {
        if(empty($employeeIds)) return [];

        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));

        $sql = "SELECT * FROM users
                WHERE employee_id IN ($placeholders)
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($employeeIds));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search($query) {
        $query = trim($query);

        if ($query === '') return [];

        $stmt = $this->db->prepare("SELECT id, username, role, employee_id, 
                last_login, is_active, created_at, updated_at
                FROM `users`
                WHERE username    LIKE :term
                OR employee_id LIKE :term
                OR role        LIKE :term
                ORDER BY created_at DESC");

        $stmt->execute([':term' => '%' . $query . '%']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update
    public function update(User $user) : bool {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE users SET
                username = ?,
                password_hash = ?,
                role = ?,
                employee_id = ?,
                last_login = ?,
                is_active = ?
                updated_at = NOW()
                WHERE id = ?"
            );

            $stmt->execute([
                $user->getUsername(),
                $user->getPassword(),
                $user->getRole(),
                $user->getEmployeeId(),
                $user->getLastLogin(),
                (int) $user->getIsActive(),
                $user->getId()
            ]);

            $stmt = $this->db->prepare(
                "SELECT id, username, role, employee_id, last_login, is_active, created_at, updated_at
                FROM users WHERE id = ?"
            );

            $id = $user->getId();
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function updatePassword(int $id, string $passwordHash): bool {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$id, $passwordHash]);
            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function updateLastLogin(int $id): bool {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function updateRole(int $id, string $role): bool {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$id, $role]);
            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function updateStatus($id, $isActive) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$id, $status]);
            $this->db->commit();
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    // Link / Unlink
    public function linkToEmployee(int $userId, int $employeeId): bool {
        $checkLink = $this->db->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ? LIMIT 1");
        $checkLink->execute([$employeeId, $userId]);

        if($checkLink->fetchColumn()){
            throw new \RuntimeException('Employee is already linked to another user');
        }

        $checkExist = $this->db->prepare("SELECT 1 FROM `employees` WHERE id = ? LIMIT 1");
        $checkExist->execute([$employeeId]);

        if (!$checkExist->fetchColumn()) {
            throw new \RuntimeException('Employee not found');
        }

        $stmt = $this->db->prepare(
            "UPDATE users SET employee_id = ?, 
            updated_at  = NOW() 
            WHERE id = ?
            AND (employee_id IS NULL OR employee_id != ?)
            ");

        $stmt->execute([$employeeId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function unlinkFromEmployee(int $userId) {
        $check = $this->db->prepare(
            "SELECT employee_id FROM `users` WHERE id = :user_id LIMIT 1"
        );
        $check->execute([':user_id' => $userId]);
        $current = $check->fetchColumn();

        // User doesn't exist
        if ($current === false) {
            throw new \RuntimeException('User not found');
        }

        $stmt = $this->db->prepare("UPDATE users SET employee_id = NULL, updated_at  = NOW() WHERE id = ?");
        $stmt->execute([$id, $status]);
        $this->db->commit();
    }

    // Delete
    public function delete(int $id): bool {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function deactivate(int $id) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function reactivate(int $id) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    // Checks
    public function userExists(int $id): bool{
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    public function usernameExists(string $username, int $exceptId = null): bool {
        $sql = "SELECT 1 FROM users WHERE username = ?";
        $params = [$username];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function employeeIdExists(int $employeeId, int $exceptId = null) {
        $sql = "SELECT 1 FROM users WHERE employee_id = ?";
        $params = [$employeeId];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    // Counters
    public function countAll() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countActive() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 ORDER BY username DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByRole($role) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = ? ORDER BY username DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}