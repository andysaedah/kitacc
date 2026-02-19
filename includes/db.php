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
                http_response_code(500);
                die(json_encode(['error' => 'Service temporarily unavailable.']));
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
