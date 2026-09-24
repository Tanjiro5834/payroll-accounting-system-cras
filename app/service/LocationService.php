<?php
namespace App\Service;

class LocationService{
    public function __construct() {}
    public function captureFromRequest(): array {}
    public function getClientIp(): string {}
    public function buildDeviceFingerprint(array $data): string {}
    public function validateCoordinates(?float $lat, ?float $lng): bool {}
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {}
    public function isWithinRadius(float $lat1, float $lng1, float $lat2, float $lng2, int $radiusMeters): bool {}
    public function formatCoordinates(?float $lat, ?float $lng): ?string {}
    public function getGoogleMapsLink(?float $lat, ?float $lng): ?string {}
    public function getAddressFromCoordinates(float $lat, float $lng): ?string {}
}