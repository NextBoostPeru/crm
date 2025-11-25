<?php
require_once '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? null;
    $servicio_id = $_POST['servicio_id'] ?? null;
    $monto = $_POST['monto'] ?? null;
    $fecha = $_POST['fecha'] ?? null;
    $estado_pago = $_POST['estado_pago'] ?? null;

    $action = $_POST['action'] ?? 'crear';

    if ($action === 'eliminar') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM ventas WHERE id = ?");
        $ok = $stmt->execute([$id]);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Venta eliminada' : 'No se pudo eliminar']);
        exit;
    }

    if (!$cliente_id || !$servicio_id || !$monto || !$fecha || !$estado_pago) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    if (!empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE ventas SET cliente_id = ?, servicio_id = ?, monto = ?, fecha = ?, estado_pago = ? WHERE id = ?");
        $success = $stmt->execute([$cliente_id, $servicio_id, $monto, $fecha, $estado_pago, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO ventas (cliente_id, servicio_id, monto, fecha, estado_pago) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([$cliente_id, $servicio_id, $monto, $fecha, $estado_pago]);
    }

    echo json_encode(['success' => $success]);
    exit;
}

// GET - Listar ventas
$mes = $_GET['mes'] ?? date('Y-m');
$stmt = $pdo->prepare("SELECT v.*, c.nombre AS cliente, s.nombre AS servicio 
                       FROM ventas v 
                       JOIN clientes c ON v.cliente_id = c.id 
                       JOIN servicios s ON v.servicio_id = s.id 
                       WHERE DATE_FORMAT(v.fecha, '%Y-%m') = ?
                       ORDER BY v.fecha DESC");
$stmt->execute([$mes]);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($ventas);
exit;
