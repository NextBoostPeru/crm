<?php
// api/clientes_buscar.php
header('Content-Type: application/json; charset=utf-8');

try {
  // (Opcional) proteger con tu middleware si lo usas
  $auth = __DIR__ . '/../includes/auth.php';
  if (file_exists($auth)) require_once $auth;

  require_once __DIR__ . '/../includes/db.php';

  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  if (mb_strlen($q) < 3) {
    echo json_encode(['success' => true, 'data' => []]); exit;
  }

  // Búsqueda por nombre, empresa o email (sin limitar por mes)
  $like = '%' . $q . '%';
  $sql = "
    SELECT c.id, c.nombre, c.empresa, c.email, c.telefono,
           s.nombre AS servicio
    FROM clientes c
    LEFT JOIN servicios s ON s.id = c.servicio_id
    WHERE c.nombre   LIKE :q
       OR c.empresa  LIKE :q
       OR c.email    LIKE :q
    ORDER BY c.creado_en DESC
    LIMIT 20
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':q', $like, PDO::PARAM_STR);
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Respuesta compacta para el autocomplete
  $data = array_map(function ($r) {
    return [
      'id'       => (int)$r['id'],
      'nombre'   => $r['nombre'] ?? '',
      'empresa'  => $r['empresa'] ?? '',
      'email'    => $r['email'] ?? '',
      'telefono' => $r['telefono'] ?? '',
      'servicio' => $r['servicio'] ?? '',
    ];
  }, $rows);

  echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
