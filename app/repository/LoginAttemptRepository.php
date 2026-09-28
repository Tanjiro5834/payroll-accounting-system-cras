<?php
namespace App\Repository;

use App\Entity\LoginAttempt;
use PDO;

class LoginAttemptRepository extends BaseRepository {
    private const COLUMNS = 'id, username, ip_address, success, attempted_at';
    private const MAX_LIMIT = 500;

    public function create(LoginAttempt $loginAttempt): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO login_attempts (username, ip_address, success, attempted_at)
             VALUES (?, ?, ?, ?)"
        );

        return $stmt->execute([
            $loginAttempt->getUsername(),
            $loginAttempt->getIpAddress(),
            $loginAttempt->getSuccess() ? 1 : 0,
            $loginAttempt->getAttemptedAt(),
        ]);
    }

    public function findByUsername(string $username, int $limit = 20): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM login_attempts
             WHERE username = :username
             ORDER BY attempted_at DESC, id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByIpAddress(string $ip, int $limit = 20): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM login_attempts
             WHERE ip_address = :ip
             ORDER BY attempted_at DESC, id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRecent(int $limit = 50): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM login_attempts
             ORDER BY attempted_at DESC, id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDateRange(string $start, string $end, int $limit = self::MAX_LIMIT): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM login_attempts
             WHERE attempted_at >= :start
               AND attempted_at <  :end
             ORDER BY attempted_at DESC, id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':start', $start, PDO::PARAM_STR);
        $stmt->bindValue(':end', $this->nextDay($end), PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFailedAttempts(string $username, string $since): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE username = :username
               AND success = 0
               AND attempted_at >= :since"
        );
        $stmt->execute([
            ':username' => $username,
            ':since'    => $since,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countFailedAttemptsByIp(string $ip, string $since): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip
               AND success = 0
               AND attempted_at >= :since"
        );
        $stmt->execute([
            ':ip'    => $ip,
            ':since' => $since,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function isIpLockedOut(string $ip, int $threshold, string $since): bool {
        if ($threshold <= 0) {
            return false;
        }

        return $this->countFailedAttemptsByIp($ip, $since) >= $threshold;
    }

    public function isUsernameLockedOut(string $username, int $threshold, string $since): bool {
        if ($threshold <= 0) {
            return false;
        }

        return $this->countFailedAttempts($username, $since) >= $threshold;
    }

    public function deleteOlderThan(string $date): int {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
        $stmt->execute([$date]);

        return $stmt->rowCount();
    }

    public function clearForUsername(string $username): int {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);

        return $stmt->rowCount();
    }

    public function clearForIp(string $ip): int {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);

        return $stmt->rowCount();
    }

    private function clampLimit(int $limit): int {
        return max(1, min(self::MAX_LIMIT, $limit));
    }

    private function nextDay(string $date): string {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date: {$date}");
        }
        return $dt->modify('+1 day')->format('Y-m-d');
    }
}