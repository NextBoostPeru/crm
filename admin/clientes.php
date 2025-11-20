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
  </style>
</head>
<body class="bg-gray-100">
  <?php include '../includes/sidebar_admin.php'; ?>

  <div class="p-4 lg:ml-64">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4">
      <h2 class="text-2xl font-bold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 20a8 8 0 0116 0"/>
        </svg>
        Clientes
      </h2>

      <button onclick="abrirModal()" class="bg-blue-600 text-white px-4 py-2 rounded mt-2 sm:mt-0 inline-flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="1.5" d="M12 5v14M5 12h14"/>
        </svg>
        Nuevo cliente
      </button>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
      <input type="month" id="filtroMes" class="border p-2 rounded" value="<?= h($mes_actual) ?>">
      <button onclick="filtrarMes()" class="bg-gray-800 text-white px-4 rounded">Filtrar</button>
    </div>

    <div class="overflow-x-auto">
      <table id="tabla-clientes" class="min-w-full bg-white rounded shadow text-sm">
        <thead>
          <tr class="text-gray-700">
            <th class="p-2 text-left">N°</th>
            <th class="p-2 text-left">Creado</th>
            <th class="p-2 text-left">Nombre</th>
            <th class="p-2 text-left">Empresa</th>
            <th class="p-2 text-left">Email</th>
            <th class="p-2 text-left">Teléfono</th>
            <th class="p-2 text-left">Servicio</th>
            <th class="p-2 text-left">Estado</th>
            <th class="p-2 text-left">Comentario</th>
            <th class="p-2 text-left">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clientes as $c): ?>
            <tr class="border-b">
              <!-- N° (se llena por DataTables) -->
              <td class="p-2"></td>

              <!-- Fecha corta -->
              <td class="p-2"><?= h(fecha_corta($c['creado_en'])) ?></td>

              <td class="p-2"><?= h($c['nombre']) ?></td>
              <td class="p-2"><?= h($c['empresa']) ?></td>
              <td class="p-2"><?= h($c['email']) ?></td>
              <td class="p-2"><?= h($c['telefono']) ?></td>
              <td class="p-2"><?= h($c['servicio'] ?? '-') ?></td>

              <!-- Estado con badge -->
              <td class="p-2"><?= badge_estado($c['estado']) ?></td>

              <td class="p-2"><?= nl2br(h($c['comentario'])) ?></td>
              <td class="p-2">
                <div class="flex gap-2">
                  <button
                    onclick='abrirModal(<?= json_encode($c, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'
                    class="bg-yellow-500 text-white px-2 py-1 rounded">Editar</button>
                  <button
                    onclick='abrirModalEliminar(<?= (int)$c["id"] ?>)'
                    class="bg-red-600 text-white px-2 py-1 rounded">Eliminar</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal agregar/editar -->
  <div id="modalCliente" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white p-6 rounded w-full max-w-xl relative">
      <h3 class="text-xl font-bold mb-4">Agregar / Editar Cliente</h3>
      <form id="formCliente" class="grid grid-cols-2 gap-4">
        <input type="hidden" name="id">
        <input type="hidden" name="action" value="crear">

        <input type="text" name="nombre" placeholder="Nombre" class="border p-2 rounded col-span-2" required>
        <input type="text" name="empresa" placeholder="Empresa" class="border p-2 rounded col-span-2">
        <input type="email" name="email" placeholder="Email" class="border p-2 rounded col-span-2">
        <input type="text" name="telefono" placeholder="Teléfono" class="border p-2 rounded col-span-2">

        <select name="servicio_id" class="border p-2 rounded col-span-2" required>
          <option value="">Seleccionar servicio</option>
          <?php foreach ($servicios as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= h($s['nombre']) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="estado" class="border p-2 rounded col-span-2" required>
          <?php foreach ($estados as $e): ?>
            <option value="<?= h($e) ?>"><?= ucfirst($e) ?></option>
          <?php endforeach; ?>
        </select>

        <textarea name="comentario" placeholder="Comentario" class="border p-2 rounded col-span-2"></textarea>

        <button type="submit" class="bg-green-600 text-white py-2 rounded col-span-2">Guardar</button>
      </form>
      <button onclick="cerrarModal()" class="absolute top-2 right-2 text-gray-600 hover:text-black">&times;</button>
    </div>
  </div>

  <!-- Modal eliminar -->
  <div id="modalEliminar" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white p-6 rounded shadow w-full max-w-md text-center relative">
      <h3 class="text-lg font-semibold mb-4">¿Eliminar este cliente?</h3>
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
    // DataTable con numeración en la 1ra columna
    $(function () {
      const table = $('#tabla-clientes').DataTable({
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

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      form.onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const res = await fetch('../controllers/clientes_controller.php', { method: 'POST', body: fd });
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
        location.reload();
      } else {
        alert(json.message || 'No se pudo eliminar.');
      }
    });
  </script>
</body>
</html>
