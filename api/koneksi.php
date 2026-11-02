<?php
declare(strict_types=1);

// Jangan pernah tampilkan warning/error ke response (bisa merusak JSON).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';

// Muat .env sekali saja (aman dipanggil dari file API mana pun).
if (!defined('APP_ENV_LOADED')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    define('APP_ENV_LOADED', true);
}

function env_str(string $key): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * Baca kredensial database dari connection URI (Supabase Session Pooler)
 * atau dari variabel DB_* terpisah sebagai fallback.
 *
 * Contoh DATABASE_URL (session pooler, port 5432):
 * postgresql://postgres.<project-ref>:<password>@<region>.pooler.supabase.com:5432/postgres?sslmode=require
 *
 * @return array{host: string, port: int, name: string, user: string, pass: string, sslmode: string, connect_timeout: int}
 */
function db_config(): array
{
    $uri = env_str('DATABASE_URL') ?? env_str('SUPABASE_DB_URL') ?? env_str('POSTGRES_URL');

    if ($uri !== null) {
        $parts = parse_url($uri);
        if ($parts === false || empty($parts['host'])) {
            throw new RuntimeException('DATABASE_URL tidak valid');
        }

        parse_str($parts['query'] ?? '', $query);

        return [
            'host'            => $parts['host'],
            'port'            => (int) ($parts['port'] ?? 5432),
            'name'            => isset($parts['path']) && trim($parts['path'], '/') !== ''
                ? trim($parts['path'], '/')
                : 'postgres',
            'user'            => isset($parts['user']) ? rawurldecode($parts['user']) : '',
            'pass'            => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
            'sslmode'         => (string) ($query['sslmode'] ?? 'require'),
            'connect_timeout' => (int) ($query['connect_timeout'] ?? 10),
        ];
    }

    return [
        'host'            => (string) env_str('DB_HOST'),
        // Session pooler Supabase memakai 5432 (transaction pooler: 6543).
        'port'            => (int) (env_str('DB_PORT') ?? 5432),
        'name'            => env_str('DB_NAME') ?? 'postgres',
        'user'            => (string) env_str('DB_USER'),
        'pass'            => (string) env_str('DB_PASSWORD'),
        'sslmode'         => env_str('DB_SSLMODE') ?? 'require',
        'connect_timeout' => (int) (env_str('DB_CONNECT_TIMEOUT') ?? 10),
    ];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = db_config();

    if ($config['host'] === '' || $config['user'] === '' || $config['pass'] === '') {
        throw new RuntimeException('Konfigurasi database belum lengkap');
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=%d',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['sslmode'],
        $config['connect_timeout']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Pooler Supabase (pgbouncer) tidak cocok dengan prepared statement server-side.
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    return $pdo;
}
