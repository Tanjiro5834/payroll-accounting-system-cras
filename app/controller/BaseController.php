<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use DomainException;
use InvalidArgumentException;
use Throwable;

abstract class BaseController {
    protected function requireMethod(string $method): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            Response::error('Method not allowed', 405);
        }
    }

    protected function input(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    protected function query(string $key, mixed $default = null): mixed {
        return $_GET[$key] ?? $default;
    }

    protected function queryInt(string $key, int $default = 0): int {
        $value = $_GET[$key] ?? null;
        return $value === null || $value === '' ? $default : (int) $value;
    }

    protected function queryTrim(string $key, string $default = ''): string {
        return trim((string) ($_GET[$key] ?? $default));
    }

    protected function sessionUserId(): int {
        return (int) (AuthMiddleware::user()['id'] ?? 0);
    }

    protected function sessionEmployeeId(): int {
        return (int) (AuthMiddleware::user()['employee_id'] ?? 0);
    }

    protected function requireSessionUser(): int {
        $id = $this->sessionUserId();
        if ($id < 1) {
            Response::error('Not authenticated.', 401);
        }
        return $id;
    }

    protected function requireSessionEmployee(): int {
        $id = $this->sessionEmployeeId();
        if ($id < 1) {
            Response::error('Not linked to an employee.', 401);
        }
        return $id;
    }

    /**
     * Runs $work and maps the common exception types to HTTP status codes.
     * Anything that isn't caught becomes a 500 and is logged.
     */
    protected function guard(callable $work, string $failureMessage = 'Request failed.'): void {
        try {
            $work();
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), $this->domainStatus($e->getMessage()));
        } catch (Throwable $e) {
            error_log($failureMessage . ' ' . $e->getMessage());
            Response::error($failureMessage, 500);
        }
    }

    /**
     * Same as guard() but lets the caller pick how DomainException maps
     * (e.g. deletes want 404, approve/reject want 409).
     */
    protected function guardWithStatus(
        callable $work,
        int $domainStatus,
        string $failureMessage = 'Request failed.'
    ): void {
        try {
            $work();
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), $domainStatus);
        } catch (Throwable $e) {
            error_log($failureMessage . ' ' . $e->getMessage());
            Response::error($failureMessage, 500);
        }
    }

    protected function streamCsv(string $filename, array $headers, array $rows, callable $mapper): void {
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

    protected function jsonField(array $data, string $key, string $default = ''): string {
        return trim((string) ($data[$key] ?? $default));
    }

    protected function jsonInt(array $data, string $key, int $default = 0): int {
        return (int) ($data[$key] ?? $default);
    }

    protected function domainStatus(string $message): int {
        $needle = strtolower($message);
        return str_contains($needle, 'not found') ? 404 : 409;
    }
}