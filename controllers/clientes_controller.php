<?php
// controllers/clientes_controller.php
header('Content-Type: application/json; charset=utf-8');

try {
  // Autenticación (opcional si la tienes)
  $auth = __DIR__ . '/../includes/auth.php';
  if (file_exists($auth)) require_once $auth;

  require_once __DIR__ . '/../includes/db.php';

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']); exit;
  }

  // Acción: crear | actualizar | eliminar
  $action = isset($_POST['action']) ? trim($_POST['action']) : '';

  // ELIMINAR
  if ($action === 'eliminar') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }

    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $ok = $stmt->execute([$id]);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Cliente eliminado' : 'No se pudo eliminar']);
    exit;
  }

  // CREAR / ACTUALIZAR
  $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $nombre      = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
  $empresa     = isset($_POST['empresa']) ? trim($_POST['empresa']) : null;
  $email       = isset($_POST['email']) ? trim($_POST['email']) : null;
  $telefono    = isset($_POST['telefono']) ? trim($_POST['telefono']) : null;
  $servicio_id = isset($_POST['servicio_id']) ? (int)$_POST['servicio_id'] : 0;
  $estado      = isset($_POST['estado']) ? trim($_POST['estado']) : '';
  $comentario  = isset($_POST['comentario']) ? trim($_POST['comentario']) : null;

  // Validaciones mínimas según la BD (NOT NULL: nombre, servicio_id, estado)
  if ($nombre === '' || $servicio_id <= 0 || $estado === '') {
    echo json_encode(['success'=>false,'message'=>'Datos incompletos']); exit;
  }
  if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'message'=>'Email no válido']); exit;
  }

  if ($action === 'crear') {
    $stmt = $pdo->prepare("
      INSERT INTO clientes (nombre, empresa, email, telefono, servicio_id, estado, comentario)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ok = $stmt->execute([$nombre, $empresa, $email, $telefono, $servicio_id, $estado, $comentario]);
    echo json_encode(['success'=>$ok, 'message'=>$ok?'Cliente creado':'No se pudo crear']);
    exit;
  }

  if ($action === 'actualizar') {
    if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }
    $stmt = $pdo->prepare("
      UPDATE clientes
      SET nombre = ?, empresa = ?, email = ?, telefono = ?, servicio_id = ?, estado = ?, comentario = ?
      WHERE id = ?
    ");
    $ok = $stmt->execute([$nombre, $empresa, $email, $telefono, $servicio_id, $estado, $comentario, $id]);
    echo json_encode(['success'=>$ok, 'message'=>$ok?'Cliente actualizado':'No se pudo actualizar']);
    exit;
  }

  echo json_encode(['success'=>false,'message'=>'Acción no reconocida']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
