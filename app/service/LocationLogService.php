<?php
namespace App\Service;

class LocationLogService{
    public function __construct() {}

    public function getByEmployee($employeeId, $start, $end) {}
    public function getByDate($date) {}
    public function getByDateRange($start, $end) {}

    public function getCoordinatesForPunch($punchId) {}
    public function getGoogleMapsLink($lat, $lng) {}
    public function formatCoordinates($lat, $lng) {}

    public function isWithinRadius($lat1, $lng1, $lat2, $lng2, $radiusMeters) {}
    public function calculateDistance($lat1, $lng1, $lat2, $lng2) {}

    public function flagSuspiciousLocation($punchId, $expectedLat, $expectedLng, $radius) {}

    public function getMovementHistory($employeeId, $date) {}
    public function getDistanceTraveled($employeeId, $date) {}
}