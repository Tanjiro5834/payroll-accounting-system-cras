<?php

class Database {
    private static ?Database $instance = null;
    private PDO $connection;
    private int $transactionLevel = 0; // Track nesting

    private function __construct() {
        $isLocal = $this->isLocalhost();

        if ($isLocal) {
            // ---- LOCAL (development) ----
            $host    = 'localhost';
            $db      = 'coronacion_svc_local';
            $user    = 'root';
            $pass    = '';
            $charset = 'utf8mb4';
        } else {
            // ---- PRODUCTION ----
            $host    = getenv('DB_HOST') ?: 'localhost';
            $db      = getenv('DB_NAME') ?: 'u822796852_coronacion_svc';
            $user    = getenv('DB_USER') ?: 'u822796852_app';
            $pass    = getenv('DB_PASS') ?: '';
            $charset = 'utf8mb4';
        }

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        try {
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (\PDOException $e) {
            if ($isLocal) {
                throw new \RuntimeException("DB connection failed: " . $e->getMessage(), 0, $e);
            }
            error_log("DB connection failed: " . $e->getMessage());
            throw new \RuntimeException("Database connection failed.", 0, $e);
        }
    }

    private function isLocalhost(): bool {
        $env = getenv('APP_ENV');
        if ($env === 'local')      return true;
        if ($env === 'production') return false;

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $host = strtolower(explode(':', $host)[0]);

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return true;
        }

        return false; // Safe default = production
    }

    // ---------------- Singleton ----------------

    private function __clone() {}

    public function __wakeup() {
        throw new \RuntimeException("Cannot unserialize a singleton.");
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    // ---------------- Transaction API ----------------

    /**
     * Start a transaction. Supports nesting via savepoints.
     */
    public function beginTransaction(): bool {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
        } else {
            // Nested → use a savepoint
            $this->connection->exec("SAVEPOINT trans_{$this->transactionLevel}");
        }
        $this->transactionLevel++;
        return true;
    }

    /**
     * Commit the current transaction (or release savepoint if nested).
     */
    public function commit(): bool {
        if ($this->transactionLevel <= 0) {
            throw new \RuntimeException("No active transaction to commit.");
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->connection->commit();
        }

        // Nested → release savepoint
        $this->connection->exec("RELEASE SAVEPOINT trans_{$this->transactionLevel}");
        return true;
    }

    /**
     * Roll back the current transaction (or rollback to savepoint if nested).
     */
    public function rollBack(): bool {
        if ($this->transactionLevel <= 0) {
            throw new \RuntimeException("No active transaction to roll back.");
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            return $this->connection->rollBack();
        }

        // Nested → rollback to savepoint
        $this->connection->exec("ROLLBACK TO SAVEPOINT trans_{$this->transactionLevel}");
        return true;
    }

    /**
     * Is a transaction currently active?
     */
    public function inTransaction(): bool {
        return $this->connection->inTransaction();
    }

    /**
     * Run a callback inside a transaction. Auto-commit on success,
     * auto-rollback on any exception.
     *
     * @param callable $callback function(PDO $db) { ... return $result; }
     * @return mixed Whatever the callback returns
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();

        try {
            $result = $callback($this->connection);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }
}