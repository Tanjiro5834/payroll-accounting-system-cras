<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\HolidayService;

class HolidayController extends BaseController {
    private HolidayService $service;

    public function __construct(?HolidayService $service = null) {
        $this->service = $service ?? new HolidayService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();
        $this->guard(fn() => Response::json($this->service->getAll()));
    }

    public function show($id): void {
        AuthMiddleware::requireLogin();

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid holiday ID.', 422);
        }

        $holiday = $this->service->getById($id);
        if ($holiday === null) {
            Response::error('Holiday not found.', 404);
        }

        Response::json($holiday->toArray());
    }

    public function create(): void {
        AuthMiddleware::requireLogin();
        Response::json(['page' => 'holiday-create']);
    }

    public function store(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $this->guard(function () {
            $id = $this->service->create($this->input());
            Response::json(['ok' => true, 'id' => $id], 201);
        }, 'Failed to create holiday.');
    }

    public function edit($id): void {
        AuthMiddleware::requireLogin();

        $id      = (int) $id;
        $holiday = $id > 0 ? $this->service->getById($id) : null;
        if ($holiday === null) {
            Response::error('Holiday not found.', 404);
        }

        Response::json(['page' => 'holiday-edit', 'data' => $holiday->toArray()]);
    }

    public function update($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid holiday ID.', 422);
        }

        $this->guard(function () use ($id) {
            $ok = $this->service->update($id, $this->input());
            Response::json(['ok' => $ok]);
        }, 'Failed to update holiday.');
    }

    public function delete($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid holiday ID.', 422);
        }

        $this->guardWithStatus(function () use ($id) {
            $this->service->delete($id);
            Response::json(['ok' => true]);
        }, 404, 'Failed to delete holiday.');
    }

    public function listByYear($year): void {
        AuthMiddleware::requireLogin();

        $year = (int) $year;
        if ($year < 1900 || $year > (int) date('Y') + 10) {
            Response::error('Invalid year.', 422);
        }

        $this->guard(fn() => Response::json($this->service->getByYear($year)));
    }

    public function import(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded.', 422);
        }

        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('Upload failed.', 422);
        }

        $this->guard(fn() => Response::json(
            $this->service->importFromCsv((string) $file['tmp_name'])
        ), 'Failed to import holidays.');
    }

    public function export(): void {
        AuthMiddleware::requireLogin();

        $year = $this->queryInt('year', (int) date('Y'));
        if ($year < 1900 || $year > (int) date('Y') + 10) {
            Response::error('Invalid year.', 422);
        }

        try {
            $rows = $this->service->getByYear($year);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        $this->streamCsv("holidays-{$year}.csv", ['Date', 'Name', 'Type'], $rows, fn(array $r) => [
            $r['holiday_date'] ?? '',
            $r['name']         ?? '',
            $r['type']         ?? '',
        ]);
    }
}