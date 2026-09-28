<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\AuthService;
use InvalidArgumentException;

class AuthController {
    private AuthService $service;

    public function __construct(?AuthService $service = null) {
        $this->service = $service ?? new AuthService();
    }

    public function showLogin(): void {
        Response::json(['page' => 'login']);
    }

    public function login(): void {
        try {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            $user = $this->service->login($username, $password);

            if ($user === null) {
                $this->redirect('index.php?page=login&error=1');
                return;
            }

            $home = $user['role'] === 'employee' ? 'punch-employee-list' : 'dashboard';
            $this->redirect("index.php?page={$home}");
        } catch (InvalidArgumentException $e) {
            $this->redirect('index.php?page=login&error=1');
        }
    }

    public function logout(): void {
        $this->service->logout();
        $this->redirect('index.php?page=login');
    }

    public function showChangePassword(): void {
        AuthMiddleware::requireLogin();
        Response::json(['page' => 'change-password']);
    }

    public function changePassword(): void {
        AuthMiddleware::requireLogin();

        $userId = (int) (AuthMiddleware::user()['id'] ?? 0);
        if ($userId < 1) {
            Response::error('Not authenticated.', 401);
        }

        $current     = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');

        if ($current === '' || $newPassword === '') {
            Response::error('Current and new passwords are required.', 422);
        }

        try {
            $service = new \App\Service\UserService();
            $service->changePassword($userId, $current, $newPassword);

            Response::json(['message' => 'Password changed.']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('Failed to change password.', 500);
        }
    }

    public function unauthorized(): void {
        Response::error('Unauthorized.', 401);
    }

    private function redirect(string $location): void {
        header("Location: {$location}");
        exit;
    }
}