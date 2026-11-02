<?php
declare(strict_types=1);
require __DIR__ . '/koneksi.php';
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, s-maxage=300, stale-while-revalidate=60');

try {
    $pdo = db();

    $profil = $pdo->query(
        'select id, nama, gelar, bio, foto_url, github_url, linkedin_url, email, updated_at
         from tbl_data_pribadi order by id limit 1'
    )->fetch();

    if (!$profil) {
        http_response_code(404);
        echo json_encode(['error' => 'Data profil belum ada'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        'select nama, level from tbl_skill where data_pribadi_id = ? order by urutan, id'
    );
    $stmt->execute([$profil['id']]);

    echo json_encode([
        'profil' => $profil,
        'skills' => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Jangan pernah kirim pesan error asli ke browser
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan server']);
}