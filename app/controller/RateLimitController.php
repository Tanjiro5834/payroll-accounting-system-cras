<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\RateLimitService;
use InvalidArgumentException;

class RateLimitController {
    private RateLimitService $service;

    public function __construct(?RateLimitService $service = null) {
        $this->service = $service ?? new RateLimitService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();

        $repository = new \App\Repository\RateLimitRepository();
        Response::json($repository->findAll());
    }

    public function reset($rateKey): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $rateKey = trim((string) $rateKey);
        if ($rateKey === '') {
            Response::error('rateKey is required.', 422);
        }

        try {
            $this->service->reset($rateKey);
            Response::json(['ok' => true]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('Failed to reset rate limit.', 500);
        }
    }

    public function resetAll(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        try {
            $deleted = $this->service->resetAll();
            Response::json(['ok' => true, 'deleted' => $deleted]);
        } catch (\Throwable $e) {
            Response::error('Failed to reset rate limits.', 500);
        }
    }

    public function cleanup(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        try {
            $deleted = $this->service->purgeExpired();
            Response::json(['ok' => true, 'deleted' => $deleted]);
        } catch (\Throwable $e) {
            Response::error('Failed to clean up rate limits.', 500);
        }
    }

    private function requireMethod(string $method): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            Response::error('Method not allowed', 405);
        }
    }
}