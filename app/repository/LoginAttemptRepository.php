<?php
namespace App\Repository;

use App\Entity\LoginAttempt;

class LoginAttemptRepository extends BaseRepository{
    public function create(array $data) {
        try{

        }catch(Exception $e){
            throw $e;
        }
    }

    public function findByUsername($username, $limit = 20) {}
    public function findByIpAddress($ip, $limit = 20) {}
    public function findRecent($limit = 50) {}
    public function findByDateRange($start, $end) {}

    public function countFailedAttempts($username, $since) {}
    public function countFailedAttemptsByIp($ip, $since) {}

    public function deleteOlderThan($date) {}
    public function clearForUsername($username) {}
}