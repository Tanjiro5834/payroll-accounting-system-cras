<?php
namespace App\Entity;

class Employee {
    
    private $id;
    private $full_name;
    private $role;
    private $profile_photo_url;
    private $sss_number;
    private $philhealth_number;
    private $pagibig_number;
    private $tin_number;
    private $hourly_rate;
    private $monthly_rate;
    private $pay_frequency;
    private $date_hired;
    private $is_active;

    public function __construct(
        $id, 
        $full_name, 
        $role, 
        $profile_photo_url, 
        $sss_number, 
        $philhealth_number, 
        $pagibig_number, 
        $tin_number, 
        $hourly_rate, 
        $monthly_rate, 
        $pay_frequency, 
        $date_hired, 
        $is_active
    ) {
        $this->id = $id;
        $this->full_name = $full_name;
        $this->role = $role;
        $this->profile_photo_url = $profile_photo_url;
        $this->sss_number = $sss_number;
        $this->philhealth_number = $philhealth_number;
        $this->pagibig_number = $pagibig_number;
        $this->tin_number = $tin_number;
        $this->hourly_rate = $hourly_rate;
        $this->monthly_rate = $monthly_rate;
        $this->pay_frequency = $pay_frequency;
        $this->date_hired = $date_hired;
        $this->is_active = $is_active;
    }

     public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['full_name'],
            $row['role'],
            $row['profile_photo_url'] ?? null,
            $row['sss_number'] ?? null,
            $row['philhealth_number'] ?? null,
            $row['pagibig_number'] ?? null,
            $row['tin_number'] ?? null,
            isset($row['hourly_rate']) ? (float) $row['hourly_rate'] : null,
            isset($row['monthly_rate']) ? (float) $row['monthly_rate'] : null,
            $row['pay_frequency'] ?? null,
            $row['date_hired'] ?? null,
            (bool) ($row['is_active'] ?? true)
        );
    }

    // Getters

    public function getId() {
        return $this->id;
    }

    public function getFullName() {
        return $this->full_name;
    }

    public function getRole() {
        return $this->role;
    }

    public function getProfilePhotoUrl() {
        return $this->profile_photo_url;
    }

    public function getSssNumber() {
        return $this->sss_number;
    }

    public function getPhilhealthNumber() {
        return $this->philhealth_number;
    }

    public function getPagibigNumber() {
        return $this->pagibig_number;
    }

    public function getTinNumber() {
        return $this->tin_number;
    }

    public function getHourlyRate() {
        return $this->hourly_rate;
    }

    public function getMonthlyRate() {
        return $this->monthly_rate;
    }

    public function getPayFrequency() {
        return $this->pay_frequency;
    }

    public function getDateHired() {
        return $this->date_hired;
    }

    public function getIsActive() {
        return $this->is_active;
    }
}