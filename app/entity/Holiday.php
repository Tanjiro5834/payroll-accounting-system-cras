<?php
namespace App\Entity;

class Holiday
{
    private $id;
    private $holiday_date;
    private $name;
    private $type;
    private $created_at;

    public function __construct(
        $id,
        $holiday_date,
        $name,
        $type,
        $created_at
    ) {
        $this->id = $id;
        $this->holiday_date = $holiday_date;
        $this->name = $name;
        $this->type = $type;
        $this->created_at = $created_at;
    }

    public static function fromArray(array $row): self {
        return new self(
            $row['id'] ?? null,
            $row['holiday_date'] ?? null,
            $row['name'] ?? null,
            $row['type'] ?? 'regular',
            $row['created_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getHolidayDate() { return $this->holiday_date; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getCreatedAt() { return $this->created_at; }

    public function toArray(): array {
        return [
            'id'           => $this->id !== null ? (int) $this->id : null,
            'holiday_date' => $this->holiday_date,
            'name'         => $this->name,
            'type'         => $this->type,
            'created_at'   => $this->created_at,
        ];
    }
}