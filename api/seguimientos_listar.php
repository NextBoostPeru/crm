<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $sql = "SELECT s.id,
                 s.fecha,
                 s.nota,
                 c.nombre       AS cliente,
                 u.nombre       AS usuario
          FROM seguimientos s
          LEFT JOIN clientes c ON c.id = s.cliente_id
          LEFT JOIN usuarios u ON u.id = s.usuario_id
          ORDER BY s.fecha DESC, s.id DESC";
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['data' => $rows, 'success' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
