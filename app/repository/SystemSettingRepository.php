<?php
namespace App\Repository;

use App\Entity\SystemSetting;

class SystemSettingRepository extends BaseRepository{
    public function create(array $data) {

    }
    
    public function update($id, array $data) {}
    public function delete($id) {}

    public function findById($id) {}
    public function findByKey($key) {}
    public function findAll() {}

    public function getValue($key, $default = null) {}
    public function setValue($key, $value) {}
    public function exists($key) {}

    public function getMultiple(array $keys) {}
    public function setMultiple(array $settings) {}
}