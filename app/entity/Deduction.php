<?php
namespace App\Entity;

class Deduction
{
    private $id;
    private $code;
    private $name;
    private $type;
    private $value;
    private $is_mandatory;
    private $is_active;
    private $created_at;
    private $updated_at;

    public function __construct(
        $id,
        $code,
        $name,
        $type,
        $value,
        $is_mandatory,
        $is_active,
        $created_at,
        $updated_at
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
        $this->is_mandatory = $is_mandatory;
        $this->is_active = $is_active;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['code'] ?? null,
            $row['name'] ?? null,
            $row['type'] ?? 'fixed',
            isset($row['value']) ? (float) $row['value'] : 0.0,
            (bool) ($row['is_mandatory'] ?? false),
            (bool) ($row['is_active'] ?? true),
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getCode() { return $this->code; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getValue() { return $this->value; }
    public function getIsMandatory() { return $this->is_mandatory; }
    public function getIsActive() { return $this->is_active; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
}