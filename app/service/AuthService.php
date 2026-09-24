<?php
namespace App\Service;

class AuthService{
    private EmployeeRepository $repository;

    public function __construct(){
        $this->repository = new EmployeeRepository();
    }

    public function login(array $data) : bool {
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if($username === '' && $password === ''){
            throw new InvalidArgumentException('Please enter your username and password.');
        }

        $user = $this->repository->findByUsername($username);
        if(!$user || !password_verify($password, $user['password_hash'])) return false;

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->repository->updatePasswordHash((int) $user['id'], $newHash);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username']  = $user['username'] ?? $username;
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_at'] = time();

        return true;
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