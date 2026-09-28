<?php
namespace App\Service;

use App\Middleware\AuthMiddleware;
use App\Repository\UserRepository;
use InvalidArgumentException;

class AuthService {
    private const MIN_PASSWORD_LENGTH = 8;
    private const COOKIE_EXPIRED_OFFSET = 42000;

    private UserRepository $users;

    public function __construct(?UserRepository $users = null) {
        $this->users = $users ?? new UserRepository();
    }

    public function login(string $username, string $password): ?array {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Username and password are required.');
        }

        $user = $this->users->findByUsername($username);
        if (!$user || !(int) $user['is_active']) {
            return null;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        $this->startSession();
        AuthMiddleware::login($user);
        $this->users->updateLastLogin((int) $user['id']);

        return $this->publicUser($user);
    }

    public function logout(): void {
        $this->startSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - self::COOKIE_EXPIRED_OFFSET,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();
    }

    public function check(): bool {
        return AuthMiddleware::user() !== null;
    }

    public function user(): ?array {
        if (!$this->check()) {
            return null;
        }

        $session = AuthMiddleware::user();
        return [
            'id'          => $session['id'],
            'employee_id' => $session['employee_id'] ?? null,
            'role'        => $session['role'],
        ];
    }

    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function publicUser(array $user): array {
        unset($user['password_hash']);
        return $user;
    }
}