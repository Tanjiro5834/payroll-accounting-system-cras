<?php
namespace App\Entity;

class AuditTrail{
    private $id;
    private $employeeId;
    public $actionType;
    public $actionDetails;
    public $ipAddress;
    public $performedAt;
}