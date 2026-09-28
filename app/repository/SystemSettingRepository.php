<?php
namespace App\Repository;

use App\Entity\SystemSetting;
use InvalidArgumentException;
use PDO;

class SystemSettingRepository extends BaseRepository {
    private const UPDATABLE = ['setting_key', 'setting_value', 'description'];
    private const COLUMNS   = 'id, setting_key, setting_value, description, updated_at';

    public function create(SystemSetting $systemSetting): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO system_settings (setting_key, setting_value, description)
             VALUES (?, ?, ?)"
        );

        return $stmt->execute([
            $systemSetting->getSettingKey(),
            $this->toStorage($systemSetting->getSettingValue()),
            $systemSetting->getDescription(),
        ]);
    }

    public function update(int $id, array $data): bool {
        $fields = array_intersect_key($data, array_flip(self::UPDATABLE));
        if (!$fields) {
            throw new InvalidArgumentException('No updatable fields provided.');
        }

        if (array_key_exists('setting_value', $fields)) {
            $fields['setting_value'] = $this->toStorage($fields['setting_value']);
        }

        $set = implode(', ', array_map(fn(string $col) => "{$col} = ?", array_keys($fields)));

        $stmt = $this->db->prepare("UPDATE system_settings SET {$set} WHERE id = ?");
        $stmt->execute([...array_values($fields), $id]);

        return $stmt->rowCount() > 0;
    }

    public function setValue(string $key, mixed $value): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO system_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        return $stmt->execute([$key, $this->toStorage($value)]);
    }

    public function setMultiple(array $settings): int {
        if (!$settings) {
            return 0;
        }

        $tuples = implode(', ', array_fill(0, count($settings), '(?, ?)'));
        $params = [];
        foreach ($settings as $key => $value) {
            $params[] = (string) $key;
            $params[] = $this->toStorage($value);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO system_settings (setting_key, setting_value)
             VALUES {$tuples}
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute($params);

        return count($settings);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM system_settings WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByKey(string $key): bool {
        $stmt = $this->db->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);

        return $stmt->rowCount() > 0;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM system_settings WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByKey(string $key): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM system_settings WHERE setting_key = ? LIMIT 1"
        );
        $stmt->execute([$key]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM system_settings ORDER BY setting_key"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getValue(string $key, mixed $default = null): mixed {
        $stmt = $this->db->prepare(
            "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1"
        );
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : $value;
    }

    public function exists(string $key): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM system_settings WHERE setting_key = ? LIMIT 1"
        );
        $stmt->execute([$key]);

        return $stmt->fetchColumn() !== false;
    }

    public function getMultiple(array $keys): array {
        $keys = array_values(array_unique(array_map('strval', $keys)));
        if (!$keys) {
            return [];
        }

        $in   = implode(', ', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare(
            "SELECT setting_key, setting_value
             FROM system_settings
             WHERE setting_key IN ({$in})"
        );
        $stmt->execute($keys);

        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_replace(array_fill_keys($keys, null), $rows);
    }

    public function countAll(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM system_settings");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function toStorage(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw new InvalidArgumentException('Setting values must be scalar or null.');
    }
}