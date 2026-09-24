<?php
namespace App\Service;

use App\Middleware\AuthMiddleware;
use App\Repository\UserRepository;

class AuthService{
    private UserRepository $users;

    public function __construct(){
        $this->users = new UserRepository();
    }

    public function login(string $username, string $password): ?array{
        $username = trim($username);
        if ($username === '' || $password === '') return null;

        $user = $this->users->findByUsername($username);
        if (!$user || !(int) $user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        AuthMiddleware::login($user);
        $this->users->updateLastLogin((int) $user['id']);
        return $user;
    }
    public function logout() : void {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    public function check() : bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function user() : ?array {
        if (!$this->check()) return null;
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
        ];
    }
}