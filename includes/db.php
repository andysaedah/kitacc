<?php
/**
 * KiTAcc - Database Connection
 * PDO singleton with .env configuration
 */

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'kitacc';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Set timezone
                $tz = getenv('APP_TIMEZONE') ?: 'Asia/Kuala_Lumpur';
                $now = new DateTime('now', new DateTimeZone($tz));
                $offset = $now->format('P');
                self::$instance->exec("SET time_zone = '{$offset}'");

            } catch (PDOException $e) {
                error_log('KiTAcc DB connection failed: ' . $e->getMessage());
                http_response_code(503);
                $debugMode = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
                if (php_sapi_name() === 'cli') {
                    die('Database connection failed.' . ($debugMode ? ' ' . $e->getMessage() : ''));
                }
                die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Service Unavailable</title>'
                    . '<style>body{font-family:Inter,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#F8F7FC;color:#374051;}'
                    . '.box{text-align:center;max-width:420px;padding:2rem;}h1{font-size:1.5rem;margin:0 0 .5rem;}p{color:#6B6F80;line-height:1.6;}</style></head>'
                    . '<body><div class="box"><h1>Service Temporarily Unavailable</h1>'
                    . '<p>We are experiencing a technical issue. Please try again later.</p>'
                    . ($debugMode ? '<pre style="text-align:left;background:#f1f0f7;padding:1rem;border-radius:.5rem;font-size:.8rem;margin-top:1rem;overflow:auto;">' . htmlspecialchars($e->getMessage()) . '</pre>' : '')
                    . '</div></body></html>');
            }
        }

        return self::$instance;
    }

    // Prevent cloning and deserialization
    private function __construct()
    {
    }
    private function __clone()
    {
    }
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

/**
 * Shortcut to get PDO instance
 */
function db(): PDO
{
    return Database::getInstance();
}
