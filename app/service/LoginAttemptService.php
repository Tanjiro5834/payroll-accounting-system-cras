<?php
namespace App\Service;

class LoginAttemptService{
    public function __construct() {}

    public function record($username, $ip, $success) {}
    public function recordSuccess($username, $ip) {}
    public function recordFailure($username, $ip) {}

    public function getRecentAttempts($limit = 50) {}
    public function getByUsername($username, $limit = 20) {}
    public function getByIp($ip, $limit = 20) {}
    public function getByDateRange($start, $end) {}

    public function countFailedAttempts($username, $minutes = 15) {}
    public function countFailedAttemptsByIp($ip, $minutes = 15) {}

    public function isLockedOut($username, $ip) {}
    public function clearAttempts($username) {}
    public function purgeOlderThan($days = 30) {}
}