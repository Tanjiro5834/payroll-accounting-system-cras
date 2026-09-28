<?php
namespace App\Middleware;

use App\Helper\IpHelper;
use App\Helper\DateTimeHelper;

class AuthMiddleware
{
    private const SESSION_KEY = 'auth_user';
    private const ACTIVITY_KEY = 'auth_last_activity';
    private const IDLE_TIMEOUT = 7200;

    public function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $this->redirectIfNotAuthenticated();
            return;
        }

        $storedAgent = $_SESSION[self::SESSION_KEY]['user_agent'] ?? '';
        $currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($storedAgent !== '' && !hash_equals($storedAgent, $currentAgent)) {
            $this->logout();
            $this->redirectIfNotAuthenticated();
            return;
        }

        $last = (int) ($_SESSION[self::ACTIVITY_KEY] ?? 0);
        if ($last > 0 && (time() - $last) > self::IDLE_TIMEOUT) {
            $this->logout();
            $this->redirectIfNotAuthenticated();
            return;
        }

        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    public function redirectIfNotAuthenticated(): void
    {
        $intended = $_SERVER['REQUEST_URI'] ?? '/';
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

    public static function login(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

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

    public static function user(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json') || strtolower($xhr) === 'xmlhttprequest';
    }
}