<?php
namespace App\Controller;

use App\Entity\Employee;
use App\Helper\Response;
use App\Service\EmployeeService;
use DomainException;
use InvalidArgumentException;

class EmployeeController {
    private EmployeeService $service;

    public function __construct(?EmployeeService $service = null) {
        $this->service = $service ?? new EmployeeService();
    }

    public function index(): void {
        $items = array_map([$this, 'toListItem'], $this->service->getAll());
        Response::json($items);
    }

    public function show(int $id): void {
        $employee = $this->service->getById($id);
        if ($employee === null) {
            Response::error('Employee not found', 404);
        }

        Response::json($employee->toArray());
    }

    public function create(): void {
        Response::json(['page' => 'employee-create']);
    }

    public function store(): void {
        $this->requireMethod('POST');

        try {
            $id = $this->service->create($this->input());
            Response::json(['ok' => true, 'id' => $id], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to create employee.', 500);
        }
    }

    public function edit(int $id): void {
        $employee = $this->service->getById($id);
        if ($employee === null) {
            Response::error('Employee not found', 404);
        }

        Response::json(['page' => 'employee-edit', 'data' => $employee->toArray()]);
    }

    public function update(int $id): void {
        $this->requireMethod('POST');

        try {
            $ok = $this->service->update($id, $this->input());
            Response::json(['ok' => $ok]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to update employee.', 500);
        }
    }

    public function deactivate(int $id): void {
        $this->requireMethod('POST');

        try {
            $this->service->deactivate($id);
            Response::json(['ok' => true]);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to deactivate employee.', 500);
        }
    }

    public function reactivate(int $id): void {
        $this->requireMethod('POST');

        try {
            $this->service->reactivate($id);
            Response::json(['ok' => true]);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to reactivate employee.', 500);
        }
    }

    public function uploadPhoto(int $id): void {
        $this->requireMethod('POST');

        if (!isset($_FILES['photo'])) {
            Response::error('No file uploaded.', 422);
        }

        try {
            $url = $this->service->uploadProfilePhoto($id, $_FILES['photo']);
            Response::json(['ok' => true, 'url' => $url]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to upload photo.', 500);
        }
    }

    public function search(): void {
        $query = trim((string) ($_GET['q'] ?? ''));
        Response::json(array_map([$this, 'toListItem'], $this->service->search($query)));
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

    private function requireMethod(string $method): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            Response::error('Method not allowed', 405);
        }
    }

    private function input(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }
}