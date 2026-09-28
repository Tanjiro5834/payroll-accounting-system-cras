<?php
namespace App\Service;

use App\Helper\IpHelper;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class LocationService {
    private const TIMEZONE = 'Asia/Manila';

    private const EARTH_RADIUS_METERS = 6371000.0;

    private const DEFAULT_RADIUS_METERS = 150;

    public function __construct() {}

    public function captureFromRequest(array $request = []): array {
        $lat      = $this->toFloat($request['gps_lat']      ?? null);
        $lng      = $this->toFloat($request['gps_lng']      ?? null);
        $accuracy = isset($request['gps_accuracy']) && $request['gps_accuracy'] !== ''
            ? (int) $request['gps_accuracy']
            : null;

        if ($lat !== null && $lng !== null && !$this->validateCoordinates($lat, $lng)) {
            throw new InvalidArgumentException('Coordinates are out of range.');
        }

        return [
            'gps_lat'            => $lat,
            'gps_lng'            => $lng,
            'gps_accuracy'       => $accuracy,
            'ip_address'         => $this->getClientIp(),
            'device_fingerprint' => $this->buildDeviceFingerprint($request),
        ];
    }

    public function getClientIp(): string {
        return IpHelper::getClientIp();
    }

    public function buildDeviceFingerprint(array $data): string {
        return IpHelper::getDeviceFingerprint($data);
    }

    public function validateCoordinates(mixed $lat, mixed $lng): bool {
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

    public function calculateDistance(mixed $lat1, mixed $lng1, mixed $lat2, mixed $lng2): float {
        if (!$this->validateCoordinates($lat1, $lng1) || !$this->validateCoordinates($lat2, $lng2)) {
            throw new InvalidArgumentException('Invalid coordinates for distance calculation.');
        }

        $lat1 = deg2rad((float) $lat1);
        $lat2 = deg2rad((float) $lat2);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $lng2 - (float) $lng1);

        $a = sin($dLat / 2) ** 2
           + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    public function isWithinRadius(
        mixed $lat1,
        mixed $lng1,
        mixed $lat2,
        mixed $lng2,
        float $radiusMeters = self::DEFAULT_RADIUS_METERS
    ): bool {
        if ($radiusMeters < 0) {
            throw new InvalidArgumentException('Radius must be non-negative.');
        }

        return $this->calculateDistance($lat1, $lng1, $lat2, $lng2) <= $radiusMeters;
    }

    public function formatCoordinates(mixed $lat, mixed $lng): string {
        if (!$this->validateCoordinates($lat, $lng)) {
            return '—';
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        return sprintf(
            '%s, %s',
            $this->formatAxis($lat, 'N', 'S'),
            $this->formatAxis($lng, 'E', 'W')
        );
    }

    public function getGoogleMapsLink(mixed $lat, mixed $lng): string {
        if (!$this->validateCoordinates($lat, $lng)) {
            return '';
        }

        return sprintf('https://www.google.com/maps?q=%s,%s', (float) $lat, (float) $lng);
    }

    public function getAddressFromCoordinates(mixed $lat, mixed $lng): ?string {
        if (!$this->validateCoordinates($lat, $lng)) {
            throw new InvalidArgumentException('Invalid coordinates.');
        }

        // No geocoding provider is configured. Callers that want reverse geocoding
        // must set an API key and implement the HTTP call — see note below.
        return null;
    }

    public function recordPing(int $employeeId, mixed $lat, mixed $lng): array {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }
        if (!$this->validateCoordinates($lat, $lng)) {
            throw new InvalidArgumentException('Invalid coordinates.');
        }

        return [
            'employee_id' => $employeeId,
            'gps_lat'     => (float) $lat,
            'gps_lng'     => (float) $lng,
            'captured_at' => (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))
                ->format('Y-m-d H:i:s'),
        ];
    }

    private function formatAxis(float $value, string $positive, string $negative): string {
        $hemisphere = $value >= 0 ? $positive : $negative;
        return sprintf('%.5f° %s', abs($value), $hemisphere);
    }

    private function toFloat(mixed $value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }
}