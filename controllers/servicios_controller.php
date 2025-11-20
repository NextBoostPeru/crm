<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';
session_start();

if (($_SESSION['rol'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $servicios = $pdo->query('SELECT id, nombre, creado_en FROM servicios ORDER BY creado_en DESC')
                     ->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $servicios]);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM servicios WHERE id = ?');
    $ok = $stmt->execute([$id]);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Servicio eliminado' : 'No se pudo eliminar']);
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');

if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'Escribe un nombre']);
    exit;
}

if ($action === 'crear') {
    $stmt = $pdo->prepare('INSERT INTO servicios (nombre) VALUES (?)');
    $ok = $stmt->execute([$nombre]);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Servicio creado' : 'No se pudo crear']);
    exit;
}

if ($action === 'actualizar') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE servicios SET nombre = ? WHERE id = ?');
    $ok = $stmt->execute([$nombre, $id]);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Servicio actualizado' : 'No se pudo actualizar']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
