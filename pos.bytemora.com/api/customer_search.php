<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}
$like = '%' . $q . '%';

$stmt = $pdo->prepare("SELECT id, name, phone FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name LIMIT 10");
$stmt->execute([$like, $like]);
echo json_encode(['ok' => true, 'results' => $stmt->fetchAll()]);
