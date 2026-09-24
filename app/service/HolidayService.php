<?php
namespace App\Service;

use App\Entity\Holiday;
use App\Repository\HolidayRepository;

class HolidayService{
    private HolidayRepository $repository;

    public function __construct() {
        $this->repository = new HolidayRepository();
    }

    public function getAll() {
        return $this->repository->findAll();
    }

    public function getById($id) {
        return $this->repository->findById($id);
    }

    public function getByDate($date) {
        return $this->repository->findByDate($date);
    }

    public function getByYear($year) {
        return $this->repository->findByYear($year);
    }

    public function getByType($type) {
        return $this->repository->findByType($type);
    }

    public function getByDateRange($start, $end) {
        $startDate = assertValidDate($start, 'start');
        $endDate = assertValidDate($end, 'end');

        if($startDate > $endDate) throw new Exception('Start date must be before or equal to end date.');
        return $this->repository->findByDateRange($startDate, $endDate);
    }

    public function create(array $data) {
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            throw new Exception("Validation failed: " . implode(', ', $errors));
        }

        $holiday = Holiday::fromArray($data);
        return $this->repository->create($holiday);
    }

    public function update(int $id, array $data) {
        $holiday = $this->getById($id);
        if (!$holiday) {
            throw new Exception("Holiday not found");
        }

        $errors = $this->validateData($data);
        if (!empty($errors)) {
            throw new Exception("Validation failed: " . implode(', ', $errors));
        }

        return $this->repository->update($id, $data) > 0;
    }

    public function delete($id) {
        $holiday = $this->getId($id);
        if(!$holiday) throw new Exception("Holiday not found");

        return $this->repository->delete($id);
    }

    public function isHoliday($date) {
        return $this->repository->isHoliday($date) !== null;
    }

    public function getHolidayType($date) {
        return $this->repository->getHolidayType($date) !== null;
    }

    public function getHolidayName($date) {
        $holiday = $this->repository->findByDate($date);
        return $holiday['name'] ?? null;
    }

    public function isRegularHoliday($date) {
        return $this->getHolidayType($date) === 'regular';
    }

    public function isSpecialNonWorking($date) {
        return $this->getHolidayType($date) === 'special_non_working';
    }

    public function isSpecialWorking($date) {
        return $this->getHolidayType($date) === 'special_working';
    }

    public function computeHolidayPremium(string $date, float $hours, float $hourlyRate) {
        $type = $this->getHolidayType($date);
        $multiplier = match ($type) {
            'regular' => 2.00,
            'special_non_working' => 1.30,
            default => 1.00,
        };

        $base = $hours * $hourlyRate;
        $total = round($base * $multiplier, 2);
        $premium = round($total - $base, 2);

        return [
            'type'       => $type,
            'multiplier' => $multiplier,
            'premium'    => $premium,
            'total'      => $total,
        ];
    }

    public function seedPhilippineHolidays($year) {
        $holidays = array_merge(
            $this->fixedRegularHolidays($year),
            $this->fixedSpecialHolidays($year)
        );

        $inserted = 0;
        foreach ($holidays as $holiday) {
            if ($this->repository->isHoliday($holiday['holiday_date'])) {
                continue;
            }
            $this->repository->create($holiday);
            $inserted++;
        }

        return $inserted;
    }

    public function importFromCsv($file) {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        if (!is_readable($file)) {
            throw new Exception("CSV file is not readable: {$file}");
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            throw new Exception("Failed to open CSV: {$file}");
        }

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
                    'holiday_date' => trim($row[0]),
                    'name' => trim($row[1]),
                    'type' => trim($row[2]),
                ];

                $errors = $this->validateData($data);
                if (!empty($errors)) {
                    $result['errors'][] = "Line {$line}: " . implode(' ', $errors);
                    $result['skipped']++;
                    continue;
                }

                if ($this->repository->isHoliday($data['holiday_date'])) {
                    $result['errors'][] = "Line {$line}: duplicate date {$data['holiday_date']}.";
                    $result['skipped']++;
                    continue;
                }

                $this->repository->create($data);
                $result['imported']++;
            }
        } finally {
            fclose($handle);
        }

        return $result;
    }

    private function validateData(array $data, bool $isUpdate = false): array {
        $errors = [];
        if (!$isUpdate || array_key_exists('full_name', $data)) {
            $name = trim((string) ($data['full_name'] ?? ''));
            if ($name === '') {
                $errors['full_name'] = 'Full name is required.';
            } elseif (mb_strlen($name) > 150) {
                $errors['full_name'] = 'Full name is too long (max 150 characters).';
            }
        }

        if (!$isUpdate || array_key_exists('role', $data)) {
            $role = $data['role'] ?? '';
            if (!in_array($role, ['owner', 'admin', 'employee'], true)) {
                $errors['role'] = 'Role must be owner, admin, or employee.';
            }
        }

        if (!$isUpdate || array_key_exists('pay_frequency', $data)) {
            $freq = $data['pay_frequency'] ?? '';
            if (!in_array($freq, ['hourly', 'daily', 'weekly', 'semi_monthly', 'monthly'], true)) {
                $errors['pay_frequency'] = 'Invalid pay frequency.';
            }
        }

        if (!$isUpdate || array_key_exists('date_hired', $data)) {
            $date = trim((string) ($data['date_hired'] ?? ''));
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if (!$dt || $dt->format('Y-m-d') !== $date) {
                $errors['date_hired'] = 'Date hired must be a valid date (YYYY-MM-DD).';
            } elseif ($dt > new \DateTimeImmutable('today')) {
                $errors['date_hired'] = 'Date hired cannot be in the future.';
            }
        }

        // Optional rates — at least one required on create.
        foreach (['hourly_rate', 'monthly_rate'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                if (!is_numeric($data[$field]) || (float) $data[$field] < 0) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a positive number.';
                }
            }
        }

        if (!$isUpdate
            && empty($data['hourly_rate'])
            && empty($data['monthly_rate'])) {
            $errors['hourly_rate'] = 'Provide an hourly or monthly rate.';
        }

        // Optional government IDs — validate format if provided.
        if (!empty($data['sss_number']) && strlen(preg_replace('/\D/', '', $data['sss_number'])) !== 10) {
            $errors['sss_number'] = 'SSS number must be 10 digits.';
        }
        if (!empty($data['philhealth_number']) && strlen(preg_replace('/\D/', '', $data['philhealth_number'])) !== 12) {
            $errors['philhealth_number'] = 'PhilHealth number must be 12 digits.';
        }
        if (!empty($data['pagibig_number']) && strlen(preg_replace('/\D/', '', $data['pagibig_number'])) !== 12) {
            $errors['pagibig_number'] = 'Pag-IBIG number must be 12 digits.';
        }
        if (!empty($data['tin_number']) && !in_array(strlen(preg_replace('/\D/', '', $data['tin_number'])), [9, 12], true)) {
            $errors['tin_number'] = 'TIN must be 9 or 12 digits.';
        }

        // Optional URL.
        if (!empty($data['profile_photo_url']) && filter_var($data['profile_photo_url'], FILTER_VALIDATE_URL) === false) {
            $errors['profile_photo_url'] = 'Profile photo URL is invalid.';
        }

        return $errors;
    }

    private function assertValidDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new Exception("Invalid date for {$field}: {$date}");
        }
    }
}