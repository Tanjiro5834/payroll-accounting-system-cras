<?php
namespace App\Service;

use App\Entity\SystemSetting;
use App\Repository\SystemSettingRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class SystemSettingService {
    public const WORK_START     = 'work_start';
    public const WORK_END       = 'work_end';
    public const LUNCH_START    = 'lunch_start';
    public const LUNCH_END      = 'lunch_end';
    public const REGULAR_HOURS  = 'regular_hours';
    public const LATE_THRESHOLD = 'late_threshold';
    public const WORK_DAYS      = 'work_days';
    public const REST_DAY       = 'rest_day';

    private const TIMEZONE = 'Asia/Manila';

    private const DEFAULTS = [
        self::WORK_START     => '08:00',
        self::WORK_END       => '17:00',
        self::LUNCH_START    => '12:00',
        self::LUNCH_END      => '13:00',
        self::REGULAR_HOURS  => '8',
        self::LATE_THRESHOLD => '10',
        self::WORK_DAYS      => 'mon,tue,wed,thu,fri,sat',
        self::REST_DAY       => 'sun',
    ];

    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private static ?array $cache = null;

    private SystemSettingRepository $repository;

    public function __construct(?SystemSettingRepository $repository = null) {
        $this->repository = $repository ?? new SystemSettingRepository();
    }

    public function getAll(): array {
        return $this->repository->findAll();
    }

    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }

    public function getByKey(string $key): ?array {
        return $this->repository->findByKey($key);
    }

    public function getValue(string $key, mixed $default = null): mixed {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function exists(string $key): bool {
        return array_key_exists($key, $this->all());
    }

    public function getMultiple(array $keys): array {
        $all    = $this->all();
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $all[$key] ?? null;
        }
        return $result;
    }

    public function setValue(string $key, mixed $value): void {
        $this->validateKey($key);
        $normalized = $this->validate($key, $value);
        $this->assertConsistent([$key => $normalized]);

        $this->repository->setValue($key, $normalized);
        $this->clearCache();
    }

    public function setMultiple(array $settings): int {
        if (!$settings) {
            return 0;
        }

        $normalized = [];
        foreach ($settings as $key => $value) {
            $key = (string) $key;
            $this->validateKey($key);
            $normalized[$key] = $this->validate($key, $value);
        }
        $this->assertConsistent($normalized);

        $count = $this->repository->setMultiple($normalized);
        $this->clearCache();
        return $count;
    }

    public function create(array $data): SystemSetting {
        $key = trim((string) ($data['setting_key'] ?? ''));
        $this->validateKey($key);

        if ($this->repository->exists($key)) {
            throw new DomainException("Setting '{$key}' already exists.");
        }

        $value = $this->validate($key, $data['setting_value'] ?? null);
        $this->assertConsistent([$key => $value]);

        $record = SystemSetting::fromArray([
            'setting_key'   => $key,
            'setting_value' => $value,
            'description'   => $data['description'] ?? null,
        ]);

        $this->repository->create($record);
        $this->clearCache();
        return $record;
    }

    public function update(int $id, array $data): bool {
        $current = $this->requireSetting($id);
        $key = $current['setting_key'];

        if (isset($data['setting_key']) && $data['setting_key'] !== $key) {
            $newKey = trim((string) $data['setting_key']);

            if ($this->isCore($key) || $this->isCore($newKey)) {
                throw new DomainException('Core settings cannot be renamed, and custom settings cannot take a core name.');
            }
            $this->validateKey($newKey);
            if ($this->repository->exists($newKey)) {
                throw new DomainException("Setting '{$newKey}' already exists.");
            }

            $data['setting_key'] = $key = $newKey;
        }

        if (array_key_exists('setting_value', $data)) {
            $data['setting_value'] = $this->validate($key, $data['setting_value']);
            $this->assertConsistent([$key => $data['setting_value']]);
        }

        $changed = $this->repository->update($id, $data);
        $this->clearCache();
        return $changed;
    }

    public function delete(int $id): bool {
        $current = $this->requireSetting($id);
        if ($this->isCore($current['setting_key'])) {
            throw new DomainException("Core setting '{$current['setting_key']}' cannot be deleted.");
        }

        $deleted = $this->repository->delete($id);
        $this->clearCache();
        return $deleted;
    }

    public function resetDefaults(): int {
        $count = $this->repository->setMultiple(self::DEFAULTS);
        $this->clearCache();
        return $count;
    }

    public function getWorkStart(): string { return $this->core(self::WORK_START); }
    public function getWorkEnd(): string   { return $this->core(self::WORK_END); }
    public function getLunchStart(): string { return $this->core(self::LUNCH_START); }
    public function getLunchEnd(): string  { return $this->core(self::LUNCH_END); }

    public function getRegularHours(): float {
        return (float) $this->core(self::REGULAR_HOURS);
    }

    public function getLateThreshold(): int {
        return (int) $this->core(self::LATE_THRESHOLD);
    }

    public function getWorkDays(): array {
        return explode(',', $this->core(self::WORK_DAYS));
    }

    public function getRestDay(): string {
        return $this->core(self::REST_DAY);
    }

    public function loadAllToCache(): void {
        self::$cache = [];
        foreach ($this->repository->findAll() as $row) {
            self::$cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    public function clearCache(): void {
        self::$cache = null;
    }

    private function all(): array {
        if (self::$cache === null) {
            $this->loadAllToCache();
        }
        return self::$cache;
    }

    private function core(string $key): string {
        $value = $this->getValue($key);
        return ($value === null || $value === '') ? self::DEFAULTS[$key] : (string) $value;
    }

    private function isCore(string $key): bool {
        return array_key_exists($key, self::DEFAULTS);
    }

    private function requireSetting(int $id): array {
        $setting = $this->repository->findById($id);
        if (!$setting) {
            throw new DomainException("Setting not found: {$id}");
        }
        return $setting;
    }

    private function validateKey(string $key): void {
        if (!preg_match('/^[a-z][a-z0-9_]{0,99}$/', $key)) {
            throw new InvalidArgumentException("Invalid setting key: '{$key}' (use snake_case, max 100 chars).");
        }
    }

    private function validate(string $key, mixed $value): ?string {
        return match ($key) {
            self::WORK_START, self::WORK_END,
            self::LUNCH_START, self::LUNCH_END => $this->time($key, $value),
            self::REGULAR_HOURS                => $this->number($key, $value, 1, 24),
            self::LATE_THRESHOLD               => $this->integer($key, $value, 0, 120),
            self::WORK_DAYS                    => $this->days($value),
            self::REST_DAY                     => $this->day($key, $value),
            default                            => $this->scalar($key, $value),
        };
    }

    private function assertConsistent(array $incoming): void {
        if (!array_intersect_key($incoming, self::DEFAULTS)) {
            return;
        }

        $stored = array_filter(
            array_intersect_key($this->all(), self::DEFAULTS),
            fn($v) => $v !== null && $v !== ''
        );
        $s = array_replace(self::DEFAULTS, $stored, $incoming);

        if ($s[self::WORK_START] >= $s[self::WORK_END]) {
            throw new DomainException('Work start must be before work end.');
        }
        if ($s[self::LUNCH_START] >= $s[self::LUNCH_END]) {
            throw new DomainException('Lunch start must be before lunch end.');
        }
        if ($s[self::LUNCH_START] < $s[self::WORK_START] || $s[self::LUNCH_END] > $s[self::WORK_END]) {
            throw new DomainException('Lunch break must fall within work hours.');
        }
        if (in_array($s[self::REST_DAY], explode(',', $s[self::WORK_DAYS]), true)) {
            throw new DomainException('Rest day cannot also be a work day.');
        }
    }

    private function time(string $key, mixed $value): string {
        $v = is_string($value) ? trim($value) : '';
        foreach (['H:i', 'H:i:s'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $v, new DateTimeZone(self::TIMEZONE));
            if ($dt && $dt->format($fmt) === $v) {
                return $dt->format('H:i');
            }
        }
        throw new InvalidArgumentException("{$key} must be a time in HH:MM format.");
    }

    private function number(string $key, mixed $value, float $min, float $max): string {
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            throw new InvalidArgumentException("{$key} must be a number from {$min} to {$max}.");
        }
        return (string) (float) $value;
    }

    private function integer(string $key, mixed $value, int $min, int $max): string {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($int === false) {
            throw new InvalidArgumentException("{$key} must be a whole number from {$min} to {$max}.");
        }
        return (string) $int;
    }

    private function days(mixed $value): string {
        $days = is_array($value) ? $value : explode(',', (string) $value);
        $days = array_filter(array_map(fn($d) => strtolower(trim((string) $d)), $days));

        $invalid = array_diff($days, self::DAYS);
        if ($invalid) {
            throw new InvalidArgumentException('Invalid work day(s): ' . implode(', ', $invalid) . '. Use mon–sun.');
        }
        if (!$days) {
            throw new InvalidArgumentException('At least one work day is required.');
        }

        return implode(',', array_values(array_intersect(self::DAYS, $days)));
    }

    private function day(string $key, mixed $value): string {
        $day = strtolower(trim((string) $value));
        if (!in_array($day, self::DAYS, true)) {
            throw new InvalidArgumentException("{$key} must be one of: " . implode(', ', self::DAYS) . '.');
        }
        return $day;
    }

    private function scalar(string $key, mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw new InvalidArgumentException("{$key} must be a scalar value.");
    }
}