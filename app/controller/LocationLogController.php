<?php
namespace App\Controller;

class LocationLogController
{
    public function index() {}
    public function byEmployee($employeeId) {}
    public function byDate($date) {}
    public function byDateRange($start, $end) {}

    public function show($punchId) {}
    public function map($employeeId, $date) {}
    public function movementHistory($employeeId, $date) {}

    public function export() {}
}