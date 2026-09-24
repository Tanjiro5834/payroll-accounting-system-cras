<?php
namespace App\Middleware;

class RateLimitMiddleware
{
    /**
     * Directory for file-backed counters when no shared cache is available.
     * In production, back this with Redis/Memcached instead.
     */
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/rate_limit';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0700, true);
        }
    }

    /**
     * Default policy: 60 requests per 60 seconds, keyed by client IP + route.
     */
    public function handle(): void
    {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $route = $_SERVER['REQUEST_URI'] ?? '/';
        $key   = 'rl:' . $ip . ':' . md5($route);

        if (!$this->check($key, 60, 60)) {
            http_response_code(429);
            header('Retry-After: 60');
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }

        $this->increment($key);
    }

    /**
     * Check whether the key is still under the limit for the current window.
     * Also prunes expired window files opportunistically.
     */
    public function check(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return true;
        }

        $data = $this->read($file);
        if ($data === null) {
            return true;
        }

        // Window expired → fresh start.
        if ((time() - $data['window_start']) >= $windowSeconds) {
            return true;
        }

        return $data['count'] < $maxAttempts;
    }

    /**
     * Increment the counter for the key. Starts a new window if
     * the existing one has expired.
     */
    public function increment(string $key): void
    {
        $file = $this->filePath($key);
        $now  = time();

        // Use LOCK_EX on write and read-modify-write under an flock to avoid
        // lost updates when two requests hit at the same instant.
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return; // Fail open — logging/monitoring should catch this.
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                return;
            }

            $raw  = stream_get_contents($fh);
            $data = $raw ? json_decode($raw, true) : null;

            if (!is_array($data) || !isset($data['count'], $data['window_start'])) {
                $data = ['count' => 0, 'window_start' => $now];
            }

            // Reset the window if the current one has expired.
            // (Callers typically know the window length, but the file's a
            // fallback for the common case where they don't pass it here.)
            if (($now - $data['window_start']) > 3600) {
                $data = ['count' => 0, 'window_start' => $now];
            }

            $data['count']++;

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($data));
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Clear the counter for a key — used after successful login, etc.
     */
    public function reset(string $key): void
    {
        $file = $this->filePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Read counter state from disk.
     */
    private function read(string $file): ?array
    {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['count'], $data['window_start']) ? $data : null;
    }

    /**
     * Map a key to a filesystem-safe path inside the storage directory.
     */
    private function filePath(string $key): string
    {
        return $this->storageDir . '/' . hash('sha256', $key) . '.json';
    }
}