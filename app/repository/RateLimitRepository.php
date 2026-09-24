<?php
namespace App\Repository;

class RateLimitRepository extends BaseRepository{
    public function create(array $data) {

    }
    
    public function update($id, array $data) {}
    public function delete($id) {}

    public function findByKey($rateKey) {}
    public function findAll() {}

    public function increment($rateKey) {}
    public function reset($rateKey) {}
    public function resetAll() {}

    public function isLimited($rateKey, $maxAttempts, $windowSeconds) {}

    public function deleteExpired() {}
    public function countByKey($rateKey) {}
}