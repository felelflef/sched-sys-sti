<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─────────────────────────────────────────────────────────
// DATABASE CREDENTIALS
// Reads from environment variables (set in Koyeb dashboard).
// Aiven MySQL provides: host, port, dbname, user, password,
// and requires SSL (sslmode=required).
// ─────────────────────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'sti_classroom_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Aiven requires SSL. Set DB_SSL=true in Koyeb env vars.
define('DB_SSL', filter_var(getenv('DB_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// Gemini API
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'your_gemini_api_key_here');
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-2.0-flash');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Aiven MySQL requires SSL connections
        if (DB_SSL) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            $options[PDO::MYSQL_ATTR_SSL_CA] = '';
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Self-healing schema migration for subject roles and subject categories
            static $migrated = false;
            if (!$migrated) {
                try {
                    $checkUsers = $pdo->query("SHOW COLUMNS FROM users LIKE 'subject_role'")->fetch();
                    if (!$checkUsers) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN subject_role VARCHAR(50) DEFAULT 'IT'");
                    }
                    $checkSchedules = $pdo->query("SHOW COLUMNS FROM schedules LIKE 'subject_category'")->fetch();
                    if (!$checkSchedules) {
                        $pdo->exec("ALTER TABLE schedules ADD COLUMN subject_category VARCHAR(50) DEFAULT 'IT'");
                    }
                    $checkRole = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
                    if ($checkRole && strpos($checkRole['Type'], 'academic-head') === false) {
                        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'academic-head', 'program-head', 'faculty') NOT NULL");
                    }
                } catch (\Throwable $migErr) {
                    // Silently ignore schema check errors to avoid blocking execution if restricted
                }
                $migrated = true;
            }
        } catch (PDOException $e) {
            http_response_code(500);
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
