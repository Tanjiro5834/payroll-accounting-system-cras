<?php
namespace App\Entity;

class TimePunch {
    private $id;
    public $employeeId;
    public $workDate;
    public $punchType;       // AM_IN, AM_OUT, PM_IN, PM_OUT, OT_IN, OT_OUT
    public $punchTime;
    public $ipAddress;
    public $gpsLat;
    public $gpsLng;
    public $gpsAccuracy;
    public $deviceFingerprint;
    public $isFlagged;
    public $flagReason;

    public function __construct(
        $id,
        $employeeId,
        $workDate,
        $punchType,
        $punchTime,
        $ipAddress,
        $gpsLat,
        $gpsLng,
        $gpsAccuracy,
        $deviceFingerprint,
        $isFlagged,
        $flagReason
    ) {
        $this->id = $id;
        $this->employeeId = $employeeId;
        $this->workDate = $workDate;
        $this->punchType = $punchType;
        $this->punchTime = $punchTime;
        $this->ipAddress = $ipAddress;
        $this->gpsLat = $gpsLat;
        $this->gpsLng = $gpsLng;
        $this->gpsAccuracy = $gpsAccuracy;
        $this->deviceFingerprint = $deviceFingerprint;
        $this->isFlagged = $isFlagged;
        $this->flagReason = $flagReason;
    }

    // --- Getters ---

    public function getId() {
        return $this->id;
    }

    public function getEmployeeId() {
        return $this->employeeId;
    }

    public function getWorkDate() {
        return $this->workDate;
    }

    public function getPunchType() {
        return $this->punchType;
    }

    public function getPunchTime() {
        return $this->punchTime;
    }

    public function getIpAddress() {
        return $this->ipAddress;
    }

    public function getGpsLat() {
        return $this->gpsLat;
    }

    public function getGpsLng() {
        return $this->gpsLng;
    }

    public function getGpsAccuracy() {
        return $this->gpsAccuracy;
    }

    public function getDeviceFingerprint() {
        return $this->deviceFingerprint;
    }

    public function getIsFlagged() {
        return $this->isFlagged;
    }

    public function getFlagReason() {
        return $this->flagReason;
    }

    // --- Setters ---

    public function setId($id) {
        $this->id = $id;
    }

    public function setEmployeeId($employeeId) {
        $this->employeeId = $employeeId;
    }

    public function setWorkDate($workDate) {
        $this->workDate = $workDate;
    }

    public function setPunchType($punchType) {
        $this->punchType = $punchType;
    }

    public function setPunchTime($punchTime) {
        $this->punchTime = $punchTime;
    }

    public function setIpAddress($ipAddress) {
        $this->ipAddress = $ipAddress;
    }

    public function setGpsLat($gpsLat) {
        $this->gpsLat = $gpsLat;
    }

    public function setGpsLng($gpsLng) {
        $this->gpsLng = $gpsLng;
    }

    public function setGpsAccuracy($gpsAccuracy) {
        $this->gpsAccuracy = $gpsAccuracy;
    }

    public function setDeviceFingerprint($deviceFingerprint) {
        $this->deviceFingerprint = $deviceFingerprint;
    }

    public function setIsFlagged($isFlagged) {
        $this->isFlagged = $isFlagged;
    }

    public function setFlagReason($flagReason) {
        $this->flagReason = $flagReason;
    }

    public static function fromArray(array $row): self {
        return new self(
            $row['id'] ?? null,
            (int) $row['employee_id'],
            $row['work_date'],
            $row['punch_type'],
            $row['punch_time'] ?? null,
            $row['ip_address'] ?? null,
            isset($row['gps_lat']) ? (float) $row['gps_lat'] : null,
            isset($row['gps_lng']) ? (float) $row['gps_lng'] : null,
            isset($row['gps_accuracy']) ? (int) $row['gps_accuracy'] : null,
            $row['device_fingerprint'] ?? null,
            (bool) ($row['is_flagged'] ?? false),
            $row['flag_reason'] ?? null
        );
    }
}