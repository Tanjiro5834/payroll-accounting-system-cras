<?php
namespace App\Entity;

class LoginAttempt
{
    private $id;
    private $username;
    private $ip_address;
    private $success;
    private $attempted_at;

    public function __construct(
        $id,
        $username,
        $ip_address,
        $success,
        $attempted_at
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->ip_address = $ip_address;
        $this->success = $success;
        $this->attempted_at = $attempted_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['username'] ?? null,
            $row['ip_address'] ?? null,
            (bool) ($row['success'] ?? false),
            $row['attempted_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getIpAddress() { return $this->ip_address; }
    public function getSuccess() { return $this->success; }
    public function getAttemptedAt() { return $this->attempted_at; }
}