<?php
// admin/clientes.php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/db.php';

$mes_actual = isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes']) ? $_GET['mes'] : date('Y-m');
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$filtro_servicio = isset($_GET['servicio']) ? trim($_GET['servicio']) : '';
$filtro_busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$limite_listado = 150; // evita listas kilométricas

// Helper para reutilizar filtros en consultas
function condiciones_clientes(array $opts): array {
  $where = ["DATE_FORMAT(c.creado_en, '%Y-%m') = :mes"];
  $params = [':mes' => $opts['mes']];

  if ($opts['estado'] !== '') {
    $where[] = 'LOWER(c.estado) = LOWER(:estado)';
    $params[':estado'] = $opts['estado'];
  }

  if ($opts['servicio'] !== '') {
    $where[] = 'c.servicio_id = :servicio_id';
    $params[':servicio_id'] = (int)$opts['servicio'];
  }

  if ($opts['busqueda'] !== '') {
    $where[] = '(c.nombre LIKE :q OR c.empresa LIKE :q OR c.email LIKE :q)';
    $params[':q'] = '%'.$opts['busqueda'].'%';
  }

  return [$where, $params];
}

// Catálogo
$servicios = $pdo->query("SELECT id, nombre FROM servicios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$estados   = ['en negociación', 'cerrado', 'interesado', 'nuevo'];

// Listado (por mes, como ventas.php) con filtros y límite
[$where_list, $params_list] = condiciones_clientes([
  'mes' => $mes_actual,
  'estado' => $filtro_estado,
  'servicio' => $filtro_servicio,
  'busqueda' => $filtro_busqueda,
]);

$errores_consulta = [];

try {
  $sql_list = "SELECT c.id, c.nombre, c.empresa, c.email, c.telefono, c.estado, c.comentario, c.creado_en,
                      c.servicio_id, s.nombre AS servicio
               FROM clientes c
               LEFT JOIN servicios s ON s.id = c.servicio_id
               WHERE ".implode(' AND ', $where_list)."
               ORDER BY c.creado_en DESC, c.id DESC
               LIMIT {$limite_listado}";
  $stmt = $pdo->prepare($sql_list);
  $stmt->execute($params_list);
  $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $clientes = [];
  $errores_consulta[] = 'No se pudo cargar el listado de clientes. Verifica la tabla clientes: '.$e->getMessage();
}

// Conteo total para avisar si se recorta la lista
try {
  $sql_total = "SELECT COUNT(*) FROM clientes c LEFT JOIN servicios s ON s.id = c.servicio_id WHERE ".implode(' AND ', $where_list);
  $stmt_total = $pdo->prepare($sql_total);
  $stmt_total->execute($params_list);
  $total_clientes_filtrados = (int)$stmt_total->fetchColumn();
  $listado_recortado = $total_clientes_filtrados > $limite_listado;
} catch (Throwable $e) {
  $total_clientes_filtrados = 0;
  $listado_recortado = false;
  $errores_consulta[] = 'No se pudo calcular el total de clientes: '.$e->getMessage();
}

// Seguimiento general (sin limitar por mes)
[$where_pipeline, $params_pipeline] = condiciones_clientes([
  'mes' => $mes_actual,
  'estado' => $filtro_estado,
  'servicio' => $filtro_servicio,
  'busqueda' => $filtro_busqueda,
]);

try {
  $sql_pipeline = "
    SELECT c.id, c.nombre, c.empresa, c.email, c.telefono, c.estado, c.servicio_id,
           s.nombre AS servicio,
           (SELECT MAX(fecha) FROM seguimientos WHERE cliente_id = c.id) AS ultima_interaccion,
           (SELECT nota FROM seguimientos WHERE cliente_id = c.id ORDER BY fecha DESC, id DESC LIMIT 1) AS ultima_nota
    FROM clientes c
    LEFT JOIN servicios s ON s.id = c.servicio_id
    WHERE ".implode(' AND ', $where_pipeline)."
    ORDER BY (ultima_interaccion IS NULL), ultima_interaccion DESC, c.nombre
    LIMIT 120
  ";
  $clientes_pipeline_stmt = $pdo->prepare($sql_pipeline);
  $clientes_pipeline_stmt->execute($params_pipeline);
  $clientes_pipeline = $clientes_pipeline_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $clientes_pipeline = [];
  $errores_consulta[] = 'No se pudo cargar el pipeline de clientes. Confirma las tablas clientes/seguimientos: '.$e->getMessage();
}

try {
  $seg_stmt = $pdo->query("
    SELECT s.id, s.fecha, s.nota, c.nombre AS cliente, c.estado
    FROM seguimientos s
    LEFT JOIN clientes c ON c.id = s.cliente_id
    ORDER BY s.fecha DESC, s.id DESC
    LIMIT 12
  ");
  $seguimientos = $seg_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $seguimientos = [];
  $errores_consulta[] = 'No se pudieron cargar los seguimientos recientes: '.$e->getMessage();
}

// Resúmenes rápidos para la cabecera
$total_clientes = count($clientes);
$resumen_estado = [
  'en negociación' => 0,
  'interesado'     => 0,
  'cerrado'        => 0,
  'nuevo'          => 0,
];
$resumen_servicio = [];
foreach ($clientes as $cli) {
  $estado_lc = mb_strtolower(trim((string)($cli['estado'] ?? '')));
  if (isset($resumen_estado[$estado_lc])) {
    $resumen_estado[$estado_lc]++;
  }

  $servicio_nombre = $cli['servicio'] ?: 'Sin servicio';
  if (!isset($resumen_servicio[$servicio_nombre])) {
    $resumen_servicio[$servicio_nombre] = 0;
  }
  $resumen_servicio[$servicio_nombre]++;
}
arsort($resumen_servicio);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fecha_corta($ts){ return $ts ? date('Y-m-d', strtotime($ts)) : ''; }

// Badges estilo ventas
function badge_estado($estado){
  $estado_lc = mb_strtolower(trim((string)$estado));
  $map = [
    'cerrado'          => 'bg-green-100 text-green-800 ring-green-200',
    'en negociación'   => 'bg-amber-100 text-amber-800 ring-amber-200',
    'interesado'       => 'bg-blue-100 text-blue-800 ring-blue-200',
    'nuevo'            => 'bg-gray-100 text-gray-800 ring-gray-200',
  ];
  $cls = $map[$estado_lc] ?? 'bg-gray-100 text-gray-800 ring-gray-200';
  return '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$cls.'">'.h($estado).'</span>';
}
function etapa_label($estado){
  $e = strtolower((string)$estado);
  if (in_array($e, ['lead','nuevo','contacto inicial'])) return 'Lead';
  if (in_array($e, ['prospecto','prospecting','seguimiento'])) return 'Prospecto';
  if (in_array($e, ['cliente','activo','cerrado'])) return 'Cliente';
  if (in_array($e, ['fidelizado','fidelizacion','fidelización'])) return 'Fidelización';
  return 'Prospecto';
}
function proximo_recordatorio($ultima){
  if (!$ultima) return date('Y-m-d', strtotime('+2 days'));
  return date('Y-m-d', strtotime($ultima.' +5 days'));
}

$etapas = ['Lead','Prospecto','Cliente','Fidelización'];
$pipeline_cols = array_fill_keys($etapas, []);
foreach ($clientes_pipeline as $cli) {
  $etapa = etapa_label($cli['estado']);
  $cli['proximo'] = proximo_recordatorio($cli['ultima_interaccion']);
  $pipeline_cols[$etapa][] = $cli;
}
$recordatorios = [];
foreach ($clientes_pipeline as $cli) {
  $recordatorios[] = [
    'cliente' => $cli['nombre'],
    'estado' => etapa_label($cli['estado']),
    'proximo' => proximo_recordatorio($cli['ultima_interaccion']),
    'ultima'  => $cli['ultima_interaccion']
  ];
}
usort($recordatorios, fn($a,$b) => strcmp($a['proximo'], $b['proximo']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Clientes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Tailwind (como ventas.php) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <style>
    table.dataTable thead th { background: #e5e7eb; }
    table.dataTable tbody tr:hover { background: #f9fafb; }
    .dt-container .dataTables_length select,
    .dt-container .dataTables_filter input { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .25rem .5rem; }
    .card-shadow { box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.08), 0 8px 10px -6px rgb(0 0 0 / 0.1); }
    .pill { border-radius: 999px; padding: .25rem .65rem; font-weight: 600; display: inline-flex; align-items: center; gap: .35rem; }
    .modal-overlay { background: linear-gradient(135deg, rgba(15,23,42,.55), rgba(15,23,42,.45)); }
    .field-label { font-size: .875rem; font-weight: 600; color: #1f2937; }
    .animate-fade-in { animation: fadeIn 0.3s ease; }
    .kanban-col { min-width: 260px; }
    .kanban-card { border: 1px solid #e5e7eb; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px);} to { opacity: 1; transform: translateY(0);} }
  </style>
</head>
<body class="bg-gray-100">
  <?php include '../includes/sidebar_admin.php'; ?>

  <div class="p-4 lg:ml-64 space-y-4">
    <div class="bg-white rounded-2xl p-5 card-shadow border border-gray-100 flex flex-col gap-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <p class="text-sm text-gray-500">Panel de relación</p>
          <h2 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 20a8 8 0 0116 0" />
            </svg>
            Clientes
          </h2>
          <p class="text-sm text-gray-600 mt-1">Seguimiento mensual de interesados, negociaciones y cierres.</p>
        </div>
      <div class="flex gap-2 flex-wrap">
        <button onclick="abrirModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2 hover:bg-blue-700 transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="1.5" d="M12 5v14M5 12h14" />
          </svg>
            Nuevo cliente
          </button>
          <button onclick="filtrarMes()" class="bg-slate-900 text-white px-4 py-2 rounded-lg inline-flex items-center gap-2 hover:bg-slate-800 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.5" d="M4 7h16M4 12h10M4 17h7" />
            </svg>
            Actualizar filtros
          </button>
        </div>
      </div>

      <?php if (!empty($errores_consulta)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 space-y-1">
          <?php foreach ($errores_consulta as $err): ?>
            <p>⚠️ <?= h($err) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
          <p class="text-xs uppercase tracking-wide text-blue-700 font-semibold">Clientes del mes</p>
          <div class="flex items-end gap-2 mt-2">
            <span class="text-3xl font-extrabold text-blue-900"><?= $total_clientes ?></span>
            <span class="text-sm text-blue-700"><?= h($mes_actual) ?></span>
          </div>
        </div>
        <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
          <p class="text-xs uppercase tracking-wide text-amber-700 font-semibold">En negociación</p>
          <div class="text-2xl font-extrabold text-amber-800 mt-2"><?= $resumen_estado['en negociación'] ?></div>
        </div>
        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
          <p class="text-xs uppercase tracking-wide text-emerald-700 font-semibold">Cerrados</p>
          <div class="text-2xl font-extrabold text-emerald-800 mt-2"><?= $resumen_estado['cerrado'] ?></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs uppercase tracking-wide text-slate-700 font-semibold">Nuevos / interesados</p>
          <div class="flex gap-4 mt-2 text-slate-800 font-semibold">
            <span><?= $resumen_estado['nuevo'] ?> nuevos</span>
            <span><?= $resumen_estado['interesado'] ?> interesados</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <label class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-gray-700">Mes</span>
          <input type="month" id="filtroMes" class="border border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-200" value="<?= h($mes_actual) ?>">
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-gray-700">Estado</span>
          <select id="filtroEstado" class="border border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-200">
            <option value="">Todos</option>
            <?php foreach ($estados as $estado): ?>
              <option value="<?= h($estado) ?>" <?= $filtro_estado === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-gray-700">Servicio</span>
          <select id="filtroServicio" class="border border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-200">
            <option value="">Todos</option>
            <?php foreach ($servicios as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= (string)$filtro_servicio === (string)$s['id'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-gray-700">Buscar</span>
          <input type="search" id="filtroBusqueda" class="border border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-200" placeholder="Nombre, empresa o email" value="<?= h($filtro_busqueda) ?>">
        </label>
      </div>

      <div class="flex items-center flex-wrap gap-2 text-xs text-gray-600">
        <span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">
          <?= $total_clientes_filtrados ?> coincidencias
          <?= $listado_recortado ? "· mostrando {$limite_listado}" : '' ?>
        </span>
        <?php if ($listado_recortado): ?>
          <span class="text-amber-600 flex items-center gap-1 text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.5" d="M12 9v3m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Demasiados resultados. Ajusta los filtros o la búsqueda para acotar la lista.
          </span>
        <?php endif; ?>
      </div>

      <div class="flex flex-wrap gap-2 text-xs text-gray-500">
        <?php $topServ = array_slice($resumen_servicio, 0, 3, true); ?>
        <?php foreach ($topServ as $servicio => $count): ?>
          <span class="pill bg-slate-100 text-slate-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.5" d="M4 7h16M4 12h16M4 17h10" />
            </svg>
            <?= h($servicio) ?> · <?= (int)$count ?>
          </span>
        <?php endforeach; ?>
        <?php if (!$topServ): ?>
          <span class="text-gray-500">Sin servicios registrados este mes.</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2 bg-white rounded-2xl card-shadow border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-semibold flex items-center gap-2 text-slate-900">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M4 6h16M6 12h12M10 18h4"/></svg>
            Pipeline de clientes
          </h3>
          <span class="text-xs text-gray-500">Lead → Prospecto → Cliente</span>
        </div>
        <div class="overflow-x-auto pb-2">
          <div class="flex gap-3 min-w-full">
            <?php foreach ($etapas as $etapa): ?>
              <div class="kanban-col bg-gray-50 rounded-xl p-3 shadow-inner flex-1">
                <div class="flex items-center justify-between mb-2">
                  <p class="font-semibold text-gray-700 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-500"></span> <?= h($etapa) ?>
                  </p>
                  <span class="text-xs text-gray-500 px-2 py-1 bg-white rounded-full border"><?= count($pipeline_cols[$etapa]) ?> en curso</span>
                </div>
                <div class="space-y-2">
                  <?php if (!count($pipeline_cols[$etapa])): ?>
                    <p class="text-sm text-gray-500 bg-white border rounded-lg p-3">Sin contactos en esta etapa.</p>
                  <?php endif; ?>
                  <?php foreach ($pipeline_cols[$etapa] as $c): ?>
                    <article class="kanban-card bg-white rounded-lg p-3 shadow-sm">
                      <div class="flex items-center justify-between gap-3">
                        <div>
                          <p class="font-semibold text-gray-800 leading-tight"><?= h($c['nombre']) ?></p>
                          <p class="text-xs text-gray-500"><?= h($c['empresa'] ?: 'Sin empresa') ?></p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100"><?= h($c['servicio'] ?: 'Servicio') ?></span>
                      </div>
                      <div class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M8 10h8M8 14h5m-9 4h12a2 2 0 0 0 2-2V8l-4-4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
                        Última nota: <?= h($c['ultima_nota'] ?: 'Pendiente de contacto') ?>
                      </div>
                      <div class="mt-2 flex items-center justify-between text-xs text-gray-600">
                        <span class="flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M3 8.5 12 4l9 4.5-9 4.5-9-4.5Zm0 5L12 18l9-4.5"/></svg>
                          Próximo: <?= h($c['proximo']) ?>
                        </span>
                        <span class="text-emerald-600 font-medium"><?= h(etapa_label($c['estado'])) ?></span>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl card-shadow border border-gray-100 p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h4 class="font-semibold text-gray-800">Recordatorios</h4>
          <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Automático</span>
        </div>
        <div class="space-y-2">
          <?php foreach (array_slice($recordatorios, 0, 6) as $r): ?>
            <div class="border rounded-lg p-3 flex items-start justify-between gap-2">
              <div>
                <p class="font-semibold text-gray-800"><?= h($r['cliente']) ?></p>
                <p class="text-xs text-gray-500">Etapa: <?= h($r['estado']) ?> · Último: <?= h(fecha_corta($r['ultima'])) ?: 'N/D' ?></p>
              </div>
              <span class="text-xs px-2 py-1 rounded-full <?= $r['proximo'] <= date('Y-m-d') ? 'bg-amber-100 text-amber-700' : 'bg-indigo-50 text-indigo-700' ?>">Seguimiento <?= h($r['proximo']) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!count($recordatorios)): ?>
            <p class="text-sm text-gray-500">Aún no hay recordatorios generados.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
      <div class="bg-white rounded-2xl card-shadow border border-gray-100 p-5 lg:col-span-2 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase text-gray-500 font-semibold">Gestión rápida</p>
            <h3 class="text-lg font-semibold flex items-center gap-2 text-slate-900">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M5 13a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v4H5Z"/><path stroke-width="1.5" d="M9 9V7a3 3 0 1 1 6 0v2"/></svg>
              Seguimientos sin fricción
            </h3>
          </div>
          <span class="text-xs text-gray-500">1 selección + 1 clic</span>
        </div>

        <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 flex flex-col gap-3">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label class="md:col-span-2 text-sm font-medium text-gray-700">Cliente
              <select id="rapidoCliente" class="mt-1 w-full border rounded-lg p-2">
                <option value="">Selecciona contacto</option>
                <?php foreach ($clientes_pipeline as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" data-etapa="<?= h(etapa_label($c['estado'])) ?>"><?= h($c['nombre']) ?> — <?= h(etapa_label($c['estado'])) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="text-sm font-medium text-gray-700">Añadir nota breve
              <input id="rapidoNota" type="text" class="mt-1 w-full border rounded-lg p-2" placeholder="Opcional" />
            </label>
          </div>
          <div class="flex flex-wrap gap-2">
            <?php
              $atajos = [
                ['cta' => 'Respondió', 'nota' => 'Respondió la llamada. Agendar siguiente paso.', 'proximo' => '+2 days'],
                ['cta' => 'No contesta', 'nota' => 'No contestó. Intentar de nuevo.', 'proximo' => '+1 day'],
                ['cta' => 'Envía info', 'nota' => 'Se envió info / demo. Esperar confirmación.', 'proximo' => '+3 days'],
                ['cta' => 'Interesado', 'nota' => 'Interés confirmado. Preparar propuesta.', 'proximo' => '+2 days'],
                ['cta' => 'Cerrado', 'nota' => 'Venta cerrada. Pasar a onboarding.', 'proximo' => '+7 days'],
              ];
            ?>
            <?php foreach ($atajos as $at): ?>
              <button
                type="button"
                class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm hover:border-blue-300 hover:text-blue-700 transition"
                onclick="gestionarRapido('<?= h($at['cta']) ?>','<?= h($at['nota']) ?>','<?= h($at['proximo']) ?>')">
                <?= h($at['cta']) ?>
              </button>
            <?php endforeach; ?>
          </div>
          <p id="rapidoMsg" class="text-sm text-gray-500"></p>
        </div>

        <div class="border-t pt-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold flex items-center gap-2 text-slate-900">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M5 13a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v4H5Z"/><path stroke-width="1.5" d="M9 9V7a3 3 0 1 1 6 0v2"/></svg>
              Registrar seguimiento detallado
            </h3>
            <span class="text-xs text-gray-500">Última interacción + próximo paso</span>
          </div>
          <form id="formSeguimiento" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="md:col-span-2">
              <label class="text-sm font-medium text-gray-700">Cliente</label>
              <select name="cliente_id" class="mt-1 w-full border rounded-lg p-2" required>
                <option value="">Selecciona un contacto</option>
                <?php foreach ($clientes_pipeline as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?> — <?= h(etapa_label($c['estado'])) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-700">Fecha de contacto</label>
              <input type="date" name="fecha" class="mt-1 w-full border rounded-lg p-2" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-700">Tipo</label>
              <select name="tipo" class="mt-1 w-full border rounded-lg p-2" required>
                <option value="llamada">Llamada</option>
                <option value="mensaje">Mensaje</option>
                <option value="reunión">Reunión</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="text-sm font-medium text-gray-700">Detalle</label>
              <textarea name="nota" class="mt-1 w-full border rounded-lg p-2" rows="2" placeholder="Objetivo, acuerdos, próximas acciones" required></textarea>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-700">Próximo seguimiento</label>
              <input type="date" name="proximo" class="mt-1 w-full border rounded-lg p-2" value="<?= date('Y-m-d', strtotime('+5 days')) ?>">
            </div>
            <div class="md:col-span-2 flex items-center gap-3">
              <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg">Guardar seguimiento</button>
              <p id="seguimientoMsg" class="text-sm text-gray-500"></p>
            </div>
          </form>
        </div>
      </div>

      <div class="bg-white rounded-2xl card-shadow border border-gray-100 p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h4 class="font-semibold text-gray-800">Seguimientos recientes</h4>
          <span class="text-xs text-gray-500">Últimos 12 registros</span>
        </div>
        <div class="space-y-3">
          <?php if (!count($seguimientos)): ?>
            <p class="text-sm text-gray-500">Aún no hay seguimientos registrados.</p>
          <?php endif; ?>
          <?php foreach ($seguimientos as $s): ?>
            <div class="flex items-start gap-3 border rounded-xl p-3">
              <div class="mt-1 bg-blue-100 text-blue-700 w-8 h-8 rounded-full flex items-center justify-center font-semibold">
                <?= strtoupper(substr($s['cliente'] ?: 'C',0,1)) ?>
              </div>
              <div class="flex-1">
                <div class="flex items-center justify-between gap-2">
                  <p class="font-semibold text-gray-800"><?= h($s['cliente'] ?: 'Cliente') ?></p>
                  <span class="text-xs bg-gray-100 px-2 py-1 rounded-full border"><?= h(fecha_corta($s['fecha'])) ?></span>
                </div>
                <p class="text-sm text-gray-700 leading-snug mt-1"><?= h($s['nota']) ?></p>
                <p class="text-xs text-gray-500 mt-1">Etapa: <?= h(etapa_label($s['estado'])) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto bg-white rounded-2xl card-shadow border border-gray-100 p-4">
      <table id="tabla-clientes" class="min-w-full text-sm">
        <thead>
          <tr class="text-gray-700">
            <th class="p-2 text-left">N°</th>
            <th class="p-2 text-left">Creado</th>
            <th class="p-2 text-left">Cliente</th>
            <th class="p-2 text-left">Empresa</th>
            <th class="p-2 text-left">Contacto</th>
            <th class="p-2 text-left">Servicio</th>
            <th class="p-2 text-left">Estado</th>
            <th class="p-2 text-left">Comentario</th>
            <th class="p-2 text-left">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clientes as $c): ?>
            <tr class="border-b" data-estado="<?= h($c['estado']) ?>" data-servicio="<?= h($c['servicio'] ?? '') ?>" data-servicio-id="<?= (int)($c['servicio_id'] ?? 0) ?>">
              <!-- N° (se llena por DataTables) -->
              <td class="p-2"></td>

              <!-- Fecha corta -->
              <td class="p-2"><?= h(fecha_corta($c['creado_en'])) ?></td>

              <td class="p-2">
                <div class="font-semibold text-slate-900"><?= h($c['nombre']) ?></div>
                <?php if ($c['empresa']): ?>
                  <div class="text-xs text-gray-500"><?= h($c['empresa']) ?></div>
                <?php endif; ?>
              </td>
              <td class="p-2"><?= h($c['empresa']) ?></td>
              <td class="p-2 space-y-1">
                <?php if ($c['email']): ?>
                  <div class="inline-flex items-center gap-1 text-gray-700 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-width="1.5" d="M4 6l8 6 8-6M5 6h14a1 1 0 011 1v10a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1z" />
                    </svg>
                    <span><?= h($c['email']) ?></span>
                  </div>
                <?php endif; ?>
                <?php if ($c['telefono']): ?>
                  <div class="inline-flex items-center gap-1 text-gray-700 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-width="1.5" d="M3 5l4-2 4 6-3 2a11 11 0 005 5l2-3 6 4-2 4c-5 .5-10.5-1.5-15-6s-6.5-10-6-15z" />
                    </svg>
                    <span><?= h($c['telefono']) ?></span>
                  </div>
                <?php endif; ?>
              </td>
              <td class="p-2">
                <span class="pill bg-slate-100 text-slate-800 ring-1 ring-slate-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-width="1.5" d="M4 7h16M4 12h16M4 17h10" />
                  </svg>
                  <?= h($c['servicio'] ?? '-') ?>
                </span>
              </td>

              <!-- Estado con badge -->
              <td class="p-2"><?= badge_estado($c['estado']) ?></td>

              <td class="p-2 text-gray-700">
                <div class="line-clamp-2 leading-relaxed"><?= nl2br(h($c['comentario'])) ?></div>
              </td>
              <td class="p-2">
                <div class="flex gap-2">
                  <button
                    onclick='abrirModal(<?= json_encode($c, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'
                    class="bg-amber-500 text-white px-3 py-1.5 rounded-lg inline-flex items-center gap-1 hover:bg-amber-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-width="1.5" d="M15.232 5.232l3.536 3.536M9 11l6.232-6.232a2 2 0 112.828 2.828L11 13l-4 1 1-4z" />
                    </svg>
                    Editar
                  </button>
                  <button
                    onclick='abrirModalEliminar(<?= (int)$c["id"] ?>)'
                    class="bg-red-600 text-white px-3 py-1.5 rounded-lg inline-flex items-center gap-1 hover:bg-red-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-width="1.5" d="M6 7h12M10 11v6M14 11v6M9 7l1-2h4l1 2M5 7h14l-.867 12.142A1 1 0 0117.138 20H6.862a1 1 0 01-.995-.858L5 7z" />
                    </svg>
                    Eliminar
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal agregar/editar -->
  <div id="modalCliente" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50 px-4">
    <div class="bg-white p-6 rounded-2xl w-full max-w-2xl relative card-shadow border border-slate-100">
      <div class="flex items-center gap-3 mb-4">
        <div class="h-10 w-10 bg-blue-50 text-blue-700 rounded-full flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 20a8 8 0 0116 0" />
          </svg>
        </div>
        <div>
          <h3 id="modalTitulo" class="text-xl font-bold text-slate-900">Agregar cliente</h3>
          <p class="text-sm text-gray-500">Completa los datos de contacto y el servicio de interés.</p>
        </div>
      </div>

      <form id="formCliente" class="grid grid-cols-2 gap-4">
        <input type="hidden" name="id">
        <input type="hidden" name="action" value="crear">

        <label class="col-span-2">
          <span class="field-label">Nombre *</span>
          <input type="text" name="nombre" placeholder="Ej. Ana Martínez" class="mt-1 border border-gray-200 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-200" required>
        </label>

        <label class="col-span-2 md:col-span-1">
          <span class="field-label">Empresa</span>
          <input type="text" name="empresa" placeholder="Organización" class="mt-1 border border-gray-200 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-200">
        </label>
        <label class="col-span-2 md:col-span-1">
          <span class="field-label">Teléfono</span>
          <input type="text" name="telefono" placeholder="+34 600 000 000" class="mt-1 border border-gray-200 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-200">
        </label>

        <label class="col-span-2 md:col-span-1">
          <span class="field-label">Email</span>
          <input type="email" name="email" placeholder="correo@cliente.com" class="mt-1 border border-gray-200 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-200">
        </label>
        <label class="col-span-2 md:col-span-1">
          <span class="field-label">Servicio *</span>
          <select name="servicio_id" class="mt-1 border border-gray-200 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-200" required>
            <option value="">Seleccionar servicio</option>
            <?php foreach ($servicios as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= h($s['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="col-span-2 md:col-span-1">
          <span class="field-label">Estado *</span>
          <select name="estado" class="mt-1 border border-gray-200 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-200" required>
            <?php foreach ($estados as $e): ?>
              <option value="<?= h($e) ?>"><?= ucfirst($e) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="col-span-2">
          <span class="field-label">Notas</span>
          <textarea name="comentario" placeholder="Intereses, próximos pasos, fechas clave" class="mt-1 border border-gray-200 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-200" rows="3"></textarea>
        </label>

        <div id="alertaFormulario" class="col-span-2 hidden text-sm rounded-lg p-3"></div>

        <div class="col-span-2 flex flex-col sm:flex-row sm:justify-end gap-2">
          <button type="button" onclick="cerrarModal()" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">Cancelar</button>
          <button type="submit" id="btnGuardar" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition inline-flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.5" d="M5 13l4 4L19 7" />
            </svg>
            Guardar
          </button>
        </div>
      </form>
      <button onclick="cerrarModal()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">&times;</button>
    </div>
  </div>

  <!-- Modal eliminar -->
  <div id="modalEliminar" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50 px-4">
    <div class="bg-white p-6 rounded-2xl card-shadow w-full max-w-md text-center relative border border-slate-100">
      <div class="h-12 w-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center mx-auto mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="1.5" d="M12 9v4m0 4h.01M5.07 19h13.86a1 1 0 00.92-1.39L12.92 4.5a1 1 0 00-1.84 0L4.15 17.61A1 1 0 005.07 19z" />
        </svg>
      </div>
      <h3 class="text-lg font-semibold mb-2">¿Eliminar este cliente?</h3>
      <p class="mb-5 text-gray-600">Se borrará definitivamente su historial de registro.</p>
      <div class="flex justify-center gap-3">
        <button onclick="cerrarModalEliminar()" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">Cancelar</button>
        <button id="btnConfirmarEliminar" class="px-4 py-2 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700 transition">Eliminar</button>
      </div>
      <button onclick="cerrarModalEliminar()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">&times;</button>
    </div>
  </div>

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script>
    let tablaClientes;

    function mostrarToast(mensaje, tipo = 'success') {
      const toast = document.createElement('div');
      const colores = tipo === 'error'
        ? 'bg-red-600 text-white'
        : 'bg-emerald-600 text-white';
      toast.className = `${colores} px-4 py-2 rounded-lg shadow-lg fixed top-4 right-4 z-50 text-sm flex items-center gap-2 animate-fade-in`;
      toast.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="1.5" d="${tipo === 'error' ? 'M6 18L18 6M6 6l12 12' : 'M5 13l4 4L19 7'}" />
        </svg>
        <span>${mensaje}</span>
      `;
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 3200);
    }

    // DataTable con numeración en la 1ra columna
    $(function () {
      tablaClientes = $('#tabla-clientes').DataTable({
        pageLength: 10,
        order: [[1, 'desc']], // por "Creado" (col 1)
        language: { url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        columnDefs: [
          { targets: -1, orderable: false, searchable: false }, // Acciones
          { targets: 0, orderable: false, searchable: false }   // N°
        ],
        drawCallback: function(settings){
          // rellena N° (respetando paginación)
          const api = this.api();
          const start = api.page.info().start;
          api.column(0, {search:'applied', order:'applied', page:'current'}).nodes().each(function(cell, i){
            cell.innerHTML = start + i + 1;
          });
        }
      });

      // Filtro por estado y servicio (cliente-side sobre el resultado ya acotado en el servidor)
      $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-clientes') return true;
        const estadoFiltro = (document.getElementById('filtroEstado').value || '').toLowerCase();
        const servicioFiltro = (document.getElementById('filtroServicio').value || '').toString();
        const rowNode = tablaClientes.row(dataIndex).node();
        const estadoRow = (rowNode.dataset.estado || '').toLowerCase();
        const servicioRowId = (rowNode.dataset.servicioId || '').toString();
        if (estadoFiltro && estadoRow !== estadoFiltro) return false;
        if (servicioFiltro && servicioRowId !== servicioFiltro) return false;
        return true;
      });

      $('#filtroEstado, #filtroServicio').on('change', () => tablaClientes.draw());
    });

    function filtrarMes(){
      const mes = document.getElementById('filtroMes').value || '';
      const estado = document.getElementById('filtroEstado').value || '';
      const servicio = document.getElementById('filtroServicio').value || '';
      const busqueda = document.getElementById('filtroBusqueda').value || '';
      const url = new URL(window.location.href);
      if (mes) url.searchParams.set('mes', mes); else url.searchParams.delete('mes');
      if (estado) url.searchParams.set('estado', estado); else url.searchParams.delete('estado');
      if (servicio) url.searchParams.set('servicio', servicio); else url.searchParams.delete('servicio');
      if (busqueda) url.searchParams.set('q', busqueda); else url.searchParams.delete('q');
      window.location.href = url.toString();
    }

    document.getElementById('filtroBusqueda').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        filtrarMes();
      }
    });

    // Gestión rápida: mínima fricción
    const msgRapido = document.getElementById('rapidoMsg');
    function formatoFecha(date) {
      return date.toISOString().slice(0, 10);
    }
    function sumarDias(base, offset) {
      const d = new Date(base);
      d.setDate(d.getDate() + offset);
      return d;
    }
    async function gestionarRapido(etiqueta, notaBase, proximoOffset) {
      const clienteId = document.getElementById('rapidoCliente').value;
      const notaExtra = document.getElementById('rapidoNota').value.trim();
      msgRapido.textContent = '';
      if (!clienteId) {
        msgRapido.textContent = 'Selecciona un cliente para registrar la gestión.';
        return;
      }

      const hoy = new Date();
      const fecha = formatoFecha(hoy);
      const offsetNumber = parseInt((proximoOffset || '+2 days').replace(/[^\d-]/g, ''), 10) || 2;
      const proximo = formatoFecha(sumarDias(hoy, offsetNumber));
      const nota = notaExtra ? `${notaBase} ${notaExtra}` : notaBase;

      msgRapido.textContent = 'Guardando...';
      const fd = new FormData();
      fd.append('cliente_id', clienteId);
      fd.append('fecha', fecha);
      fd.append('tipo', etiqueta.toLowerCase());
      fd.append('nota', nota);
      fd.append('proximo', proximo);

      try {
        const res = await fetch('../controllers/seguimiento_nuevo.php', { method:'POST', body: fd });
        const json = await res.json();
        msgRapido.textContent = json.message || 'Listo';
        msgRapido.classList.toggle('text-emerald-600', !!json.success);
        if (json.success) {
          document.getElementById('rapidoNota').value = '';
        }
      } catch (err) {
        msgRapido.textContent = 'No se pudo guardar la gestión rápida.';
        msgRapido.classList.remove('text-emerald-600');
      }
    }

    // Modal alta/edición
    function abrirModal(data = {}) {
      const modal = document.getElementById('modalCliente');
      const form  = document.getElementById('formCliente');
      const alerta = document.getElementById('alertaFormulario');
      const titulo = document.getElementById('modalTitulo');
      const btnGuardar = document.getElementById('btnGuardar');

      alerta.classList.add('hidden');
      alerta.textContent = '';
      form.reset();

      form.action.value      = data.id ? 'actualizar' : 'crear';
      form.id.value          = data.id || '';
      form.nombre.value      = data.nombre || '';
      form.empresa.value     = data.empresa || '';
      form.email.value       = data.email || '';
      form.telefono.value    = data.telefono || '';
      form.servicio_id.value = data.servicio_id || '';
      form.estado.value      = data.estado || 'en negociación';
      form.comentario.value  = data.comentario || '';
      titulo.textContent     = data.id ? 'Editar cliente' : 'Agregar cliente';
      btnGuardar.disabled    = false;
      btnGuardar.innerHTML   = `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="1.5" d="M5 13l4 4L19 7" />
        </svg>
        <span>Guardar</span>`;

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      form.onsubmit = async (e) => {
        e.preventDefault();
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="animate-pulse">Guardando...</span>';
        alerta.className = 'col-span-2 hidden text-sm rounded-lg p-3';

        const fd = new FormData(form);
        const res = await fetch('../controllers/clientes_controller.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
          cerrarModal();
          mostrarToast(data.id ? 'Cliente actualizado' : 'Cliente creado');
          location.reload();
        } else {
          alerta.textContent = json.message || 'Error al guardar';
          alerta.classList.remove('hidden');
          alerta.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-100');
          btnGuardar.disabled = false;
          btnGuardar.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.5" d="M5 13l4 4L19 7" />
            </svg>
            <span>Reintentar</span>`;
        }
      };
    }
    function cerrarModal(){
      const modal = document.getElementById('modalCliente');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    // Modal eliminar
    let idEliminar = null;
    function abrirModalEliminar(id){
      idEliminar = id;
      const modal = document.getElementById('modalEliminar');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
    function cerrarModalEliminar(){
      idEliminar = null;
      const modal = document.getElementById('modalEliminar');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    document.getElementById('btnConfirmarEliminar').addEventListener('click', async function () {
      if (!idEliminar) return;
      const fd = new FormData();
      fd.append('action', 'eliminar');
      fd.append('id', idEliminar);
      const res = await fetch('../controllers/clientes_controller.php', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        cerrarModalEliminar();
        mostrarToast('Cliente eliminado');
        location.reload();
      } else {
        mostrarToast(json.message || 'No se pudo eliminar', 'error');
      }
    });

    const formSeg = document.getElementById('formSeguimiento');
    if (formSeg) {
      formSeg.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = document.getElementById('seguimientoMsg');
        msg.textContent = 'Guardando...';
        msg.classList.remove('text-emerald-600');
        const fd = new FormData(formSeg);
        const res = await fetch('../controllers/seguimiento_nuevo.php', { method:'POST', body: fd });
        const json = await res.json();
        msg.textContent = json.message || '';
        msg.classList.toggle('text-emerald-600', !!json.success);
        if (json.success) formSeg.reset();
      });
    }
  </script>
</body>
</html>
