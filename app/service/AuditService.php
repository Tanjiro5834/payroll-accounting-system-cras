<?php
namespace App\Service;

use App\Middleware\AuthMiddleware;
use App\Repository\AuditTrailRepository;
use InvalidArgumentException;
use Throwable;

// Write side of the audit trail. Reading/filtering lives in TimeAuditService.
//   employee_id = the employee the action was ABOUT
//   user_id     = the logged-in user who DID it (NULL for kiosk/system)
class AuditService {
    // Government IDs: record that they changed, never the values.
    private const REDACTED_FIELDS = ['sss_number', 'philhealth_number', 'pagibig_number', 'tin_number'];

    private AuditTrailRepository $repository;

    public function __construct(?AuditTrailRepository $repository = null) {
        $this->repository = $repository ?? new AuditTrailRepository();
    }

    // Best-effort: a failed audit write goes to the PHP error log and never breaks the action that triggered it.
    public function record(string $action, ?int $employeeId, array $details = []): void {
        try {
            $this->repository->log(
                $employeeId,
                $this->normalizeAction($action),
                $details ?: null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $this->actorId()
            );
        } catch (Throwable $e) {
            error_log("[audit] {$action} failed: " . $e->getMessage());
        }
    }

    // Field-level changes: ['field' => ['from' => x, 'to' => y]]. Only fields present in both snapshots are compared.
    public static function diff(array $before, array $after, array $ignore = []): array {
        $changes = [];
        foreach ($after as $field => $to) {
            if (in_array($field, $ignore, true) || !array_key_exists($field, $before)) {
                continue;
            }
            $from = $before[$field];
            if (self::same($from, $to)) {
                continue;
            }
            $changes[$field] = in_array($field, self::REDACTED_FIELDS, true)
                ? ['changed' => true]
                : ['from' => $from, 'to' => $to];
        }
        return $changes;
    }

    // '500' == '500.00', true == 1, null == ''
    private static function same(mixed $a, mixed $b): bool {
        if (is_numeric($a) && is_numeric($b)) {
            return bccomp((string) $a, (string) $b, 4) === 0;
        }
        return (string) $a === (string) $b;
    }

    private function actorId(): ?int {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null; // CLI / scheduled jobs
        }
        $user = AuthMiddleware::user();
        return isset($user['id']) ? (int) $user['id'] : null;
    }

    private function normalizeAction(string $action): string {
        $action = strtoupper(trim($action));
        if ($action === '' || strlen($action) > 50) {
            throw new InvalidArgumentException('Audit action must be 1-50 characters.');
        }
        return $action;
    }
}
