<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\LoginAttemptService;
use InvalidArgumentException;

class LoginAttemptController {
    private LoginAttemptService $service;

    public function __construct(?LoginAttemptService $service = null) {
        $this->service = $service ?? new LoginAttemptService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();

        $limit = (int) ($_GET['limit'] ?? 50);
        Response::json($this->service->getRecentAttempts($limit));
    }

    public function byUsername(string $username): void {
        AuthMiddleware::requireLogin();

        try {
            $limit = (int) ($_GET['limit'] ?? 20);
            Response::json($this->service->getByUsername($username, $limit));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load attempts.', 500);
        }
    }

    public function byIp(string $ip): void {
        AuthMiddleware::requireLogin();

        try {
            $limit = (int) ($_GET['limit'] ?? 20);
            Response::json($this->service->getByIp($ip, $limit));
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to load attempts.', 500);
        }
    }

    public function recent(): void {
        AuthMiddleware::requireLogin();

        $limit = (int) ($_GET['limit'] ?? 50);
        Response::json($this->service->getRecentAttempts($limit));
    }

    public function export(): void {
        AuthMiddleware::requireLogin();

        $start = trim((string) ($_GET['start'] ?? ''));
        $end   = trim((string) ($_GET['end']   ?? ''));

        if ($start === '' || $end === '') {
            Response::error('start and end are required.', 422);
        }

        try {
            $rows = $this->service->getByDateRange($start, $end);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="login-attempts.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

        fputcsv($out, ['ID', 'Username', 'IP', 'Success', 'Attempted At']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id']           ?? '',
                $row['username']     ?? '',
                $row['ip_address']   ?? '',
                ((int) ($row['success'] ?? 0)) === 1 ? 'yes' : 'no',
                $row['attempted_at'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }
}