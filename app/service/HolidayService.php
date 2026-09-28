<?php
namespace App\Service;

use App\Entity\Holiday;
use App\Repository\HolidayRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class HolidayService {
    private const TIMEZONE = 'Asia/Manila';

    private const TYPES = ['regular', 'special_non_working', 'special_working'];

    private const PREMIUM_MULTIPLIERS = [
        'regular'             => '2.00',
        'special_non_working' => '1.30',
        'special_working'     => '1.00',
    ];

    private const MAX_NAME_LENGTH = 100;

    private HolidayRepository $repository;

    public function __construct(?HolidayRepository $repository = null) {
        $this->repository = $repository ?? new HolidayRepository();
    }

    public function getAll(): array {
        return $this->repository->findAll();
    }

    public function getById(int $id): ?Holiday {
        return $this->repository->findById($id);
    }

    public function getByDate(string $date): ?Holiday {
        $this->assertValidDate($date, 'date');
        return $this->repository->findByDate($date);
    }

    public function getByYear(int $year): array {
        $this->assertValidYear($year);
        return $this->repository->findByYear($year);
    }

    public function getByType(string $type): array {
        $this->assertValidType($type);
        return $this->repository->findByType($type);
    }

    public function getByDateRange(string $start, string $end): array {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        return $this->repository->findByDateRange($start, $end);
    }

    public function create(array $data): int {
        $clean = $this->validate($data, false);
        $this->assertDateNotTaken($clean['holiday_date'], null);

        return $this->repository->create(Holiday::fromArray($clean));
    }

    public function update(int $id, array $data): bool {
        $this->requireHoliday($id);

        $clean = $this->validate($data, true);
        if (isset($clean['holiday_date'])) {
            $this->assertDateNotTaken($clean['holiday_date'], $id);
        }

        return $this->repository->update($id, Holiday::fromArray($clean));
    }

    public function delete(int $id): bool {
        $this->requireHoliday($id);
        return $this->repository->delete($id);
    }

    public function isHoliday(string $date): bool {
        $this->assertValidDate($date, 'date');
        return $this->repository->isHoliday($date);
    }

    public function getHolidayType(string $date): ?string {
        $this->assertValidDate($date, 'date');
        return $this->repository->getHolidayType($date);
    }

    public function getHolidayName(string $date): ?string {
        $holiday = $this->getByDate($date);
        return $holiday?->getName();
    }

    public function isRegularHoliday(string $date): bool {
        return $this->getHolidayType($date) === 'regular';
    }

    public function isSpecialNonWorking(string $date): bool {
        return $this->getHolidayType($date) === 'special_non_working';
    }

    public function isSpecialWorking(string $date): bool {
        return $this->getHolidayType($date) === 'special_working';
    }

    public function computeHolidayPremium(string $date, string $hours, string $hourlyRate): array {
        $this->assertValidDate($date, 'date');

        if (!is_numeric($hours) || (float) $hours < 0) {
            throw new InvalidArgumentException('Hours must be a non-negative number.');
        }
        if (!is_numeric($hourlyRate) || (float) $hourlyRate < 0) {
            throw new InvalidArgumentException('Hourly rate must be a non-negative number.');
        }

        $type = $this->getHolidayType($date) ?? 'special_working';
        $multiplier = self::PREMIUM_MULTIPLIERS[$type] ?? '1.00';

        $base  = bcmul($hours, $hourlyRate, 4);
        $total = $this->roundHalfUp(bcmul($base, $multiplier, 4));
        $premium = bcsub($total, $this->roundHalfUp($base), 2);

        return [
            'type'       => $type,
            'multiplier' => $multiplier,
            'premium'    => $premium,
            'total'      => $total,
        ];
    }

    public function seedPhilippineHolidays(int $year): int {
        $this->assertValidYear($year);

        $inserted = 0;
        foreach ($this->philippineHolidays($year) as $row) {
            if ($this->repository->isHoliday($row['holiday_date'])) continue;

            $this->repository->create(Holiday::fromArray($row));
            $inserted++;
        }

        return $inserted;
    }

    public function importFromCsv(string $file): array {
        if (!is_readable($file)) {
            throw new InvalidArgumentException("CSV file is not readable: {$file}");
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new DomainException("Failed to open CSV: {$file}");
        }

        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                return $result;
            }

            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if (count($row) < 3) {
                    $result['errors'][] = "Line {$line}: expected 3 columns.";
                    $result['skipped']++;
                    continue;
                }

                $data = [
                    'holiday_date' => trim((string) $row[0]),
                    'name'         => trim((string) $row[1]),
                    'type'         => trim((string) $row[2]),
                ];

                try {
                    $clean = $this->validate($data, false);
                } catch (InvalidArgumentException $e) {
                    $result['errors'][] = "Line {$line}: {$e->getMessage()}";
                    $result['skipped']++;
                    continue;
                }

                if ($this->repository->isHoliday($clean['holiday_date'])) {
                    $result['errors'][] = "Line {$line}: duplicate date {$clean['holiday_date']}.";
                    $result['skipped']++;
                    continue;
                }

                $this->repository->create(Holiday::fromArray($clean));
                $result['imported']++;
            }
        } finally {
            fclose($handle);
        }

        return $result;
    }

    private function validate(array $data, bool $isUpdate): array {
        $errors = [];

        $date = $this->validateHolidayDate($data, $isUpdate, $errors);
        $name = $this->validateName($data, $isUpdate, $errors);
        $type = $this->validateType($data, $isUpdate, $errors);

        if ($errors) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $clean = array_filter([
            'holiday_date' => $date,
            'name'         => $name,
            'type'         => $type,
        ], fn($v) => $v !== null);

        if ($isUpdate && !$clean) {
            throw new InvalidArgumentException('No updatable fields provided.');
        }

        return $clean;
    }

    private function validateHolidayDate(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('holiday_date', $data)) return null;

        $date = trim((string) ($data['holiday_date'] ?? ''));
        if ($date === '') {
            $errors['holiday_date'] = 'Holiday date is required.';
            return null;
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            $errors['holiday_date'] = 'Holiday date must be a valid YYYY-MM-DD.';
            return null;
        }

        return $date;
    }

    private function validateName(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('name', $data)) return null;

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Holiday name is required.';
            return null;
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Holiday name must be ' . self::MAX_NAME_LENGTH . ' characters or fewer.';
            return null;
        }

        return $name;
    }

    private function validateType(array $data, bool $isUpdate, array &$errors): ?string {
        if ($isUpdate && !array_key_exists('type', $data)) return null;

        $type = trim((string) ($data['type'] ?? ''));
        if ($type === '') {
            $errors['type'] = 'Holiday type is required.';
            return null;
        }
        if (!in_array($type, self::TYPES, true)) {
            $errors['type'] = 'Holiday type must be one of: ' . implode(', ', self::TYPES) . '.';
            return null;
        }

        return $type;
    }

    private function requireHoliday(int $id): Holiday {
        $holiday = $this->repository->findById($id);
        if (!$holiday) {
            throw new DomainException("Holiday not found: {$id}");
        }
        return $holiday;
    }

    private function assertDateNotTaken(string $date, ?int $exceptId): void {
        if ($this->repository->dateExists($date, $exceptId)) {
            throw new DomainException("A holiday already exists on {$date}.");
        }
    }

    private function assertValidDate(string $date, string $field): void {
        $tz = new DateTimeZone(self::TIMEZONE);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }

    private function assertDateOrder(string $start, string $end): void {
        if ($start > $end) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }

    private function assertValidYear(int $year): void {
        if ($year < 1900 || $year > (int) date('Y') + 10) {
            throw new InvalidArgumentException("Invalid year: {$year}");
        }
    }

    private function assertValidType(string $type): void {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Holiday type must be one of: ' . implode(', ', self::TYPES) . '.');
        }
    }

    private function roundHalfUp(string $value): string {
        return bccomp($value, '0', 4) >= 0
            ? bcadd($value, '0.005', 2)
            : bcsub($value, '0.005', 2);
    }

    private function philippineHolidays(int $year): array {
        $fixedRegular = [
            ['holiday_date' => "{$year}-01-01", 'name' => "New Year's Day",         'type' => 'regular'],
            ['holiday_date' => "{$year}-04-09", 'name' => 'Araw ng Kagitingan',      'type' => 'regular'],
            ['holiday_date' => "{$year}-05-01", 'name' => 'Labor Day',               'type' => 'regular'],
            ['holiday_date' => "{$year}-06-12", 'name' => 'Independence Day',        'type' => 'regular'],
            ['holiday_date' => "{$year}-11-30", 'name' => 'Bonifacio Day',           'type' => 'regular'],
            ['holiday_date' => "{$year}-12-25", 'name' => 'Christmas Day',           'type' => 'regular'],
            ['holiday_date' => "{$year}-12-30", 'name' => 'Rizal Day',               'type' => 'regular'],
        ];

        $fixedSpecial = [
            ['holiday_date' => "{$year}-08-21", 'name' => 'Ninoy Aquino Day',        'type' => 'special_non_working'],
            ['holiday_date' => "{$year}-11-01", 'name' => "All Saints' Day",         'type' => 'special_non_working'],
            ['holiday_date' => "{$year}-12-08", 'name' => 'Immaculate Conception',   'type' => 'special_non_working'],
            ['holiday_date' => "{$year}-12-31", 'name' => 'Last Day of the Year',    'type' => 'special_non_working'],
        ];

        return array_merge($fixedRegular, $fixedSpecial);
    }
}