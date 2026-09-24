<?php
namespace App\Middleware;

class CsrfMiddleware
{
    private const SESSION_KEY = 'csrf_token';
    private const FORM_FIELD  = '_token';
    private const HEADER_NAME = 'HTTP_X_CSRF_TOKEN';

    /**
     * 64 hex chars = 32 bytes of entropy. That's the recommendation
     * from OWASP; anything less shortens the search space needlessly.
     */
    private const TOKEN_BYTES = 32;

    /**
     * Enforce CSRF protection on the current request.
     * Safe methods (GET, HEAD, OPTIONS) are exempt — they must not
     * mutate state (that's the contract that makes CSRF protection work).
     */
    public function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = $this->extractToken();
        if (!$this->validateToken($token)) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token mismatch']);
            exit;
        }
    }

    /**
     * Get the current session's CSRF token, generating one if missing.
     */
    public function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_BYTES));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Constant-time validation of a submitted token.
     */
    public function validateToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $expected = $this->getToken();
        if ($expected === '') {
            return false;
        }

        // hash_equals() prevents timing side-channels on the comparison.
        return hash_equals($expected, $token);
    }

    /**
     * Public accessor — same token, but named for use in views.
     */
    public function getToken(): string
    {
        return $this->generateToken();
    }

    /**
     * Extract the submitted token from header or form body.
     * Header wins because it's the only option for JSON APIs.
     */
    private function extractToken(): string
    {
        if (!empty($_SERVER[self::HEADER_NAME])) {
            return (string) $_SERVER[self::HEADER_NAME];
        }

        if (isset($_POST[self::FORM_FIELD])) {
            return (string) $_POST[self::FORM_FIELD];
        }

        // Some clients send JSON; parse body if content type says so.
        $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ctype, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data[self::FORM_FIELD])) {
                return (string) $data[self::FORM_FIELD];
            }
        }

        return '';
    }
}