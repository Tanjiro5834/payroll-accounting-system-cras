<?php
namespace App\Service;

use DomainException;

class OfficeGeofenceService {
    public const OFFICE_LAT   = 'office_lat';
    public const OFFICE_LNG   = 'office_lng';
    public const RADIUS_M     = 'office_radius_m';
    public const MAX_ACCURACY = 'gps_max_accuracy_m';

    private const DEFAULT_RADIUS_M     = 100;
    private const DEFAULT_MAX_ACCURACY = 100;

    private SystemSettingService $settings;
    private LocationService $location;

    public function __construct(?SystemSettingService $settings = null, ?LocationService $location = null) {
        $this->settings = $settings ?? new SystemSettingService();
        $this->location = $location ?? new LocationService();
    }

    public function isEnabled(): bool {
        return $this->office() !== null;
    }

    // Throws if the punch is not from the office. Returns the distance in meters when it is.
    public function assertInsideOffice(?float $lat, ?float $lng, ?int $accuracy): ?int {
        $office = $this->office();
        if ($office === null) {
            return null;
        }

        if ($lat === null || $lng === null || !$this->location->validateCoordinates($lat, $lng)) {
            throw new DomainException('Location is required to punch. Turn on location and allow it for this site, then try again.');
        }

        $maxAccuracy = $this->intSetting(self::MAX_ACCURACY, self::DEFAULT_MAX_ACCURACY);
        if ($accuracy === null || $accuracy > $maxAccuracy) {
            $shown = $accuracy === null ? '' : " (±{$accuracy}m)";
            throw new DomainException("Location is not accurate enough{$shown}. Go outside or near a window and try again.");
        }

        $radius   = $this->intSetting(self::RADIUS_M, self::DEFAULT_RADIUS_M);
        $distance = (int) round($this->location->calculateDistance($lat, $lng, $office[0], $office[1]));

        if ($distance > $radius) {
            throw new DomainException("You are {$distance}m away from the office. You can only punch at the office.");
        }

        return $distance;
    }

    private function office(): ?array {
        $lat = $this->settings->getValue(self::OFFICE_LAT);
        $lng = $this->settings->getValue(self::OFFICE_LNG);

        if (!is_numeric($lat) || !is_numeric($lng) || !$this->location->validateCoordinates($lat, $lng)) return null;
        return [(float) $lat, (float) $lng];
    }

    private function intSetting(string $key, int $default): int {
        $value = $this->settings->getValue($key);
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}