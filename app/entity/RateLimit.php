<?php
namespace App\Entity;

class RateLimit
{
    private $id;
    private $rate_key;
    private $attempts;
    private $window_start;
    private $expires_at;

    public function __construct(
        $id,
        $rate_key,
        $attempts,
        $window_start,
        $expires_at
    ) {
        $this->id = $id;
        $this->rate_key = $rate_key;
        $this->attempts = $attempts;
        $this->window_start = $window_start;
        $this->expires_at = $expires_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['rate_key'] ?? null,
            isset($row['attempts']) ? (int) $row['attempts'] : 0,
            $row['window_start'] ?? null,
            $row['expires_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getRateKey() { return $this->rate_key; }
    public function getAttempts() { return $this->attempts; }
    public function getWindowStart() { return $this->window_start; }
    public function getExpiresAt() { return $this->expires_at; }

    // Helpers
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return strtotime($this->expires_at) <= time();
    }
}