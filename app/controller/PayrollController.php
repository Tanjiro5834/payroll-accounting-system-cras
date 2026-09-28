<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\PayrollDeductionService;
use App\Service\PayrollService;

class PayrollController extends BaseController {
    private PayrollService $service;
    private PayrollDeductionService $deductions;

    public function __construct(
        ?PayrollService $service = null,
        ?PayrollDeductionService $deductions = null
    ) {
        $this->service    = $service    ?? new PayrollService();
        $this->deductions = $deductions ?? new PayrollDeductionService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();

        $employeeId = $this->queryInt('employee_id');
        $start      = $this->queryTrim('start');
        $end        = $this->queryTrim('end');
        $status     = $this->queryTrim('status');

        $this->guard(function () use ($employeeId, $start, $end, $status) {
            if ($employeeId > 0) {
                Response::json($this->service->getPayrollHistory($employeeId));
                return;
            }
            if ($start !== '' && $end !== '') {
                Response::json($this->service->getByDateRange($start, $end));
                return;
            }
            if ($status !== '') {
                Response::json($this->service->getByStatus($status));
                return;
            }
            Response::json([]);
        }, 'Failed to load payroll records.');
    }

    public function compute(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $actorId    = $this->requireSessionUser();
        $employeeId = $this->jsonInt($_POST, 'employee_id');
        $start      = $this->jsonField($_POST, 'start');
        $end        = $this->jsonField($_POST, 'end');

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        $this->guard(function () use ($actorId, $employeeId, $start, $end) {
            if ($employeeId > 0) {
                Response::json([
                    'ok'   => true,
                    'data' => $this->service->computeForPeriod($employeeId, $start, $end, $actorId),
                ]);
                return;
            }

            $rows = $this->service->computeForAllEmployees($start, $end, $actorId);
            Response::json(['ok' => true, 'computed' => count($rows), 'data' => $rows]);
        }, 'Failed to compute payroll.');
    }

    public function show(int $id): void {
        AuthMiddleware::requireLogin();

        $record = $this->service->getById($id);
        if ($record === null) {
            Response::error('Payroll record not found.', 404);
        }

        Response::json($record);
    }

    public function history(int $employeeId): void {
        AuthMiddleware::requireLogin();

        if ($employeeId < 1) {
            Response::error('Invalid employee ID.', 422);
        }

        Response::json($this->service->getPayrollHistory($employeeId));
    }

    public function export(): void {
        AuthMiddleware::requireLogin();

        $employeeId = $this->queryInt('employee_id');
        $start      = $this->queryTrim('start');
        $end        = $this->queryTrim('end');

        try {
            if ($employeeId > 0) {
                $rows = $this->service->getPayrollHistory($employeeId);
            } elseif ($start !== '' && $end !== '') {
                $rows = $this->service->getByDateRange($start, $end);
            } else {
                Response::error('Provide employee_id or a start/end range.', 422);
                return;
            }
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="payroll.csv"');
        echo $this->service->exportToCsv($rows);
        exit;
    }

    public function markAsPaid(int $id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        if ($id < 1) {
            Response::error('Invalid payroll ID.', 422);
        }

        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));
        if ($paidAt === '') {
            $paidAt = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))
                ->format('Y-m-d H:i:s');
        }

        $this->guard(function () use ($id, $paidAt) {
            $this->service->markAsPaid($id, $paidAt);
            Response::json(['ok' => true]);
        }, 'Failed to mark payroll as paid.');
    }

    public function thirteenthMonth(): void {
        AuthMiddleware::requireLogin();

        $year = $this->queryInt('year', (int) date('Y'));

        $this->guard(fn() => Response::json(
            (new \App\Service\ThirteenthMonthService())->generateReport($year)
        ), 'Failed to load 13th month report.');
    }

    public function thirteenthMonthExport(): void {
        AuthMiddleware::requireLogin();

        $year   = $this->queryInt('year', (int) date('Y'));
        $format = strtolower($this->queryTrim('format', 'csv'));

        $service = new \App\Service\ThirteenthMonthService();

        try {
            $report = $service->generateReport($year);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        if ($format === 'pdf') {
            try {
                $bytes = $service->exportToPdf($report);
            } catch (\RuntimeException $e) {
                Response::error($e->getMessage(), 501);
                return;
            }

            header('Content-Type: application/pdf');
            header("Content-Disposition: attachment; filename=\"13th-month-{$year}.pdf\"");
            echo $bytes;
            exit;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"13th-month-{$year}.csv\"");
        echo $service->exportToCsv($report);
        exit;
    }
}