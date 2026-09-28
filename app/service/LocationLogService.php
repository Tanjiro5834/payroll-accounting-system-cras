<?php
namespace App\Service;

use App\Repository\TimePunchRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class LocationLogService {
    private const TIMEZONE = 'Asia/Manila';

    private TimePunchRepository $repository;

    public function __construct(?TimePunchRepository $repository = null) {
        $this->repository = $repository ?? new TimePunchRepository();
    }

    public function getByEmployee(int $employeeId, string $start, string $end): array {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
        $this->assertDateRange($start, $end);

        $punches = $this->repository->findByEmployeeAndDateRange($employeeId, $start, $end);

        return $this->projectWithLocation($punches);
    }

    public function getByDate(string $date): array {
        $this->assertDate($date, 'date');

        return $this->projectWithLocation($this->repository->findByDate($date));
    }

    public function getByDateRange(string $start, string $end): array {
        $this->assertDateRange($start, $end);

        return $this->projectWithLocation($this->repository->findByDateRange($start, $end));
    }

    public function getCoordinatesForPunch(int $punchId): ?array {
        if ($punchId < 1) {
            throw new InvalidArgumentException('Punch ID must be a positive integer.');
        }

        $punch = $this->repository->findById($punchId);
        if (!$punch) {
            return null;
        }

        $lat = $punch->getGpsLat();
        $lng = $punch->getGpsLng();

        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'punch_id'    => $punchId,
            'employee_id' => $punch->getEmployeeId(),
            'gps_lat'     => (float) $lat,
            'gps_lng'     => (float) $lng,
            'accuracy'    => $punch->getGpsAccuracy() !== null ? (int) $punch->getGpsAccuracy() : null,
            'punch_time'  => $punch->getPunchTime(),
        ];
    }

    public function getGoogleMapsLink(mixed $lat, mixed $lng): string {
        if (!$this->isValidCoordinatePair($lat, $lng)) {
            return '';
        }

        return sprintf('https://www.google.com/maps?q=%s,%s', (float) $lat, (float) $lng);
    }

    public function formatCoordinates(mixed $lat, mixed $lng): string {
        if (!$this->isValidCoordinatePair($lat, $lng)) {
            return '—';
        }

        return sprintf('%.5f, %.5f', (float) $lat, (float) $lng);
    }

    public function isWithinRadius(
        mixed $lat1, mixed $lng1,
        mixed $lat2, mixed $lng2,
        float $radiusMeters
    ): bool {
        if ($radiusMeters < 0) {
            throw new InvalidArgumentException('Radius must be non-negative.');
        }

        return $this->calculateDistance($lat1, $lng1, $lat2, $lng2) <= $radiusMeters;
    }

    public function calculateDistance(mixed $lat1, mixed $lng1, mixed $lat2, mixed $lng2): float {
        if (!$this->isValidCoordinatePair($lat1, $lng1) || !$this->isValidCoordinatePair($lat2, $lng2)) {
            throw new InvalidArgumentException('Invalid coordinates for distance calculation.');
        }

        $lat1 = deg2rad((float) $lat1);
        $lat2 = deg2rad((float) $lat2);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $lng2 - (float) $lng1);

        $a = sin($dLat / 2) ** 2
           + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6371000.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function flagSuspiciousLocation(
        int $punchId,
        mixed $expectedLat,
        mixed $expectedLng,
        float $radiusMeters
    ): bool {
        $coords = $this->getCoordinatesForPunch($punchId);
        if ($coords === null) {
            return false;
        }

        if (!$this->isValidCoordinatePair($expectedLat, $expectedLng)) {
            throw new InvalidArgumentException('Expected coordinates are invalid.');
        }

        $distance = $this->calculateDistance(
            $coords['gps_lat'],
            $coords['gps_lng'],
            $expectedLat,
            $expectedLng
        );

        if ($distance <= $radiusMeters) {
            return false;
        }

        $reason = sprintf('Punch recorded %.0f m from expected location.', $distance);

        return $this->repository->flagById($punchId, $reason);
    }

    public function getMovementHistory(int $employeeId, string $date): array {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
        $this->assertDate($date, 'date');

        $punches = $this->repository->findByEmployeeAndDate($employeeId, $date);

        $points = [];
        foreach ($punches as $p) {
            $lat = $p->getGpsLat();
            $lng = $p->getGpsLng();

            if ($lat === null || $lng === null) {
                continue;
            }

            $points[] = [
                'punch_id'   => $p->getId(),
                'punch_type' => $p->getPunchType(),
                'punch_time' => $p->getPunchTime(),
                'gps_lat'    => (float) $lat,
                'gps_lng'    => (float) $lng,
                'accuracy'   => $p->getGpsAccuracy() !== null ? (int) $p->getGpsAccuracy() : null,
            ];
        }

        return [
            'employee_id' => $employeeId,
            'date'        => $date,
            'points'      => $points,
            'total'       => count($points),
        ];
    }

    public function getDistanceTraveled(int $employeeId, string $date): float {
        $history = $this->getMovementHistory($employeeId, $date);
        $points  = $history['points'];

        if (count($points) < 2) {
            return 0.0;
        }

        $total = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $total += $this->calculateDistance(
                $points[$i - 1]['gps_lat'],
                $points[$i - 1]['gps_lng'],
                $points[$i]['gps_lat'],
                $points[$i]['gps_lng']
            );
        }

        return round($total, 2);
    }

    private function projectWithLocation(array $punches): array
    {
        $rows = [];
        foreach ($punches as $p) {
            $lat = $p->getGpsLat();
            $lng = $p->getGpsLng();

            $rows[] = [
                'id'           => $p->getId(),
                'employee_id'  => $p->getEmployeeId(),
                'work_date'    => $p->getWorkDate(),
                'punch_type'   => $p->getPunchType(),
                'punch_time'   => $p->getPunchTime(),
                'gps_lat'      => $lat !== null ? (float) $lat : null,
                'gps_lng'      => $lng !== null ? (float) $lng : null,
                'accuracy'     => $p->getGpsAccuracy() !== null ? (int) $p->getGpsAccuracy() : null,
                'has_location' => $lat !== null && $lng !== null,
            ];
        }
        return $rows;
    }

    private function isValidCoordinatePair(mixed $lat, mixed $lng): bool {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }

        return $lat >= -90.0 && $lat <= 90.0
            && $lng >= -180.0 && $lng <= 180.0;
    }

    private function assertDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }

    private function assertDateRange(string $start, string $end): void {
        $this->assertDate($start, 'start');
        $this->assertDate($end, 'end');

        if ($start > $end) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }
}