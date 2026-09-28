<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\SystemSettingService;

class SystemSettingController extends BaseController {
    private SystemSettingService $service;

    public function __construct(?SystemSettingService $service = null) {
        $this->service = $service ?? new SystemSettingService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();
        Response::json($this->service->getAll());
    }

    public function show($id): void {
        AuthMiddleware::requireLogin();

        $id      = (int) $id;
        $setting = $id > 0 ? $this->service->getById($id) : null;
        if ($setting === null) {
            Response::error('Setting not found.', 404);
        }

        Response::json($setting);
    }

    public function edit($id): void {
        AuthMiddleware::requireLogin();

        $id      = (int) $id;
        $setting = $id > 0 ? $this->service->getById($id) : null;
        if ($setting === null) {
            Response::error('Setting not found.', 404);
        }

        Response::json(['page' => 'setting-edit', 'data' => $setting]);
    }

    public function update($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid setting ID.', 422);
        }

        $this->guard(function () use ($id) {
            $ok = $this->service->update($id, $this->input());
            Response::json(['ok' => $ok]);
        }, 'Failed to update setting.');
    }

    public function updateMultiple(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $this->guard(function () {
            $count = $this->service->setMultiple($this->input());
            Response::json(['ok' => true, 'updated' => $count]);
        }, 'Failed to update settings.');
    }

    public function resetDefaults(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $this->guard(function () {
            $count = $this->service->resetDefaults();
            Response::json(['ok' => true, 'updated' => $count]);
        }, 'Failed to reset settings.');
    }

    public function export(): void {
        AuthMiddleware::requireLogin();

        $rows = $this->service->getAll();

        $this->streamCsv('system-settings.csv', ['Key', 'Value', 'Description', 'Updated At'], $rows, fn(array $r) => [
            $r['setting_key']   ?? '',
            $r['setting_value'] ?? '',
            $r['description']   ?? '',
            $r['updated_at']    ?? '',
        ]);
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

        $handle = fopen((string) $file['tmp_name'], 'r');
        if ($handle === false) {
            Response::error('Failed to read file.', 422);
        }

        $imported = 0;
        $errors   = [];
        $line     = 0;

        try {
            fgetcsv($handle); // skip header
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if (count($row) < 2) {
                    $errors[] = "Line {$line}: expected at least 2 columns.";
                    continue;
                }

                $key   = trim((string) $row[0]);
                $value = (string) $row[1];

                if ($key === '') {
                    $errors[] = "Line {$line}: empty key.";
                    continue;
                }

                try {
                    $this->service->setValue($key, $value);
                    $imported++;
                } catch (\InvalidArgumentException | \DomainException $e) {
                    $errors[] = "Line {$line}: {$e->getMessage()}";
                }
            }
        } finally {
            fclose($handle);
        }

        Response::json(['imported' => $imported, 'errors' => $errors]);
    }
}