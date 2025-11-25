<?php
require_once '../includes/db.php';
session_start();

$cliente_id = $_POST['cliente_id'] ?? null;
$fecha      = $_POST['fecha'] ?? null;
$nota       = trim($_POST['nota'] ?? '');
$tipo       = trim($_POST['tipo'] ?? '');
$proximo    = trim($_POST['proximo'] ?? '');
$usuario_id = $_SESSION['usuario_id'] ?? null;

if (!$cliente_id || !$fecha || !$usuario_id) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$texto = $nota;
if ($tipo !== '') {
    $texto = '[' . ucfirst($tipo) . '] ' . $texto;
}
if ($proximo !== '') {
    $texto .= " | Próximo contacto: $proximo";
}

$stmt = $pdo->prepare("INSERT INTO seguimientos (cliente_id, fecha, nota, usuario_id)
                       VALUES (?, ?, ?, ?)");
$ok = $stmt->execute([$cliente_id, $fecha, $texto, $usuario_id]);

echo json_encode(['success' => $ok, 'message' => $ok ? 'Seguimiento registrado' : 'No se pudo registrar']);
