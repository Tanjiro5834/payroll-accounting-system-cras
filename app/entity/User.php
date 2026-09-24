<?php
namespace App\Entity;

class User
{
    private $id;
    private $username;
    private $password_hash;
    private $role;
    private $employee_id;
    private $last_login;
    private $is_active;
    private $created_at;
    private $updated_at;

    public function __construct(
        $id,
        $username,
        $password_hash,
        $role,
        $employee_id,
        $last_login,
        $is_active,
        $created_at,
        $updated_at
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->password_hash = $password_hash;
        $this->role = $role;
        $this->employee_id = $employee_id;
        $this->last_login = $last_login;
        $this->is_active = $is_active;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['username'] ?? null,
            $row['password_hash'] ?? null,
            $row['role'] ?? null,
            $row['employee_id'] ?? null,
            $row['last_login'] ?? null,
            (bool) ($row['is_active'] ?? true),
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getPasswordHash() { return $this->password_hash; }
    public function getRole() { return $this->role; }
    public function getEmployeeId() { return $this->employee_id; }
    public function getLastLogin() { return $this->last_login; }
    public function getIsActive() { return $this->is_active; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
}