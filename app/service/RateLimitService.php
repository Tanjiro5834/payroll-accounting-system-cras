<?php
namespace App\Service;

class RateLimitService{
    public function __construct() {}

    public function check($rateKey, $maxAttempts, $windowSeconds) {}
    public function hit($rateKey, $maxAttempts, $windowSeconds) {}
    public function increment($rateKey) {}
    public function reset($rateKey) {}
    public function resetAll() {}

    public function isLimited($rateKey, $maxAttempts, $windowSeconds) {}
    public function remainingAttempts($rateKey, $maxAttempts) {}
    public function retryAfter($rateKey) {}

    public function limitLogin($ip) {}
    public function limitPunch($employeeId) {}
    public function limitApi($ip, $endpoint) {}

    public function cleanup() {}
    public function purgeExpired() {}
}