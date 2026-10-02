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

    // GET ?page=payroll&action=index&start=Y-m-d&end=Y-m-d[&employee_id=5][&frequency=weekly]
    // With start/end: saved rows for exactly that period. Otherwise: one employee's history, or by status.
    public function index(): void {
        AuthMiddleware::requireLogin();

        $employeeId = $this->queryInt('employee_id');
        $start      = $this->queryTrim('start');
        $end        = $this->queryTrim('end');
        $status     = $this->queryTrim('status');

        $this->guard(function () use ($employeeId, $start, $end, $status) {
            if ($start !== '' && $end !== '') {
                Response::json($this->service->getPeriod($start, $end, $employeeId, $this->queryTrim('frequency')));
                return;
            }
            if ($employeeId > 0) {
                Response::json($this->service->getPayrollHistory($employeeId));
                return;
            }
            if ($status !== '') {
                Response::json($this->service->getByStatus($status));
                return;
            }
            Response::json([]);
        }, 'Failed to load payroll records.');
    }

    // POST ?page=payroll&action=compute   body: { start, end, employee_id?, frequency? }
    // Summarizes punches, computes, saves, and returns the saved rows for the period.
    public function compute(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $actorId = $this->requireSessionUser();
        $input   = $this->input();
        $start   = $this->jsonField($input, 'start');
        $end     = $this->jsonField($input, 'end');

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        $this->guard(function () use ($input, $start, $end, $actorId) {
            Response::json($this->service->computeAndSave(
                $start,
                $end,
                $actorId,
                $this->jsonInt($input, 'employee_id'),
                $this->jsonField($input, 'frequency')
            ));
        }, 'Failed to compute payroll.');
    }

    // POST ?page=payroll&action=approve&id=5   (computed → approved)
    public function approve(int $id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $this->guard(function () use ($id) {
            $this->service->approve($id, $this->sessionUserId() ?: null);
            Response::json(['ok' => true]);
        }, 'Failed to approve payroll.');
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
            if ($start !== '' && $end !== '') {
                $rows = $this->service->getPeriod($start, $end, $employeeId, $this->queryTrim('frequency'));
            } elseif ($employeeId > 0) {
                $rows = $this->service->getPayrollHistory($employeeId);
            } else {
                Response::error('Provide employee_id or a start/end range.', 422);
                return;
            }
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        $name = $start !== '' ? "payroll_{$start}_{$end}.csv" : 'payroll.csv';
        header("Content-Disposition: attachment; filename=\"{$name}\"");
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