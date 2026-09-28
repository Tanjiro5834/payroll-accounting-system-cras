<?php
namespace App\Controller;

use App\Helper\Response;
use App\Middleware\AuthMiddleware;
use App\Service\UserService;
use DomainException;
use InvalidArgumentException;

class UserController {
    private UserService $service;

    public function __construct(?UserService $service = null) {
        $this->service = $service ?? new UserService();
    }

    public function index(): void {
        AuthMiddleware::requireLogin();
        Response::json($this->service->getAll());
    }

    public function show($id): void {
        AuthMiddleware::requireLogin();

        $id   = (int) $id;
        $user = $id > 0 ? $this->service->getById($id) : null;
        if ($user === null) {
            Response::error('User not found.', 404);
        }

        unset($user['password_hash']);
        Response::json($user);
    }

    public function create(): void {
        AuthMiddleware::requireLogin();
        Response::json(['page' => 'user-create']);
    }

    public function store(): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $data = $this->input();

        try {
            $user = $this->service->create($data);

            Response::json([
                'message' => 'User created.',
                'data'    => $this->publicUser($user->toArray()),
            ], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to create user.', 500);
        }
    }

    public function edit($id): void {
        AuthMiddleware::requireLogin();

        $id   = (int) $id;
        $user = $id > 0 ? $this->service->getById($id) : null;
        if ($user === null) {
            Response::error('User not found.', 404);
        }

        unset($user['password_hash']);
        Response::json(['page' => 'user-edit', 'data' => $user]);
    }

    public function update($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid user ID.', 422);
        }

        try {
            $ok = $this->service->update($id, $this->input());
            Response::json(['message' => 'User updated.', 'ok' => $ok]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to update user.', 500);
        }
    }

    public function delete($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid user ID.', 422);
        }

        try {
            $this->service->delete($id);
            Response::json(['message' => 'User deleted.']);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to delete user.', 500);
        }
    }

    public function deactivate($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid user ID.', 422);
        }

        try {
            $this->service->deactivate($id);
            Response::json(['message' => 'User deactivated.']);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to deactivate user.', 500);
        }
    }

    public function reactivate($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid user ID.', 422);
        }

        try {
            $this->service->reactivate($id);
            Response::json(['message' => 'User reactivated.']);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to reactivate user.', 500);
        }
    }

    public function resetPassword($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid user ID.', 422);
        }

        $data        = $this->input();
        $newPassword = (string) ($data['password'] ?? $data['new_password'] ?? '');

        try {
            $this->service->resetPassword($id, $newPassword);
            Response::json(['message' => 'Password reset successfully.']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to reset password.', 500);
        }
    }

    public function changePassword($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid user ID.', 422);
        }

        $data    = $this->input();
        $current = (string) ($data['current_password'] ?? '');
        $new     = (string) ($data['new_password']     ?? '');

        try {
            $this->service->changePassword($id, $current, $new);
            Response::json(['message' => 'Password changed successfully.']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('Failed to change password.', 500);
        }
    }

    public function linkEmployee($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id         = (int) $id;
        $data       = $this->input();
        $employeeId = (int) ($data['employee_id'] ?? 0);

        if ($id < 1 || $employeeId < 1) {
            Response::error('User ID and employee_id are required.', 422);
        }

        try {
            $this->service->linkToEmployee($id, $employeeId);
            Response::json(['message' => 'User linked to employee.']);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Response::error('Failed to link user.', 500);
        }
    }

    public function unlinkEmployee($id): void {
        AuthMiddleware::requireLogin();
        $this->requireMethod('POST');

        $id = (int) $id;
        if ($id < 1) {
            Response::error('Invalid user ID.', 422);
        }

        try {
            $this->service->unlinkFromEmployee($id);
            Response::json(['message' => 'User unlinked from employee.']);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            Response::error('Failed to unlink user.', 500);
        }
    }

    public function usernameExists(): void {
        AuthMiddleware::requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));
        $exceptId = isset($_GET['except_id']) ? (int) $_GET['except_id'] : null;

        if ($username === '') {
            Response::error('username is required.', 422);
        }

        Response::json([
            'exists' => $this->service->usernameExists($username, $exceptId),
        ]);
    }

    public function employeeIdExists(): void {
        AuthMiddleware::requireLogin();

        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $exceptId   = isset($_GET['except_id']) ? (int) $_GET['except_id'] : null;

        if ($employeeId < 1) {
            Response::error('employee_id is required.', 422);
        }

        Response::json([
            'exists' => $this->service->employeeIdExists($employeeId, $exceptId),
        ]);
    }

    private function requireMethod(string $method): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            Response::error('Method not allowed', 405);
        }
    }

    private function input(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    private function publicUser(array $user): array {
        unset($user['password_hash']);
        return $user;
    }
}