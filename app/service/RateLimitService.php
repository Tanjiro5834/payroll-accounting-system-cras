<?php
namespace App\Service;

use App\Repository\RateLimitRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class RateLimitService {
    private const TIMEZONE = 'Asia/Manila';

    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_WINDOW       = 900;

    private const PUNCH_MAX_ATTEMPTS = 30;
    private const PUNCH_WINDOW       = 3600;

    private const API_MAX_ATTEMPTS   = 60;
    private const API_WINDOW         = 60;

    private RateLimitRepository $repository;

    public function __construct(?RateLimitRepository $repository = null) {
        $this->repository = $repository ?? new RateLimitRepository();
    }

    public function check(string $rateKey, int $maxAttempts, int $windowSeconds): bool {
        $this->validateRule($rateKey, $maxAttempts, $windowSeconds);
        return !$this->repository->isLimited($rateKey, $maxAttempts, $windowSeconds);
    }

    public function hit(string $rateKey, int $maxAttempts, int $windowSeconds): bool {
        $this->validateRule($rateKey, $maxAttempts, $windowSeconds);

        $attempts = $this->repository->increment($rateKey, $windowSeconds);

        return $attempts <= $maxAttempts;
    }

    public function increment(string $rateKey): int {
        if (trim($rateKey) === '') {
            throw new InvalidArgumentException('Rate key is required.');
        }

        return $this->repository->increment($rateKey, self::API_WINDOW);
    }

    public function reset(string $rateKey): bool {
        if (trim($rateKey) === '') {
            throw new InvalidArgumentException('Rate key is required.');
        }
        return $this->repository->reset($rateKey);
    }

    public function resetAll(): int {
        return $this->repository->resetAll();
    }

    public function isLimited(string $rateKey, int $maxAttempts, int $windowSeconds): bool {
        $this->validateRule($rateKey, $maxAttempts, $windowSeconds);
        return $this->repository->isLimited($rateKey, $maxAttempts, $windowSeconds);
    }

    public function remainingAttempts(string $rateKey, int $maxAttempts): int {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }

        $used = $this->repository->countByKey($rateKey);
        return max(0, $maxAttempts - $used);
    }

    public function retryAfter(string $rateKey): int {
        $row = $this->repository->findByKey($rateKey);
        if (!$row || $row['expires_at'] === null) {
            return 0;
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $expires = new DateTimeImmutable((string) $row['expires_at'], $tz);
        $now = new DateTimeImmutable('now', $tz);

        return max(0, $expires->getTimestamp() - $now->getTimestamp());
    }

    public function limitLogin(string $ip): bool {
        $ip = trim($ip);
        if ($ip === '') {
            throw new InvalidArgumentException('IP address is required.');
        }

        return $this->hit($this->key('login', $ip), self::LOGIN_MAX_ATTEMPTS, self::LOGIN_WINDOW);
    }

    public function limitPunch(int $employeeId): bool {
        if ($employeeId < 1) {
            throw new InvalidArgumentException('Employee ID must be a positive integer.');
        }

        return $this->hit($this->key('punch', (string) $employeeId), self::PUNCH_MAX_ATTEMPTS, self::PUNCH_WINDOW);
    }

    public function limitApi(string $ip, string $endpoint): bool {
        $ip = trim($ip);
        $endpoint = trim($endpoint);

        if ($ip === '') {
            throw new InvalidArgumentException('IP address is required.');
        }
        if ($endpoint === '') {
            throw new InvalidArgumentException('Endpoint is required.');
        }

        return $this->hit($this->key('api', $ip, $endpoint), self::API_MAX_ATTEMPTS, self::API_WINDOW);
    }

    public function cleanup(): int {
        return $this->repository->deleteExpired();
    }

    public function purgeExpired(): int {
        return $this->repository->deleteExpired();
    }

    private function key(string ...$parts): string {
        return implode(':', $parts);
    }

    private function validateRule(string $rateKey, int $maxAttempts, int $windowSeconds): void {
        if (trim($rateKey) === '') {
            throw new InvalidArgumentException('Rate key is required.');
        }
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }
        if ($windowSeconds < 1) {
            throw new InvalidArgumentException('windowSeconds must be at least 1.');
        }
    }
}