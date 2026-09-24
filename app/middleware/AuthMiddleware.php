<?php
namespace App\Middleware;

use App\Helper\IpHelper;
use App\Helper\DateTimeHelper;

class AuthMiddleware
{
    /**
     * Session key that stores the authenticated user record.
     */
    private const SESSION_KEY = 'auth_user';

    /**
     * Session key that stores the last activity timestamp (for idle timeout).
     */
    private const ACTIVITY_KEY = 'auth_last_activity';

    /**
     * Idle timeout in seconds (default 2 hours).
     */
    private const IDLE_TIMEOUT = 7200;

    /**
     * Run the auth check for the current request. Halts the request
     * (redirect) if the user is not authenticated or the session is stale.
     */
    public function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // 1. Must have an authenticated user in the session.
        if (empty($_SESSION[self::SESSION_KEY])) {
            $this->redirectIfNotAuthenticated();
            return;
        }

        // 2. Session fixation / hijack mitigation: bind to user agent.
        $storedAgent = $_SESSION[self::SESSION_KEY]['user_agent'] ?? '';
        $currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($storedAgent !== '' && !hash_equals($storedAgent, $currentAgent)) {
            $this->logout();
            $this->redirectIfNotAuthenticated();
            return;
        }

        // 3. Idle timeout.
        $last = (int) ($_SESSION[self::ACTIVITY_KEY] ?? 0);
        if ($last > 0 && (time() - $last) > self::IDLE_TIMEOUT) {
            $this->logout();
            $this->redirectIfNotAuthenticated();
            return;
        }

        // 4. Refresh activity timestamp.
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    /**
     * Redirect unauthenticated users to the login page, preserving the
     * intended URL so they land back where they wanted after logging in.
     */
    public function redirectIfNotAuthenticated(): void
    {
        $intended = $_SERVER['REQUEST_URI'] ?? '/';
        // Only store same-origin paths — avoid open-redirect.
        if (!str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            $intended = '/';
        }
        $_SESSION['intended_url'] = $intended;

        if (self::wantsJson()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthenticated']);
        } else {
            header('Location: index.php?page=login'); 
        }
        exit;
    }

    /**
     * Authenticate a user and initialize the session.
     * Call this from your login controller.
     */
    public static function login(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Prevent session fixation — issue a new session ID on login.
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'         => (int) $user['id'],
            'role'       => (string) ($user['role'] ?? 'employee'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip'         => IpHelper::getClientIp(),
            'login_at'   => DateTimeHelper::now(),
        ];
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    /**
     * Clear the current session and destroy its cookie.
     */
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }

    /**
     * Get the currently authenticated user (or null).
     */
    public static function user(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Whether the current request expects a JSON response.
     */
    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest';
    }
}