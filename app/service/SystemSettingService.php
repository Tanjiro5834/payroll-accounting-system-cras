<?php
namespace App\Service;

use App\Entity\SystemSetting;

class SystemSettingService{
    public function __construct() {}

    public function getAll() {}
    public function getById($id) {}
    public function getByKey($key) {}

    public function getValue($key, $default = null) {}
    public function setValue($key, $value) {}
    public function exists($key) {}

    public function getMultiple(array $keys) {}
    public function setMultiple(array $settings) {}

    public function create(array $data) {}
    public function update($id, array $data) {}
    public function delete($id) {}

    public function getWorkStart() {}
    public function getWorkEnd() {}
    public function getLunchStart() {}
    public function getLunchEnd() {}
    public function getRegularHours() {}
    public function getLateThreshold() {}
    public function getWorkDays() {}
    public function getRestDay() {}

    public function loadAllToCache() {}
    public function clearCache() {}
}