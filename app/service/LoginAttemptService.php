<?php
namespace App\Service;

use App\Entity\LoginAttempt;
use App\Helper\DateTimeHelper;
use App\Helper\IpHelper;
use App\Repository\LoginAttemptRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class LoginAttemptService {
    private const TIMEZONE = 'Asia/Manila';

    private const DEFAULT_WINDOW_MINUTES = 15;
    private const DEFAULT_LOCKOUT_THRESHOLD = 5;
    private const DEFAULT_PURGE_DAYS = 30;

    private LoginAttemptRepository $repository;

    public function __construct(?LoginAttemptRepository $repository = null) {
        $this->repository = $repository ?? new LoginAttemptRepository();
    }

    public function record(string $username, string $ip, bool $success): bool {
        $username = trim($username);
        $ip       = trim($ip);

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        if ($ip === '' || !IpHelper::isValid($ip)) {
            throw new InvalidArgumentException('A valid IP address is required.');
        }

        $attempt = LoginAttempt::fromArray([
            'username'     => $username,
            'ip_address'   => $ip,
            'success'      => $success ? 1 : 0,
            'attempted_at' => DateTimeHelper::now(),
        ]);

        return $this->repository->create($attempt);
    }

    public function recordSuccess(string $username, string $ip): bool {
        $recorded = $this->record($username, $ip, true);

        if ($recorded) {
            $this->clearAttempts($username);
        }

        return $recorded;
    }

    public function recordFailure(string $username, string $ip): bool {
        return $this->record($username, $ip, false);
    }

    public function getRecentAttempts(int $limit = 50): array {
        return $this->repository->findRecent($limit);
    }

    public function getByUsername(string $username, int $limit = 20): array {
        $username = trim($username);
        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        return $this->repository->findByUsername($username, $limit);
    }

    public function getByIp(string $ip, int $limit = 20): array {
        $ip = trim($ip);
        if ($ip === '' || !IpHelper::isValid($ip)) {
            throw new InvalidArgumentException('A valid IP address is required.');
        }

        return $this->repository->findByIpAddress($ip, $limit);
    }

    public function getByDateRange(string $start, string $end): array {
        $this->assertValidDate($start, 'start');
        $this->assertValidDate($end, 'end');
        $this->assertDateOrder($start, $end);

        return $this->repository->findByDateRange($start, $end);
    }

    public function countFailedAttempts(string $username, int $minutes = self::DEFAULT_WINDOW_MINUTES): int {
        $username = trim($username);
        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        return $this->repository->countFailedAttempts($username, $this->minutesAgo($minutes));
    }

    public function countFailedAttemptsByIp(string $ip, int $minutes = self::DEFAULT_WINDOW_MINUTES): int {
        $ip = trim($ip);
        if ($ip === '' || !IpHelper::isValid($ip)) {
            throw new InvalidArgumentException('A valid IP address is required.');
        }

        return $this->repository->countFailedAttemptsByIp($ip, $this->minutesAgo($minutes));
    }

    public function isLockedOut(
        string $username,
        string $ip,
        int $threshold = self::DEFAULT_LOCKOUT_THRESHOLD,
        int $windowMinutes = self::DEFAULT_WINDOW_MINUTES
    ): bool {
        $username = trim($username);
        $ip       = trim($ip);

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        if ($ip === '' || !IpHelper::isValid($ip)) {
            throw new InvalidArgumentException('A valid IP address is required.');
        }

        $since = $this->minutesAgo($windowMinutes);

        return $this->repository->isUsernameLockedOut($username, $threshold, $since)
            || $this->repository->isIpLockedOut($ip, $threshold, $since);
    }

    public function clearAttempts(string $username): int {
        $username = trim($username);
        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        return $this->repository->clearForUsername($username);
    }

    public function clearAttemptsForIp(string $ip): int {
        $ip = trim($ip);
        if ($ip === '' || !IpHelper::isValid($ip)) {
            throw new InvalidArgumentException('A valid IP address is required.');
        }

        return $this->repository->clearForIp($ip);
    }

    public function purgeOlderThan(int $days = self::DEFAULT_PURGE_DAYS): int {
        if ($days < 1) {
            throw new InvalidArgumentException('Days must be a positive integer.');
        }

        return $this->repository->deleteOlderThan(
            DateTimeHelper::minutesAgo($days * 24 * 60)
        );
    }

    private function minutesAgo(int $minutes): string {
        if ($minutes < 1) {
            throw new InvalidArgumentException('Minutes must be a positive integer.');
        }

        return DateTimeHelper::minutesAgo($minutes);
    }

    private function assertValidDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }

    private function assertDateOrder(string $start, string $end): void {
        if ($start > $end) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }
}