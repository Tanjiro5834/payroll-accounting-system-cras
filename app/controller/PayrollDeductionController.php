<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\PayrollDeductionService;
use DomainException;
use InvalidArgumentException;

class PayrollDeductionController {
    private PayrollDeductionService $service;

    public function __construct(?PayrollDeductionService $service = null) {
        $this->service = $service ?? new PayrollDeductionService();
    }

    public function index($payrollPeriodId): void {
        AuthMiddleware::requireLogin();

        $payrollPeriodId = (int) $payrollPeriodId;
        if ($payrollPeriodId < 1) {
            Response::error('Invalid payroll period ID.', 422);
        }

        try {
            Response::json($this->service->getByPayrollPeriod($payrollPeriodId));
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to load deductions.', 500);
        }
    }

    public function show($id): void {
        AuthMiddleware::requireLogin();

        $id  = (int) $id;
        $row = $id > 0 ? $this->service->getById($id) : null;
        if ($row === null) {
            Response::error('Payroll deduction not found.', 404);
        }

        Response::json($row);
    }

    public function store(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $payrollPeriodId = (int) ($_POST['payroll_period_id'] ?? 0);
        $deductionId     = (int) ($_POST['deduction_id'] ?? 0);
        $amount          = (string) ($_POST['amount'] ?? '');

        if ($payrollPeriodId < 1 || $deductionId < 1) {
            Response::error('payroll_period_id and deduction_id are required.', 422);
        }

        try {
            $id = $this->service->create($payrollPeriodId, $deductionId, $amount);
            Response::json(['ok' => true, 'id' => $id], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to create payroll deduction.', 500);
        }
    }

    public function update($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id     = (int) $id;
        $amount = (string) ($_POST['amount'] ?? '');

        if ($id < 1) {
            Response::error('Invalid payroll deduction ID.', 422);
        }

        try {
            $ok = $this->service->update($id, $amount);
            Response::json(['ok' => $ok]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to update payroll deduction.', 500);
        }
    }

    public function delete($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid payroll deduction ID.', 422);
        }

        try {
            $this->service->delete($id);
            Response::json(['ok' => true]);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to delete payroll deduction.', 500);
        }
    }

    public function bulkStore(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $payrollPeriodId = (int) ($_POST['payroll_period_id'] ?? 0);
        $rows            = $_POST['rows'] ?? null;

        if ($payrollPeriodId < 1) {
            Response::error('payroll_period_id is required.', 422);
        }
        if (!is_array($rows) || empty($rows)) {
            Response::error('rows must be a non-empty array.', 422);
        }

        try {
            $count = $this->service->applyDeductionsToPayroll($payrollPeriodId, $rows);
            Response::json(['ok' => true, 'applied' => $count]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to apply deductions.', 500);
        }
    }

    public function recompute($payrollPeriodId): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $payrollPeriodId = (int) $payrollPeriodId;
        if ($payrollPeriodId < 1) {
            Response::error('Invalid payroll period ID.', 422);
        }

        $employeeId  = (int) ($_POST['employee_id']  ?? 0);
        $grossPay    = (string) ($_POST['gross_pay'] ?? '0');
        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $periodEnd   = trim((string) ($_POST['period_end']   ?? ''));

        if ($employeeId < 1 || $periodStart === '' || $periodEnd === '') {
            Response::error('employee_id, period_start, and period_end are required.', 422);
        }

        try {
            $computed = $this->service->computeFromCatalog(
                $employeeId,
                $grossPay,
                $periodStart,
                $periodEnd
            );

            $applied = $this->service->snapshotForPayroll($payrollPeriodId, $computed);

            Response::json([
                'ok'       => true,
                'applied'  => $applied,
                'computed' => $computed,
            ]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to recompute deductions.', 500);
        }
    }

    private function requireMethod(string $method): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            Response::error('Method not allowed', 405);
        }
    }
}