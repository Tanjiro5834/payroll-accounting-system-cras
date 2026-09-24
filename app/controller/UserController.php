<?php
namespace App\Controller;

use App\Service\UserService;
use InvalidArgumentException;
use DomainException;
use RuntimeException;

class UserController
{
    private UserService $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /users
     * List all users.
     */
    public function index(): void
    {
        try {
            $users = $this->service->getAll();
            $this->json(['data' => $users], 200);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/{id}
     * Show a single user.
     */
    public function show($id): void
    {
        try {
            $user = $this->service->getById((int) $id);

            if ($user === null) {
                $this->json(['error' => 'User not found.'], 404);
                return;
            }

            $this->json(['data' => $user], 200);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/create
     * Show the create form (or return an empty template).
     */
    public function create(): void
    {
        // If you have a view layer, render it here.
        // Example: $this->render('user/create');
        $this->json(['message' => 'Create user form'], 200);
    }

    /**
     * POST /users
     * Store a new user.
     */
    public function store(): void
    {
        try {
            $data = $this->input();

            $user = $this->service->create($data);

            $this->json(['message' => 'User created.', 'data' => $user], 201);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /users/{id}/edit
     * Show the edit form.
     */
    public function edit($id): void
    {
        try {
            $user = $this->service->getById((int) $id);

            if ($user === null) {
                $this->json(['error' => 'User not found.'], 404);
                return;
            }

            $this->json(['data' => $user], 200);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT/PATCH /users/{id}
     * Update an existing user.
     */
    public function update($id): void
    {
        try {
            $data = $this->input();

            $user = $this->service->update((int) $id, $data);

            $this->json(['message' => 'User updated.', 'data' => $user], 200);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /users/{id}
     * Delete a user.
     */
    public function delete($id): void
    {
        try {
            $this->service->delete((int) $id);
            $this->json(['message' => 'User deleted.'], 200);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /users/{id}/deactivate
     * Deactivate a user.
     */
    public function deactivate($id): void
    {
        try {
            $this->service->deactivate((int) $id);
            $this->json(['message' => 'User deactivated.'], 200);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /users/{id}/reactivate
     * Reactivate a user.
     */
    public function reactivate($id): void
    {
        try {
            $this->service->reactivate((int) $id);
            $this->json(['message' => 'User reactivated.'], 200);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /users/{id}/reset-password
     * Reset a user's password (admin action).
     */
    public function resetPassword($id): void
    {
        try {
            $data = $this->input();
            $newPassword = trim($data['password'] ?? '');

            if ($newPassword === '') {
                $this->json(['error' => 'New password is required.'], 422);
                return;
            }

            $this->service->resetPassword((int) $id, $newPassword);

            $this->json(['message' => 'Password reset successfully.'], 200);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /users/{id}/change-password
     * Change a user's password (user action, requires current password).
     */
    public function changePassword($id): void
    {
        try {
            $data = $this->input();
            $current     = trim($data['current_password'] ?? '');
            $newPassword = trim($data['new_password'] ?? '');

            if ($current === '' || $newPassword === '') {
                $this->json(['error' => 'Current and new passwords are required.'], 422);
                return;
            }

            $this->service->changePassword((int) $id, $current, $newPassword);

            $this->json(['message' => 'Password changed successfully.'], 200);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /users/{id}/link-employee
     * Link a user to an employee record.
     */
    public function linkEmployee($id): void
    {
        try {
            $data = $this->input();
            $employeeId = $data['employee_id'] ?? null;

            if ($employeeId === null || $employeeId === '') {
                $this->json(['error' => 'employee_id is required.'], 422);
                return;
            }

            $this->service->linkToEmployee((int) $id, (int) $employeeId);

            $this->json(['message' => 'User linked to employee.'], 200);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /users/{id}/unlink-employee
     * Unlink a user from their employee record.
     */
    public function unlinkEmployee($id): void
    {
        try {
            $this->service->unlinkFromEmployee((int) $id);
            $this->json(['message' => 'User unlinked from employee.'], 200);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ------------------------------------------------------------------
    // Helpers — replace these with your framework's request/response.
    // ------------------------------------------------------------------

    /**
     * Retrieve input data from the request.
     * Adjust to use your framework's request object.
     */
    protected function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (is_array($json)) {
            return $json;
        }

        return array_merge($_GET, $_POST);
    }

    /**
     * Send a JSON response.
     */
    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}