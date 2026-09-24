<?php
namespace App\Middleware;

class RoleMiddleware
{
    /**
     * Role hierarchy — higher number means more privileges.
     * Adding roles here automatically works with hasRole() and require*().
     */
    private const HIERARCHY = [
        'employee' => 1,
        'admin'    => 2,
        'owner'    => 3,
    ];

    /**
     * The specific role required by the current route.
     * Set by one of the require*() methods before handle() runs.
     */
    private ?string $requiredRole = null;

    /**
     * Enforce the configured role requirement.
     * Call this after (or from within) a require*() method.
     */
    public function handle(): void
    {
        if ($this->requiredRole === null) {
            // No requirement configured — route is public from this
            // middleware's perspective. AuthMiddleware (if present)
            // is responsible for the login gate.
            return;
        }

        if (!$this->hasRole($this->requiredRole)) {
            $this->deny();
        }
    }

    /**
     * Require the highest privilege role — typically the business owner.
     */
    public function requireOwner(): void
    {
        $this->requiredRole = 'owner';
        $this->handle();
    }

    /**
     * Require at least admin-level access (admin or owner).
     */
    public function requireAdmin(): void
    {
        $this->requiredRole = 'admin';
        $this->handle();
    }

    /**
     * Require at least employee-level access (any authenticated user).
     */
    public function requireEmployee(): void
    {
        $this->requiredRole = 'employee';
        $this->handle();
    }

    /**
     * Check whether the currently authenticated user meets the given role.
     * Uses the hierarchy so 'admin' satisfies an 'employee' requirement.
     */
    public function hasRole(string $role): bool
    {
        $userRole = $this->currentUserRole();
        if ($userRole === null) {
            return false;
        }

        $userLevel     = self::HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::HIERARCHY[$role] ?? PHP_INT_MAX;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Emit a 403 response and stop the request.
     */
    private function deny(): void
    {
        http_response_code(403);

        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>403 — Forbidden</h1>';
        }

        exit;
    }

    /**
     * Read the authenticated user's role from the session.
     * Returns null when unauthenticated or when role is missing/blank.
     */
    private function currentUserRole(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $role = $_SESSION['auth_user']['role'] ?? null;

        if (!is_string($role) || $role === '') {
            return null;
        }

        return $role;
    }

    /**
     * Whether the client expects a JSON response.
     */
    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest';
    }
}