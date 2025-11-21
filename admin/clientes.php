<?php
// admin/clientes.php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$mes_actual = isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes']) ? $_GET['mes'] : date('Y-m');

// Catálogo
$servicios = $pdo->query("SELECT id, nombre FROM servicios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$estados   = ['en negociación', 'cerrado', 'interesado', 'nuevo'];

// Listado (por mes, como ventas.php)
$stmt = $pdo->prepare("SELECT c.id, c.nombre, c.empresa, c.email, c.telefono, c.estado, c.comentario, c.creado_en,
                              c.servicio_id, s.nombre AS servicio
                       FROM clientes c
                       LEFT JOIN servicios s ON s.id = c.servicio_id
                       WHERE DATE_FORMAT(c.creado_en, '%Y-%m') = ?
                       ORDER BY c.creado_en DESC, c.id DESC");
$stmt->execute([$mes_actual]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <label class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-gray-700">Mes</span>
          <input type="month" id="filtroMes" class="border border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-200" value="<?= h($mes_actual) ?>">
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-gray-700">Estado</span>
          <select id="filtroEstado" class="border border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-200">
            <option value="">Todos</option>
            <?php foreach ($estados as $estado): ?>
              <option value="<?= h($estado) ?>"><?= ucfirst($estado) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-gray-700">Servicio</span>
          <select id="filtroServicio" class="border border-gray-200 p-2 rounded-lg focus:ring-2 focus:ring-blue-200">
            <option value="">Todos</option>
            <?php foreach ($servicios as $s): ?>
              <option value="<?= h($s['nombre']) ?>"><?= h($s['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
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
            <tr class="border-b" data-estado="<?= h($c['estado']) ?>" data-servicio="<?= h($c['servicio'] ?? '') ?>">
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

      // Filtro por estado y servicio
      $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'tabla-clientes') return true;
        const estadoFiltro = (document.getElementById('filtroEstado').value || '').toLowerCase();
        const servicioFiltro = (document.getElementById('filtroServicio').value || '').toLowerCase();
        const rowNode = tablaClientes.row(dataIndex).node();
        const estadoRow = (rowNode.dataset.estado || '').toLowerCase();
        const servicioRow = (rowNode.dataset.servicio || '').toLowerCase();
        if (estadoFiltro && estadoRow !== estadoFiltro) return false;
        if (servicioFiltro && servicioRow !== servicioFiltro) return false;
        return true;
      });

      $('#filtroEstado, #filtroServicio').on('change', () => tablaClientes.draw());
    });

    function filtrarMes(){
      const mes = document.getElementById('filtroMes').value || '';
      const url = new URL(window.location.href);
      if (mes) url.searchParams.set('mes', mes); else url.searchParams.delete('mes');
      window.location.href = url.toString();
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
  </script>
</body>
</html>
