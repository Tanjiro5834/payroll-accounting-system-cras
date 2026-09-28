<?php
namespace App\Repository;

use PDO;
use Throwable;

class RateLimitRepository extends BaseRepository {
    private const COLUMNS = 'id, rate_key, attempts, window_start, expires_at';
    private const MAX_LIMIT = 1000;

    public function findByKey(string $rateKey): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM rate_limits WHERE rate_key = ? LIMIT 1"
        );
        $stmt->execute([$rateKey]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM rate_limits ORDER BY expires_at ASC, id ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActive(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM rate_limits
             WHERE expires_at IS NULL OR expires_at > NOW()
             ORDER BY expires_at ASC, id ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByKey(string $rateKey): int {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(attempts, 0) FROM rate_limits WHERE rate_key = ? LIMIT 1"
        );
        $stmt->execute([$rateKey]);
        $attempts = $stmt->fetchColumn();

        return $attempts === false ? 0 : (int) $attempts;
    }

    public function create(string $rateKey, int $windowSeconds): int {
        $expiresAt = $this->expiresAt($windowSeconds);

        $stmt = $this->db->prepare(
            "INSERT INTO rate_limits (rate_key, attempts, window_start, expires_at)
             VALUES (:rate_key, 1, NOW(), :expires_at)"
        );
        $stmt->execute([
            ':rate_key'   => $rateKey,
            ':expires_at' => $expiresAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function increment(string $rateKey, int $windowSeconds): int {
        $expiresAt = $this->expiresAt($windowSeconds);

        $this->db->prepare(
            "INSERT INTO rate_limits (rate_key, attempts, window_start, expires_at)
             VALUES (:rate_key, 1, NOW(), :expires_at)
             ON DUPLICATE KEY UPDATE
                attempts    = IF(expires_at IS NULL OR expires_at > NOW(), attempts + 1, 1),
                window_start = IF(expires_at IS NULL OR expires_at > NOW(), window_start, NOW()),
                expires_at   = IF(expires_at IS NULL OR expires_at > NOW(), expires_at, VALUES(expires_at))"
        )->execute([
            ':rate_key'   => $rateKey,
            ':expires_at' => $expiresAt,
        ]);

        return $this->countByKey($rateKey);
    }

    public function isLimited(string $rateKey, int $maxAttempts, int $windowSeconds): bool {
        $row = $this->findByKey($rateKey);
        if ($row === null) {
            return false;
        }

        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) <= time()) {
            return false;
        }

        return (int) $row['attempts'] >= $maxAttempts;
    }

    public function reset(string $rateKey): bool {
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
        $stmt->execute([$rateKey]);

        return $stmt->rowCount() > 0;
    }

    public function resetAll(): int {
        $stmt = $this->db->prepare("DELETE FROM rate_limits");
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteExpired(): int {
        $stmt = $this->db->prepare(
            "DELETE FROM rate_limits WHERE expires_at IS NOT NULL AND expires_at <= NOW()"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function deleteOlderThan(string $cutoff): int {
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE window_start < ?");
        $stmt->execute([$cutoff]);

        return $stmt->rowCount();
    }

    public function upsert(string $rateKey, int $attempts, string $expiresAt): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO rate_limits (rate_key, attempts, window_start, expires_at)
             VALUES (:rate_key, :attempts, NOW(), :expires_at)
             ON DUPLICATE KEY UPDATE
                attempts   = VALUES(attempts),
                expires_at = VALUES(expires_at)"
        );
        $stmt->execute([
            ':rate_key'   => $rateKey,
            ':attempts'   => $attempts,
            ':expires_at' => $expiresAt,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function expiresAt(int $windowSeconds): ?string {
        if ($windowSeconds <= 0) {
            return null;
        }

        $tz = new \DateTimeZone('Asia/Manila');
        $now = new \DateTimeImmutable('now', $tz);

        return $now->modify("+{$windowSeconds} seconds")->format('Y-m-d H:i:s');
    }
}