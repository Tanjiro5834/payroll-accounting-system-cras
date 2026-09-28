<?php
namespace App\Entity;

class SystemSetting
{
    private $id;
    private $setting_key;
    private $setting_value;
    private $description;
    private $updated_at;

    public function __construct(
        $id,
        $setting_key,
        $setting_value,
        $description,
        $updated_at
    ) {
        $this->id = $id;
        $this->setting_key = $setting_key;
        $this->setting_value = $setting_value;
        $this->description = $description;
        $this->updated_at = $updated_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['setting_key'] ?? null,
            $row['setting_value'] ?? null,
            $row['description'] ?? null,
            $row['updated_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getSettingKey() { return $this->setting_key; }
    public function getSettingValue() { return $this->setting_value; }
    public function getDescription() { return $this->description; }
    public function getUpdatedAt() { return $this->updated_at; }
}