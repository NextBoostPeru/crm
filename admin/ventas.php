<?php
// admin/ventas.php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$mes_actual = isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes']) ? $_GET['mes'] : date('Y-m');

// Catálogos
$servicios = $pdo->query("SELECT id, nombre FROM servicios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$estados_pago = ['pendiente', 'pagado'];

// Listado de ventas por mes (vista principal)
$stmt = $pdo->prepare("
  SELECT v.id, v.monto, v.fecha, v.estado_pago,
         s.nombre AS servicio,
         c.id AS cliente_id, c.nombre AS cliente
  FROM ventas v
  LEFT JOIN servicios s ON s.id = v.servicio_id
  LEFT JOIN clientes  c ON c.id = v.cliente_id
  WHERE DATE_FORMAT(v.fecha, '%Y-%m') = ?
  ORDER BY v.fecha DESC, v.id DESC
");
$stmt->execute([$mes_actual]);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clientes para pipeline y recordatorios
$clientes_stmt = $pdo->query("
  SELECT c.id, c.nombre, c.empresa, c.email, c.telefono, c.estado, c.servicio_id,
         s.nombre AS servicio,
         (SELECT MAX(fecha) FROM seguimientos WHERE cliente_id = c.id) AS ultima_interaccion,
         (SELECT nota FROM seguimientos WHERE cliente_id = c.id ORDER BY fecha DESC, id DESC LIMIT 1) AS ultima_nota
  FROM clientes c
  LEFT JOIN servicios s ON s.id = c.servicio_id
  ORDER BY c.nombre
");
$clientes_pipeline = $clientes_stmt->fetchAll(PDO::FETCH_ASSOC);

$seg_stmt = $pdo->query("
  SELECT s.id, s.fecha, s.nota, c.nombre AS cliente, c.estado
  FROM seguimientos s
  LEFT JOIN clientes c ON c.id = s.cliente_id
  ORDER BY s.fecha DESC, s.id DESC
  LIMIT 12
");
$seguimientos = $seg_stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fecha_corta($d){ return $d ? date('Y-m-d', strtotime($d)) : ''; }
function badge_estado($estado){
  $e = mb_strtolower(trim((string)$estado));
  $map = [
    'pagado'    => 'bg-green-100 text-green-800 ring-green-200',
    'pendiente' => 'bg-amber-100 text-amber-800 ring-amber-200',
  ];
  $cls = $map[$e] ?? 'bg-gray-100 text-gray-800 ring-gray-200';
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
  <title>Ventas y Seguimientos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

  <style>
    table.dataTable thead th { background: #e5e7eb; }
    table.dataTable tbody tr:hover { background: #f9fafb; }
    .dt-container .dataTables_length select,
    .dt-container .dataTables_filter input { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .25rem .5rem; }

    .ac-wrap { position: relative; }
    .ac-list {
      position: absolute; top: 100%; left: 0; right: 0;
      background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem;
      margin-top: .25rem; max-height: 280px; overflow: auto; z-index: 60;
      box-shadow: 0 8px 24px rgba(0,0,0,.08);
    }
    .ac-item { padding: .5rem .75rem; cursor: pointer; }
    .ac-item:hover, .ac-item.active { background: #f3f4f6; }
    .ac-empty { padding: .75rem; color: #6b7280; }

    .kanban-col { min-width: 260px; }
    .kanban-card { border: 1px solid #e5e7eb; }
  </style>
</head>
<body class="bg-gray-100">

  <?php include '../includes/sidebar_admin.php'; ?>

  <div class="p-4 lg:ml-64">

    <!-- Encabezado hero -->
    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 text-white rounded-2xl p-6 mb-6 shadow">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
          <p class="text-sm uppercase tracking-wide text-blue-100 mb-1">CRM Avanzado · Seguimientos y oportunidades</p>
          <h1 class="text-3xl font-bold">Pipeline visual: Lead → Prospecto → Cliente → Fidelización</h1>
          <p class="text-blue-100 mt-2 max-w-2xl">Monitorea el avance comercial, registra llamadas, mensajes o reuniones y activa recordatorios automáticos de seguimiento.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <button onclick="abrirModal()" class="bg-white text-indigo-700 px-4 py-2 rounded-lg font-semibold shadow flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M12 5v14M5 12h14"/></svg>
            Nueva venta
          </button>
          <a href="#seguimiento" class="border border-white/60 px-4 py-2 rounded-lg font-semibold hover:bg-white hover:text-indigo-700">Registrar seguimiento</a>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
        <?php foreach ($etapas as $et): $count = count($pipeline_cols[$et]); ?>
          <div class="bg-white/10 rounded-xl p-4 backdrop-blur border border-white/15">
            <p class="text-sm text-blue-100 flex items-center gap-2">
              <span class="inline-block w-2 h-2 rounded-full bg-white"></span> <?= h($et) ?>
            </p>
            <p class="text-3xl font-semibold mt-2"><?= $count ?></p>
            <p class="text-xs text-blue-100">Contactos en etapa</p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Embudo y ritmo de captación -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
      <div class="col-span-2 bg-white rounded-2xl shadow p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M4 6h16M6 12h12M10 18h4"/></svg>
            Pipeline visual
          </h2>
          <span class="text-sm text-gray-500">Arrastra mentalmente Lead → Fidelización</span>
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

      <div class="bg-white rounded-2xl shadow p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-gray-800 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M5 5h14v14H5z"/></svg>
            Ritmo de captación
          </h3>
          <span class="text-xs text-gray-500">Automático</span>
        </div>
        <div class="space-y-2">
          <?php foreach ($etapas as $et): ?>
            <?php $avance = min(100, count($pipeline_cols[$et]) * 12); ?>
            <div>
              <div class="flex justify-between text-xs text-gray-600 mb-1">
                <span><?= h($et) ?></span>
                <span><?= $avance ?>%</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="h-2 rounded-full bg-indigo-500" style="width: <?= $avance ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-100 text-sm text-indigo-700">
          Recordatorios automáticos activados para próximos 7 días.
        </div>
      </div>
    </div>

    <!-- Seguimientos y recordatorios -->
    <div id="seguimiento" class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
      <div class="lg:col-span-2 bg-white rounded-2xl shadow p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-semibold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M5 13a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v4H5Z"/><path stroke-width="1.5" d="M9 9V7a3 3 0 1 1 6 0v2"/></svg>
            Registrar llamada / mensaje / reunión
          </h3>
          <span class="text-xs text-gray-500">Actualiza la última interacción</span>
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
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" id="toggleAuto" checked>
            <label for="toggleAuto">Crear recordatorio automático</label>
          </div>
          <div class="md:col-span-2 flex items-center gap-3">
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Guardar seguimiento</button>
            <p id="seguimientoMsg" class="text-sm text-gray-500"></p>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-2xl shadow p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h4 class="font-semibold text-gray-800">Recordatorios automáticos</h4>
          <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">ON</span>
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
        </div>
      </div>
    </div>

    <!-- Línea de tiempo -->
    <div class="bg-white rounded-2xl shadow p-5 mb-6">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-semibold flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.5" d="M7 7h10M7 12h6m-8 5h12"/></svg>
          Seguimientos recientes
        </h3>
        <span class="text-xs text-gray-500">Últimos 12 registros</span>
      </div>
      <div class="space-y-3">
        <?php if (!count($seguimientos)): ?>
          <p class="text-sm text-gray-500">Aún no hay seguimientos registrados.</p>
        <?php endif; ?>
        <?php foreach ($seguimientos as $s): ?>
          <div class="flex items-start gap-3 border rounded-xl p-3">
            <div class="mt-1 bg-indigo-100 text-indigo-700 w-8 h-8 rounded-full flex items-center justify-center font-semibold">
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

    <!-- Ventas (tabla) -->
    <div class="bg-white rounded-2xl shadow p-5">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-3">
        <h2 class="text-xl font-bold flex items-center gap-2 text-gray-800">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="1.5" d="M3 20h18M7 16v-5M12 16V8M17 16v-3"/>
          </svg>
          Ventas registradas
        </h2>
        <div class="flex flex-wrap gap-2 items-center">
          <input type="month" id="filtroMes" class="border p-2 rounded" value="<?= h($mes_actual) ?>">
          <button onclick="filtrarMes()" class="bg-gray-800 text-white px-4 rounded">Filtrar</button>
          <button onclick="abrirModal()" class="bg-indigo-600 text-white px-4 py-2 rounded inline-flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-width="1.5" d="M12 5v14M5 12h14"/>
            </svg>
            Nueva venta
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table id="tabla-ventas" class="min-w-full bg-white rounded shadow text-sm">
          <thead>
            <tr class="text-gray-700">
              <th class="p-2 text-left">N°</th>
              <th class="p-2 text-left">Fecha</th>
              <th class="p-2 text-left">Cliente</th>
              <th class="p-2 text-left">Servicio</th>
              <th class="p-2 text-left">Monto (S/)</th>
              <th class="p-2 text-left">Estado</th>
              <th class="p-2 text-left">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ventas as $v): ?>
              <tr class="border-b">
                <td class="p-2"></td>
                <td class="p-2"><?= h(fecha_corta($v['fecha'])) ?></td>
                <td class="p-2 font-semibold text-gray-800"><?= h($v['cliente'] ?? '-') ?></td>
                <td class="p-2 text-gray-700"><?= h($v['servicio'] ?? '-') ?></td>
                <td class="p-2 font-medium"><?= number_format((float)$v['monto'], 2) ?></td>
                <td class="p-2"><?= badge_estado($v['estado_pago']) ?></td>
                <td class="p-2">
                  <div class="flex gap-2">
                    <button class="px-3 py-1 rounded bg-white border border-amber-200 text-amber-700 hover:bg-amber-50"
                            onclick='abrirModal(<?= json_encode($v, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Editar</button>
                    <button class="px-3 py-1 rounded bg-white border border-red-200 text-red-700 hover:bg-red-50"
                            onclick='abrirModalEliminar(<?= (int)$v["id"] ?>)'>Eliminar</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Modal Agregar/Editar -->
  <div id="modalVenta" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl w-full max-w-xl relative shadow-lg">
      <div class="flex items-center justify-between mb-2">
        <div>
          <p class="text-xs text-gray-500">Lead → Prospecto → Cliente</p>
          <h3 class="text-xl font-bold">Agregar / Editar venta</h3>
        </div>
        <button onclick="cerrarModal()" class="text-gray-500 hover:text-black">&times;</button>
      </div>

      <form id="formVenta" class="grid grid-cols-2 gap-4">
        <input type="hidden" name="id">
        <input type="hidden" name="action" value="crear">

        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
          <div class="ac-wrap">
            <input type="hidden" name="cliente_id" />
            <input type="text" id="cliente_buscar" class="border p-2 rounded w-full" placeholder="Escribe 3 letras o más..." autocomplete="off">
            <div id="acCliente" class="ac-list hidden"></div>
          </div>
          <p id="cliente_help" class="text-xs text-gray-500 mt-1">Busca por nombre, empresa o email.</p>
        </div>

        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Servicio</label>
          <select name="servicio_id" class="border p-2 rounded w-full" required>
            <option value="">Seleccionar servicio</option>
            <?php foreach ($servicios as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= h($s['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Monto (S/)</label>
          <input type="number" step="0.01" min="0" name="monto" class="border p-2 rounded w-full" required>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
          <input type="date" name="fecha" class="border p-2 rounded w-full" required>
        </div>

        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Estado de pago</label>
          <select name="estado_pago" class="border p-2 rounded w-full" required>
            <?php foreach ($estados_pago as $e): ?>
              <option value="<?= h($e) ?>"><?= ucfirst($e) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="bg-green-600 text-white py-2 rounded col-span-2">Guardar</button>
      </form>
    </div>
  </div>

  <!-- Modal Eliminar -->
  <div id="modalEliminar" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white p-6 rounded shadow w-full max-w-md text-center relative">
      <h3 class="text-lg font-semibold mb-4">¿Eliminar esta venta?</h3>
      <p class="mb-4 text-gray-600">Esta acción no se puede deshacer.</p>
      <div class="flex justify-center gap-4">
        <button onclick="cerrarModalEliminar()" class="bg-gray-300 px-4 py-2 rounded">Cancelar</button>
        <button id="btnConfirmarEliminar" class="bg-red-600 text-white px-4 py-2 rounded">Eliminar</button>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script>
    // DataTable con numeración
    $(function () {
      const table = $('#tabla-ventas').DataTable({
        pageLength: 10,
        order: [[1, 'desc']],
        language: { url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        columnDefs: [
          { targets: 0, orderable: false, searchable: false },
          { targets: -1, orderable: false, searchable: false }
        ],
        drawCallback: function(settings){
          const api = this.api();
          const start = api.page.info().start;
          api.column(0, {search:'applied', order:'applied', page:'current'}).nodes().each(function(cell, i){
            cell.innerHTML = start + i + 1;
          });
        }
      });
    });

    function filtrarMes(){
      const mes = document.getElementById('filtroMes').value || '';
      const url = new URL(window.location.href);
      if (mes) url.searchParams.set('mes', mes); else url.searchParams.delete('mes');
      window.location.href = url.toString();
    }

    // ---------- Modal Agregar/Editar ----------
    function abrirModal(data = {}) {
      const modal = document.getElementById('modalVenta');
      const form  = document.getElementById('formVenta');
      form.reset();

      form.action.value   = data.id ? 'actualizar' : 'crear';
      form.id.value       = data.id || '';

      form.cliente_id.value = data.cliente_id || '';
      document.getElementById('cliente_buscar').value = data.cliente || '';

      form.servicio_id.value = data.servicio_id || '';
      form.monto.value       = data.monto || '';
      form.fecha.value       = data.fecha ? (new Date(data.fecha).toISOString().slice(0,10)) : '';
      form.estado_pago.value = data.estado_pago || 'pendiente';

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      form.onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        if (!fd.get('cliente_id')) {
          alert('Selecciona un cliente de las sugerencias.');
          return;
        }
        const res = await fetch('../controllers/ventas_controller.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
          cerrarModal();
          location.reload();
        } else {
          alert(json.message || 'Error al guardar');
        }
      };
    }
    function cerrarModal(){
      const modal = document.getElementById('modalVenta');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    // ---------- Modal Eliminar ----------
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
      const res = await fetch('../controllers/ventas_controller.php', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        cerrarModalEliminar();
        location.reload();
      } else {
        alert(json.message || 'No se pudo eliminar.');
      }
    });

    // ---------- Autocomplete Cliente (AJAX con debounce) ----------
    const ac = {
      wrap: null, list: null, input: null, hidden: null, timer: null, items: [], index: -1
    };

    document.addEventListener('DOMContentLoaded', () => {
      ac.wrap  = document.querySelector('.ac-wrap');
      ac.list  = document.getElementById('acCliente');
      ac.input = document.getElementById('cliente_buscar');
      ac.hidden= document.querySelector('input[name="cliente_id"]');

      ac.input.addEventListener('input', onType);
      ac.input.addEventListener('keydown', onKey);
      document.addEventListener('click', (e) => {
        if (!ac.wrap.contains(e.target)) hideList();
      });

      // Seguimiento form
      const formSeg = document.getElementById('formSeguimiento');
      formSeg.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = document.getElementById('seguimientoMsg');
        msg.textContent = 'Guardando...';
        const fd = new FormData(formSeg);
        const res = await fetch('../controllers/seguimiento_nuevo.php', { method:'POST', body: fd });
        const json = await res.json();
        msg.textContent = json.message || '';
        msg.classList.toggle('text-emerald-600', !!json.success);
        if (json.success) formSeg.reset();
      });
    });

    function onType(){
      const q = ac.input.value.trim();
      ac.hidden.value = '';
      if (ac.timer) clearTimeout(ac.timer);
      if (q.length < 3) { hideList(); return; }

      ac.timer = setTimeout(async () => {
        try {
          const url = '../api/clientes_buscar.php?q=' + encodeURIComponent(q);
          const res = await fetch(url);
          const json = await res.json();
          if (!json || !Array.isArray(json.data)) { renderList([]); return; }
          renderList(json.data);
        } catch {
          renderList([]);
        }
      }, 250);
    }

    function renderList(items){
      ac.items = items;
      ac.index = -1;
      if (!items.length) {
        ac.list.innerHTML = '<div class="ac-empty">Sin resultados</div>';
          it.empresa ? `  ${it.empresa}` : '',
          it.email ? `  ${it.email}` : ''
      }
      ac.input.value  = it.nombre + (it.empresa ? '  ' + it.empresa : '');
        const linea = [
          it.nombre || '',
          it.empresa ? ` · ${it.empresa}` : '',
          it.email ? ` · ${it.email}` : ''
        ].join('');
        return `<div class="ac-item" data-i="${i}">${linea}</div>`;
      }).join('');
      ac.list.classList.remove('hidden');

      // Click selección
      ac.list.querySelectorAll('.ac-item').forEach(el => {
        el.addEventListener('click', () => {
          const i = parseInt(el.getAttribute('data-i'));
          choose(i);
        });
      });
    }

    function onKey(e){
      if (ac.list.classList.contains('hidden')) return;
      const max = ac.items.length - 1;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        ac.index = Math.min(max, ac.index + 1);
        highlight();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        ac.index = Math.max(0, ac.index - 1);
        highlight();
      } else if (e.key === 'Enter') {
        if (ac.index >= 0) {
          e.preventDefault();
          choose(ac.index);
        }
      } else if (e.key === 'Escape') {
        hideList();
      }
    }

    function highlight(){
      ac.list.querySelectorAll('.ac-item').forEach((el, i) => {
        el.classList.toggle('active', i === ac.index);
        if (i === ac.index) el.scrollIntoView({ block: 'nearest' });
      });
    }

    function choose(i){
      const it = ac.items[i];
      if (!it) return;
      ac.hidden.value = it.id;
      ac.input.value  = it.nombre + (it.empresa ? ' · ' + it.empresa : '');
      hideList();
    }

    function hideList(){
      ac.list.classList.add('hidden');
      ac.list.innerHTML = '';
      ac.items = [];
      ac.index = -1;
    }
  </script>
</body>
</html>
