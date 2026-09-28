<?php
namespace App\Controller;

use App\Entity\Employee;
use App\Helper\Response;
use App\Service\EmployeeService;

class EmployeeController extends BaseController {
    private EmployeeService $service;

    public function __construct(?EmployeeService $service = null) {
        $this->service = $service ?? new EmployeeService();
    }

    // GET ?page=employees&action=index
    public function index(): void {
        $this->guard(
            fn() => Response::json(array_map([$this, 'toListItem'], $this->service->getAll())),
            'Failed to load employees.'
        );
    }

    // GET ?page=employees&action=search&q=juan
    public function search(): void {
        $this->guard(
            fn() => Response::json(array_map([$this, 'toListItem'], $this->service->search($this->queryTrim('q')))),
            'Failed to search employees.'
        );
    }

    // GET ?page=employees&action=show&id=5 — full record for the edit form
    public function show(int $id): void {
        $employee = $this->service->getById($id) ?? Response::error('Employee not found.', 404);
        Response::json($employee->toArray());
    }

    // POST ?page=employees&action=store   body: JSON employee fields
    public function store(): void {
        $this->requireMethod('POST');
        $input = $this->input();

        $this->guard(function () use ($input) {
            Response::json(['ok' => true, 'id' => $this->service->create($input)], 201);
        }, 'Failed to create employee.');
    }

    // POST ?page=employees&action=update&id=5   body: only the fields to change
    public function update(int $id): void {
        $this->requireMethod('POST');
        $input = $this->input();

        $this->guard(function () use ($id, $input) {
            $this->service->update($id, $input);
            Response::json(['ok' => true, 'id' => $id]);
        }, 'Failed to update employee.');
    }

    // POST ?page=employees&action=deactivate&id=5
    public function deactivate(int $id): void {
        $this->requireMethod('POST');
        $this->guard(function () use ($id) {
            $this->service->deactivate($id);
            Response::json(['ok' => true]);
        }, 'Failed to deactivate employee.');
    }

    // POST ?page=employees&action=reactivate&id=5
    public function reactivate(int $id): void {
        $this->requireMethod('POST');
        $this->guard(function () use ($id) {
            $this->service->reactivate($id);
            Response::json(['ok' => true]);
        }, 'Failed to reactivate employee.');
    }

    // POST ?page=employees&action=uploadPhoto&id=5   multipart field: photo
    public function uploadPhoto(int $id): void {
        $this->requireMethod('POST');
        if (!isset($_FILES['photo'])) {
            Response::error('No file uploaded.', 422);
        }

        $this->guard(function () use ($id) {
            Response::json(['ok' => true, 'url' => $this->service->uploadProfilePhoto($id, $_FILES['photo'])]);
        }, 'Failed to upload photo.');
    }

    // POST ?page=employees&action=removePhoto&id=5
    public function removePhoto(int $id): void {
        $this->requireMethod('POST');
        $this->guard(function () use ($id) {
            $this->service->deleteProfilePhoto($id);
            Response::json(['ok' => true]);
        }, 'Failed to remove photo.');
    }

    // List view: no government IDs.
    private function toListItem(Employee $e): array {
        return [
            'id'      => (int) $e->getId(),
            'name'    => $e->getFullName(),
            'role'    => $e->getRole(),
            'photo'   => $e->getProfilePhotoUrl(),
            'freq'    => $e->getPayFrequency(),
            'hourly'  => (float) ($e->getHourlyRate() ?? 0),
            'monthly' => (float) ($e->getMonthlyRate() ?? 0),
            'hired'   => $e->getDateHired(),
            'status'  => $e->getIsActive() ? 'Active' : 'Inactive',
        ];
    }
}