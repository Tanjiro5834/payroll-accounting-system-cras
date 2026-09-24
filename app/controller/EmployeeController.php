<?php
namespace App\Controller;

use App\Entity\Employee;
use App\Helper\Response;
use App\Service\EmployeeService;

class EmployeeController
{
    private EmployeeService $service;

    public function __construct(){
        $this->service = new EmployeeService();
    }

    public function index(): void {
        Response::json(array_map([$this, 'toListItem'], $this->service->getAll()));
    }

    public function show(int $id): void {
        $employee = $this->service->getById($id) ?? Response::error('Employee not found', 404);
        Response::json($employee->toArray());
    }

    public function create(): void {

    }
    public function store(): void {

    }
    public function edit(int $id): void {

    }
    public function update(int $id): void {

    }

    public function deactivate(int $id): void {
        $this->requirePost();
        try {
            $this->service->deactivate($id);
            Response::json(['ok' => true]);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 404);
        }
    }
    
    public function reactivate(int $id): void {
        $this->requirePost();
        try {
            $this->service->reactivate($id);
            Response::json(['ok' => true]);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    private function toListItem(Employee $e): array {
        return [
            'id'      => (int) $e->getId(),
            'name'    => $e->getFullName(),
            'role'    => $e->getRole(),
            'freq'    => $e->getPayFrequency(),
            'hourly'  => (float) ($e->getHourlyRate() ?? 0),
            'monthly' => (float) ($e->getMonthlyRate() ?? 0),
            'status'  => $e->getIsActive() ? 'Active' : 'Inactive',
        ];
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
        }
    }

    public function uploadPhoto(int $id): void {

    }
    public function search(): void {

    }
}