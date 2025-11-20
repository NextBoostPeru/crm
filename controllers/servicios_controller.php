<?php
require_once '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    if ($nombre === '') {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO servicios (nombre) VALUES (?)");
    $ok = $stmt->execute([$nombre]);
    echo json_encode(['success' => $ok]);
    exit;
}

$stmt = $pdo->query("SELECT * FROM servicios ORDER BY creado_en DESC");
$servicios = $stmt->fetchAll();

foreach ($servicios as $s) {
    echo "<div class='bg-white p-2 border-b'>{$s['nombre']}</div>";
}