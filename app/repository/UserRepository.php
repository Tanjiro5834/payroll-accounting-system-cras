<?php
namespace App\Repository;

use App\Entity\User;
use DomainException;
use PDO;

class UserRepository extends BaseRepository {
    private const PUBLIC_COLUMNS = 'id, username, role, employee_id, last_login, is_active, created_at, updated_at';
    private const FULL_COLUMNS   = 'id, username, password_hash, role, employee_id, last_login, is_active, created_at, updated_at';

    public function create(User $user): int {
        $stmt = $this->db->prepare(
            "INSERT INTO users (username, password_hash, role, employee_id, last_login, is_active)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        try {
            $stmt->execute([
                $user->getUsername(),
                $user->getPasswordHash(),
                $user->getRole(),
                $user->getEmployeeId(),
                $user->getLastLogin(),
                $user->getIsActive() ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new DomainException('Username already exists.');
            }
            throw $e;
        }

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . " FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.password_hash, u.role, u.employee_id, u.last_login,
                    u.is_active, u.created_at, u.updated_at, e.full_name
             FROM users u
             LEFT JOIN employees e ON e.id = u.employee_id
             WHERE u.username = ? LIMIT 1"
        );
        $stmt->execute([$username]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmployeeId(int $employeeId): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . " FROM users WHERE employee_id = ? LIMIT 1"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . " FROM users ORDER BY username"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllActive(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . " FROM users WHERE is_active = 1 ORDER BY username"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllByRole(string $role): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . " FROM users WHERE role = ? ORDER BY username"
        );
        $stmt->execute([$role]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllByEmployeeIds(array $employeeIds): array {
        if (empty($employeeIds)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        $in  = implode(', ', array_fill(0, count($ids), '?'));

        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . "
             FROM users
             WHERE employee_id IN ({$in})
             ORDER BY username"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $query, int $limit = 100): array {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $term  = '%' . $query . '%';

        $stmt = $this->db->prepare(
            "SELECT " . self::PUBLIC_COLUMNS . "
             FROM users
             WHERE username LIKE :term1
                OR role     LIKE :term2
             ORDER BY username
             LIMIT :lim"
        );
        $stmt->bindValue(':term1', $term, PDO::PARAM_STR);
        $stmt->bindValue(':term2', $term, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(User $user): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET
                username      = :username,
                password_hash = :password_hash,
                role          = :role,
                employee_id   = :employee_id,
                last_login    = :last_login,
                is_active     = :is_active
             WHERE id = :id"
        );
        $stmt->execute([
            ':username'      => $user->getUsername(),
            ':password_hash' => $user->getPasswordHash(),
            ':role'          => $user->getRole(),
            ':employee_id'   => $user->getEmployeeId(),
            ':last_login'    => $user->getLastLogin(),
            ':is_active'     => $user->getIsActive() ? 1 : 0,
            ':id'            => $user->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updatePassword(int $id, string $passwordHash): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET password_hash = ? WHERE id = ?"
        );
        $stmt->execute([$passwordHash, $id]);

        return $stmt->rowCount() > 0;
    }

    public function updateLastLogin(int $id): bool {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function updateRole(int $id, string $role): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET role = ? WHERE id = ? AND role <> ?"
        );
        $stmt->execute([$role, $id, $role]);

        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, bool $isActive): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET is_active = ? WHERE id = ? AND is_active <> ?"
        );
        $stmt->execute([$isActive ? 1 : 0, $id, $isActive ? 1 : 0]);

        return $stmt->rowCount() > 0;
    }

    public function linkToEmployee(int $userId, int $employeeId): bool {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET employee_id = :employee_id
             WHERE id = :user_id
               AND (employee_id IS NULL OR employee_id <> :employee_id_guard)"
        );
        $stmt->execute([
            ':employee_id'       => $employeeId,
            ':user_id'           => $userId,
            ':employee_id_guard' => $employeeId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function unlinkFromEmployee(int $userId): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET employee_id = NULL WHERE id = ? AND employee_id IS NOT NULL"
        );
        $stmt->execute([$userId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET is_active = 0 WHERE id = ? AND is_active = 1"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function reactivate(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE users SET is_active = 1 WHERE id = ? AND is_active = 0"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function userExists(int $id): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        return $stmt->fetchColumn() !== false;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool {
        $sql    = "SELECT 1 FROM users WHERE username = ?";
        $params = [$username];

        if ($exceptId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function employeeIdExists(int $employeeId, ?int $exceptId = null): bool {
        $sql    = "SELECT 1 FROM users WHERE employee_id = ?";
        $params = [$employeeId];

        if ($exceptId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function countAll(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countActive(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countByRole(string $role): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$role]);

        return (int) $stmt->fetchColumn();
    }
}