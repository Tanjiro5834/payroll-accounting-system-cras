<?php
namespace App\Repository;

use App\Config\Database;
use PDO;

class BaseRepository{
    protected PDO $db;

    public function __construct(){
        $this->db = \Database::getInstance()->getConnection();
    }

    public function transaction(callable $work): mixed{
        if ($this->db->inTransaction()) {
            return $work(); // already inside one; join it
        }

        $this->db->beginTransaction();
        try {
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}