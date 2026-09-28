<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Repository\DailySummaryRepository;
use App\Service\FlaggingService;
use App\Service\LocationLogService;
use App\Service\TimeAuditService;
use InvalidArgumentException;

class ReportController {
    private TimeAuditService $audit;
    private LocationLogService $location;
    private FlaggingService $flags;
    private DailySummaryRepository $dailySummary;

    public function __construct(
        ?TimeAuditService $audit = null,
        ?LocationLogService $location = null,
        ?FlaggingService $flags = null,
        ?DailySummaryRepository $dailySummary = null
    ) {
        $this->audit        = $audit        ?? new TimeAuditService();
        $this->location     = $location     ?? new LocationLogService();
        $this->flags        = $flags        ?? new FlaggingService();
        $this->dailySummary = $dailySummary ?? new DailySummaryRepository();
    }

    public function auditLog(): void {
        AuthMiddleware::requireLogin();

        $start      = trim((string) ($_GET['start'] ?? ''));
        $end        = trim((string) ($_GET['end']   ?? ''));
        $employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : null;

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        try {
            Response::json($this->audit->getAuditTrail($start, $end, $employeeId));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load audit report.', 500);
        }
    }

    public function auditLogExport(): void {
        AuthMiddleware::requireLogin();

        $start      = trim((string) ($_GET['start'] ?? ''));
        $end        = trim((string) ($_GET['end']   ?? ''));
        $employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : null;

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        try {
            $rows = $this->audit->getAuditTrail($start, $end, $employeeId);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        $this->streamCsv("audit-log-{$start}-to-{$end}.csv", [
            'ID', 'Employee ID', 'Name', 'Action', 'Details', 'IP', 'Performed At',
        ], $rows, fn(array $r) => [
            $r['id']           ?? '',
            $r['employee_id']  ?? '',
            $r['full_name']    ?? '',
            $r['action_type']  ?? '',
            $this->flatten($r['action_details'] ?? ''),
            $r['ip_address']   ?? '',
            $r['performed_at'] ?? '',
        ]);
    }

    public function locationLog(): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date       = trim((string) ($_GET['date'] ?? ''));
        $start      = trim((string) ($_GET['start'] ?? ''));
        $end        = trim((string) ($_GET['end']   ?? ''));

        try {
            if ($employeeId > 0 && $start !== '' && $end !== '') {
                Response::json($this->location->getByEmployee($employeeId, $start, $end));
                return;
            }

            if ($date !== '') {
                Response::json($this->location->getByDate($date));
                return;
            }

            if ($start !== '' && $end !== '') {
                Response::json($this->location->getByDateRange($start, $end));
                return;
            }

            Response::error('Provide employee_id + range, or date, or a range.', 422);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load location report.', 500);
        }
    }

    public function locationLogExport(): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date       = trim((string) ($_GET['date'] ?? ''));

        try {
            if ($employeeId > 0 && $date !== '') {
                $rows = $this->location->getMovementHistory($employeeId, $date)['points'];
            } elseif ($employeeId > 0) {
                $start = trim((string) ($_GET['start'] ?? ''));
                $end   = trim((string) ($_GET['end']   ?? ''));
                if ($start === '' || $end === '') {
                    Response::error('start and end are required when date is not provided.', 422);
                    return;
                }
                $rows = $this->location->getByEmployee($employeeId, $start, $end);
            } elseif ($date !== '') {
                $rows = $this->location->getByDate($date);
            } else {
                Response::error('Provide employee_id + date, employee_id + range, or date.', 422);
                return;
            }
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        $this->streamCsv('location-log.csv', [
            'Punch ID', 'Employee ID', 'Work Date', 'Punch Type', 'Punch Time',
            'Latitude', 'Longitude', 'Accuracy (m)',
        ], $rows, fn(array $r) => [
            $r['id']          ?? $r['punch_id']   ?? '',
            $r['employee_id'] ?? '',
            $r['work_date']   ?? $r['punch_time'] ?? '',
            $r['punch_type']  ?? '',
            $r['punch_time']  ?? '',
            $r['gps_lat']     ?? '',
            $r['gps_lng']     ?? '',
            $r['accuracy']    ?? $r['gps_accuracy'] ?? '',
        ]);
    }

    public function flaggedPunches(): void {
        AuthMiddleware::requireLogin();

        $date = trim((string) ($_GET['date'] ?? ''));

        try {
            Response::json($this->flags->getAllFlags($date !== '' ? $date : null));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load flagged punches.', 500);
        }
    }

    public function flaggedPunchesExport(): void {
        AuthMiddleware::requireLogin();

        $date = trim((string) ($_GET['date'] ?? ''));

        try {
            $rows = $this->flags->getAllFlags($date !== '' ? $date : null);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        $this->streamCsv('flagged-punches.csv', [
            'ID', 'Employee ID', 'Name', 'Work Date', 'Punch Type', 'Punch Time',
            'IP', 'Flag Reason', 'Reviewed By', 'Reviewed At',
        ], $rows, fn(array $r) => [
            $r['id']            ?? '',
            $r['employee_id']   ?? '',
            $r['full_name']     ?? '',
            $r['work_date']     ?? '',
            $r['punch_type']    ?? '',
            $r['punch_time']    ?? '',
            $r['ip_address']    ?? '',
            $r['flag_reason']   ?? '',
            $r['reviewed_by']   ?? '',
            $r['reviewed_at']   ?? '',
        ]);
    }

    public function dailySummary(): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date       = trim((string) ($_GET['date'] ?? ''));

        try {
            if ($employeeId > 0 && $date !== '') {
                Response::json($this->dailySummary->findByEmployeeAndDate($employeeId, $date));
                return;
            }

            if ($date !== '') {
                Response::json($this->dailySummary->findByDate($date));
                return;
            }

            Response::error('Provide date, or employee_id + date.', 422);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load daily summary.', 500);
        }
    }

    public function monthlySummary(): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $year       = (int) ($_GET['year'] ?? date('Y'));

        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }

        try {
            Response::json($this->dailySummary->countMonthsWorked($employeeId, $year));
        } catch (\Throwable $e) {
            Response::error('Failed to load monthly summary.', 500);
        }
    }

    private function flatten(mixed $details): string {
        if (is_string($details)) {
            return $details;
        }
        if (is_array($details)) {
            return json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }
        return '';
    }

    private function streamCsv(string $filename, array $headers, array $rows, callable $mapper): void {
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $mapper($row));
        }

        fclose($out);
        exit;
    }
}