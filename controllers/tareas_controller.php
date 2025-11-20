<?php
// controllers/tareas_controller.php
header('Content-Type: application/json; charset=utf-8');

try {
  $auth = __DIR__ . '/../includes/auth.php';
  if (file_exists($auth)) require_once $auth;
  require_once __DIR__ . '/../includes/db.php';

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
  }

  $action = $_POST['action'] ?? '';

  // Helpers
  function mes_prefijo($ts=null){
    $m = (int)date('n', $ts ?: time());
    $map = [1=>'ENE',2=>'FEB',3=>'MAR',4=>'ABR',5=>'MAY',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SEP',10=>'OCT',11=>'NOV',12=>'DIC'];
    return $map[$m] ?? date('M');
  }
  function generar_ticket(PDO $pdo){
    $pref = mes_prefijo();
    $stmt = $pdo->prepare("SELECT MAX(ticket) AS max_t FROM tareas WHERE ticket LIKE CONCAT(?, '-%')");
    $stmt->execute([$pref]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $next = 1;
    if (!empty($row['max_t']) && preg_match('/-(\d{4})$/', $row['max_t'], $m)) $next = (int)$m[1] + 1;
    return sprintf('%s-%04d', $pref, $next);
  }
  function quitar_acentos($str){
    $tr = [
      'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
      'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
    ];
    return strtr($str, $tr);
  }
  function norm($s){ return mb_strtolower(trim(quitar_acentos((string)$s))); }
  function to_int_array($arr){
    $out = [];
    if (is_array($arr)) {
      foreach ($arr as $v) { $n = (int)$v; if ($n>0 && !in_array($n,$out,true)) $out[] = $n; }
    }
    return $out;
  }

  // Buscar usuarios (autocomplete)
  if ($action === 'buscar_usuarios') {
    $q = trim($_POST['q'] ?? '');
    if (mb_strlen($q) < 2) { echo json_encode(['success'=>true,'data'=>[]]); exit; }
    $like = "%{$q}%";
    $stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE nombre LIKE ? OR email LIKE ? ORDER BY nombre LIMIT 20");
    $stmt->execute([$like, $like]);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  // Asignados de una tarea
  if ($action === 'get_asignados') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT u.id, u.nombre FROM tareas_asignados ta JOIN usuarios u ON u.id=ta.usuario_id WHERE ta.tarea_id=? ORDER BY u.nombre");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  // Checklists
  if ($action === 'get_checklists') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, titulo FROM tareas_checklists WHERE tarea_id=? ORDER BY id");
    $stmt->execute([$id]);
    $secs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($secs as $s) {
      $it = $pdo->prepare("SELECT texto, done FROM tareas_checklist_items WHERE checklist_id=? ORDER BY id");
      $it->execute([$s['id']]);
      $out[] = ['titulo'=>$s['titulo'],'items'=>$it->fetchAll(PDO::FETCH_ASSOC)];
    }
    echo json_encode(['success'=>true,'data'=>$out]); exit;
  }

  // Adjuntos
  if ($action === 'get_adjuntos') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, nombre, ruta FROM tareas_adjuntos WHERE tarea_id=? ORDER BY id");
    $stmt->execute([$id]);
    $rows = [];
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
      $url = (strpos($r['ruta'], '/')===0 ? $r['ruta'] : '/'.$r['ruta']);
      $rows[] = ['id'=>(int)$r['id'], 'nombre'=>$r['nombre'], 'url'=>$url];
    }
    echo json_encode(['success'=>true,'data'=>$rows]); exit;
  }

  if ($action === 'adjunto_eliminar') {
    $adjunto_id = (int)($_POST['adjunto_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT ruta FROM tareas_adjuntos WHERE id=?");
    $stmt->execute([$adjunto_id]);
    $ruta = $stmt->fetchColumn();
    $ok = true;
    if ($ruta && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.ltrim($ruta,'/'))) {
      $ok = @unlink($_SERVER['DOCUMENT_ROOT'].'/'.ltrim($ruta,'/'));
    }
    $del = $pdo->prepare("DELETE FROM tareas_adjuntos WHERE id=?");
    $ok = $del->execute([$adjunto_id]) && $ok;
    echo json_encode(['success'=>$ok]); exit;
  }

  // Drag & drop: mover + reordenar columna
  if ($action === 'mover') {
    $id      = (int)($_POST['id'] ?? 0);
    $estado  = $_POST['estado_kanban'] ?? '';
    $ordenes = isset($_POST['orden_json']) ? json_decode($_POST['orden_json'], true) : [];

    if (!$id || !in_array($estado, ['pendiente','en_progreso','en_revision','completada'], true)) {
      echo json_encode(['success'=>false,'message'=>'Datos inválidos']); exit;
    }

    $pdo->beginTransaction();
    try {
      $pdo->prepare("UPDATE tareas SET estado_kanban=? WHERE id=?")->execute([$estado, $id]);

      if (is_array($ordenes) && count($ordenes) > 0) {
        $stmt = $pdo->prepare("UPDATE tareas SET orden=? WHERE id=?");
        foreach ($ordenes as $i => $tid) {
          $stmt->execute([(int)$i, (int)$tid]);
        }
      }

      $pdo->commit();
      echo json_encode(['success'=>true]); exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit;
    }
  }

  // Crear / Actualizar
  if ($action === 'crear' || $action === 'actualizar') {
    $id            = (int)($_POST['id'] ?? 0);
    $titulo        = trim($_POST['titulo'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');

    // Normalizar prioridad/equipo para evitar “Datos incompletos” por acentos/diferencias
    $prioridad_raw = $_POST['prioridad'] ?? 'media';
    $equipo_raw    = $_POST['equipo'] ?? 'desarrollo';
    $pn = norm($prioridad_raw); // baja|media|alta|critica
    $en = norm($equipo_raw);    // diseno|desarrollo

    $prioridad = in_array($pn, ['baja','media','alta','critica'], true)
                  ? ($pn === 'critica' ? 'crítica' : $pn)
                  : 'media';
    $equipo    = in_array($en, ['diseno','desarrollo'], true)
                  ? ($en === 'diseno' ? 'diseño' : $en)
                  : 'desarrollo';

    $fecha_inicio  = $_POST['fecha_inicio'] ?: null;
    $fecha_entrega = $_POST['fecha_entrega'] ?: null;
    $estimado_h    = $_POST['estimado_horas'] !== '' ? (float)$_POST['estimado_horas'] : null;
    $real_h        = $_POST['real_horas'] !== '' ? (float)$_POST['real_horas'] : null;
    $asignados     = to_int_array($_POST['asignados'] ?? []);
    $checklists_json = $_POST['checklists_json'] ?? '[]';
    $estado_kanban = 'pendiente';

    if ($titulo === '') {
      echo json_encode(['success'=>false,'message'=>'Título requerido']); exit;
    }

    $pdo->beginTransaction();
    try {
      if ($action === 'crear') {
        $ticket = generar_ticket($pdo);
        $mx = $pdo->query("SELECT COALESCE(MAX(orden), -1)+1 FROM tareas WHERE estado_kanban='pendiente'")->fetchColumn();
        $ins = $pdo->prepare("INSERT INTO tareas (ticket, titulo, descripcion, estado_kanban, prioridad, equipo, fecha_inicio, fecha_entrega, estimado_horas, real_horas, orden) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $ok  = $ins->execute([$ticket, $titulo, $descripcion, $estado_kanban, $prioridad, $equipo, $fecha_inicio, $fecha_entrega, $estimado_h, $real_h, (int)$mx]);
        if (!$ok) throw new Exception('No se pudo crear la tarea');
        $id = (int)$pdo->lastInsertId();

      } else {
        $upd = $pdo->prepare("UPDATE tareas SET titulo=?, descripcion=?, prioridad=?, equipo=?, fecha_inicio=?, fecha_entrega=?, estimado_horas=?, real_horas=? WHERE id=?");
        $ok  = $upd->execute([$titulo, $descripcion, $prioridad, $equipo, $fecha_inicio, $fecha_entrega, $estimado_h, $real_h, $id]);
        if (!$ok) throw new Exception('No se pudo actualizar la tarea');
      }

      // Asignados
      $pdo->prepare("DELETE FROM tareas_asignados WHERE tarea_id=?")->execute([$id]);
      if (!empty($asignados)) {
        $insA = $pdo->prepare("INSERT INTO tareas_asignados (tarea_id, usuario_id) VALUES (?,?)");
        foreach ($asignados as $uid) { $insA->execute([$id, $uid]); }
      }

      // Checklists (reemplazo)
      $pdo->prepare("DELETE i FROM tareas_checklist_items i JOIN tareas_checklists c ON c.id=i.checklist_id WHERE c.tarea_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM tareas_checklists WHERE tarea_id=?")->execute([$id]);
      $cks = json_decode($checklists_json, true) ?: [];
      if (!empty($cks)) {
        $insC = $pdo->prepare("INSERT INTO tareas_checklists (tarea_id, titulo) VALUES (?,?)");
        $insI = $pdo->prepare("INSERT INTO tareas_checklist_items (checklist_id, texto, done) VALUES (?,?,?)");
        foreach ($cks as $sec) {
          $tituloC = trim($sec['titulo'] ?? '');
          $insC->execute([$id, $tituloC]);
          $cid = (int)$pdo->lastInsertId();
          foreach (($sec['items'] ?? []) as $it) {
            $txt = trim($it['texto'] ?? '');
            if ($txt === '') continue;
            $done = !empty($it['done']) ? 1 : 0;
            $insI->execute([$cid, $txt, $done]);
          }
        }
      }

      // Adjuntos
      if (!empty($_FILES['adjuntos']) && is_array($_FILES['adjuntos']['name'])) {
        $base = '/uploads/tareas';
        $absBase = rtrim($_SERVER['DOCUMENT_ROOT'], '/').$base;
        if (!is_dir($absBase)) @mkdir($absBase, 0775, true);
        $insAdj = $pdo->prepare("INSERT INTO tareas_adjuntos (tarea_id, ruta, nombre, peso, tipo) VALUES (?,?,?,?,?)");
        for($i=0;$i<count($_FILES['adjuntos']['name']);$i++){
          if ($_FILES['adjuntos']['error'][$i] !== UPLOAD_ERR_OK) continue;
          $tmp  = $_FILES['adjuntos']['tmp_name'][$i];
          $name = basename($_FILES['adjuntos']['name'][$i]);
          $type = $_FILES['adjuntos']['type'][$i] ?: '';
          $size = (int)$_FILES['adjuntos']['size'][$i];
          $safe = preg_replace('/[^a-zA-Z0-9_.-]/','_', $name);
          $dest = $absBase.'/'.time().'_'.$safe;
          if (@move_uploaded_file($tmp, $dest)) {
            $ruta = ltrim($base.'/'.basename($dest), '/');
            $insAdj->execute([$id, $ruta, $name, $size, $type]);
          }
        }
      }

      $pdo->commit();
      echo json_encode(['success'=>true, 'id'=>$id]); exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit;
    }
  }

  // Eliminar
  if ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }
    $pdo->beginTransaction();
    try{
      $stmt = $pdo->prepare("SELECT ruta FROM tareas_adjuntos WHERE tarea_id=?");
      $stmt->execute([$id]);
      while($ruta = $stmt->fetchColumn()){
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.ltrim($ruta,'/');
        if (is_file($path)) @unlink($path);
      }
      $pdo->prepare("DELETE i FROM tareas_checklist_items i JOIN tareas_checklists c ON c.id=i.checklist_id WHERE c.tarea_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM tareas_checklists WHERE tarea_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM tareas_adjuntos WHERE tarea_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM tareas_asignados WHERE tarea_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM tareas WHERE id=?")->execute([$id]);
      $pdo->commit();
      echo json_encode(['success'=>true]); exit;
    }catch(Throwable $e){
      $pdo->rollBack();
      echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit;
    }
  }

  echo json_encode(['success'=>false,'message'=>'Acción no reconocida']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
