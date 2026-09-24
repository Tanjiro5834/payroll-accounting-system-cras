<?php
namespace App\Controller;

use App\Middleware\AuthMiddleware;
use App\Service\AuthService;

class AuthController
{
    public function showLogin(): void {}
    public function login(): void {
        $user = (new AuthService())->login($_POST['username'] ?? '', $_POST['password'] ?? '');

        if ($user === null) {
            header('Location: index.php?page=login&error=1');
            exit;
        }

        $home = $user['role'] === 'employee' ? 'punch-list' : 'dashboard';
        header("Location: index.php?page=$home");
        exit;
    }

    public function logout(): void {
        AuthMiddleware::logout();
        header('Location: index.php?page=login');
        exit;
    }
    
    public function showChangePassword(): void {}
    public function changePassword(): void {}
    public function unauthorized(): void {}
}