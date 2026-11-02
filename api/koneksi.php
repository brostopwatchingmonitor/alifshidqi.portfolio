<?php 
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO){
        return $pdo;
    }
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '6543';
    $name = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');

    if (!$host || !$user || !$pass) {
        throw new RuntimeException('Konfigurasi database belum lengkap');
    }

    $dsn = "pgsql -h {$host} -p {$port} -d {$name} -U {$user}";
    //psql -h aws-0-ap-southeast-1.pooler.supabase.com -p 5432 -d postgres -U postgres.weucyxmnjczylbajddmr
// -p 5432 -d postgres -U postgres.weucyxmnjczylbajddmr
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Pooler Supabase (pgbouncer) tidak cocok dengan prepared statement server-side
        PDO::ATTR_EMULATE_PREPARES   => true,
    ]);
    
    return $pdo;
}


