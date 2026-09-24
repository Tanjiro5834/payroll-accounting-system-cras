<?php
namespace App\Repository;

use App\Config\Database;
use PDO;

class BaseRepository{
    protected PDO $db;

    public function __construct(){
        $this->db = \Database::getInstance()->getConnection();
    }
}