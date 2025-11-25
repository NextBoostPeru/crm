<?php require_once '../includes/auth.php';
require_once '../includes/db.php';

if (($_SESSION['rol'] ?? '') !== 'admin') {
  header('Location: ../index.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Servicios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <?php include '../includes/sidebar_admin.php'; ?>
  <div class="md:ml-64 p-4 md:p-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-sm text-gray-500">Organiza los servicios que ofreces</p>
        <h1 class="text-2xl font-bold flex items-center gap-2 text-gray-900">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M4 7h16M4 12h16M4 17h16"/>
            <path d="M8 7v10m4-10v10m4-10v10" opacity=".5"/>
          </svg>
          Servicios
        </h1>
      </div>
      <button onclick="abrirModal()" class="bg-indigo-600 text-white px-4 py-2 rounded shadow inline-flex items-center gap-2 hover:bg-indigo-700 transition">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M12 5v14M5 12h14"/>
        </svg>
        Nuevo servicio
      </button>
    </div>

    <div class="bg-white rounded shadow p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2 text-gray-800 font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M5 12h14M12 5v14"/>
          </svg>
          Catálogo de servicios
        </div>
        <span id="totalServicios" class="text-xs text-gray-500"></span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left" id="tablaServicios">
          <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
            <tr>
              <th class="px-3 py-2">#</th>
              <th class="px-3 py-2">Nombre</th>
              <th class="px-3 py-2">Creado</th>
              <th class="px-3 py-2 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal Crear/Editar -->
  <div id="modalServicio" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-md p-6">
      <div class="flex items-start justify-between mb-4">
        <div>
          <p class="text-sm text-gray-500" id="modalSub">Define un nuevo servicio</p>
          <h3 class="text-xl font-semibold text-gray-900" id="modalTitle">Nuevo servicio</h3>
        </div>
        <button onclick="cerrarModal()" class="text-gray-500 hover:text-gray-800">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M6 6l12 12M6 18L18 6"/>
          </svg>
        </button>
      </div>
      <form id="formServicio" class="space-y-3">
        <input type="hidden" name="id">
        <input type="hidden" name="action" value="crear">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del servicio</label>
          <input type="text" name="nombre" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
        </div>
        <div id="alertaServicio" class="hidden text-sm"></div>
        <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded shadow hover:bg-indigo-700 transition">Guardar</button>
      </form>
    </div>
  </div>

  <!-- Modal Eliminar -->
  <div id="modalEliminar" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-md p-6 text-center">
      <div class="flex justify-center mb-3">
        <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M12 9v4m0 4h.01"/>
            <path d="M4.93 4.93a10 10 0 1 1 14.14 14.14A10 10 0 0 1 4.93 4.93Z"/>
          </svg>
        </div>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 mb-2">Eliminar servicio</h3>
      <p class="text-sm text-gray-600 mb-4">Esta acción no se puede deshacer.</p>
      <div class="flex justify-center gap-3">
        <button onclick="cerrarModalEliminar()" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
        <button id="btnConfirmarEliminar" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">Eliminar</button>
      </div>
    </div>
  </div>

  <script>
    const modal = document.getElementById('modalServicio');
    const form = document.getElementById('formServicio');
    const alerta = document.getElementById('alertaServicio');
    const modalTitle = document.getElementById('modalTitle');
    const modalSub = document.getElementById('modalSub');
    const tbody = document.querySelector('#tablaServicios tbody');
    const totalServicios = document.getElementById('totalServicios');

    async function cargarServicios() {
      const res = await fetch('../controllers/servicios_controller.php');
      const json = await res.json();
      if (!json.success) return;
      tbody.innerHTML = '';
      totalServicios.textContent = `Total: ${json.data.length}`;
      if (!json.data.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Aún no hay servicios registrados.</td></tr>';
        return;
      }
      json.data.forEach((s, idx) => {
        const tr = document.createElement('tr');
        tr.className = 'border-b last:border-0 hover:bg-gray-50';
        tr.innerHTML = `
          <td class="px-3 py-2 text-gray-600">${idx + 1}</td>
          <td class="px-3 py-2 font-medium text-gray-900">${escapeHtml(s.nombre)}</td>
          <td class="px-3 py-2 text-gray-700">${formatDate(s.creado_en)}</td>
          <td class="px-3 py-2">
            <div class="flex items-center justify-center gap-2">
              <button data-edit class="bg-amber-500 hover:bg-amber-600 text-white px-2 py-1 rounded text-xs inline-flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M4 20h4l10-10a1.5 1.5 0 0 0-3-3L5 17l-1 4Z"/>
                </svg>
                Editar
              </button>
              <button data-delete class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs inline-flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M5 7h14"/>
                  <path d="M10 11v6M14 11v6"/>
                  <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6 7l1 12a1 1 0 0 0 1 .9h8a1 1 0 0 0 1-.9L18 7"/>
                </svg>
                Eliminar
              </button>
            </div>
          </td>
        `;
        tr.querySelector('[data-edit]').addEventListener('click', () => abrirModal({ id: s.id, nombre: s.nombre }));
        tr.querySelector('[data-delete]').addEventListener('click', () => abrirModalEliminar(s.id));
        tbody.appendChild(tr);
      });
    }

    function escapeHtml(str = '') {
      return str.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    }

    function formatDate(val) {
      if (!val) return '-';
      const d = new Date(val);
      if (Number.isNaN(d)) return val;
      return d.toISOString().slice(0,10);
    }

    function abrirModal(data = {}) {
      form.reset();
      alerta.classList.add('hidden');
      alerta.textContent = '';

      form.action.value = data.id ? 'actualizar' : 'crear';
      form.id.value = data.id || '';
      form.nombre.value = data.nombre || '';

      modalTitle.textContent = data.id ? 'Editar servicio' : 'Nuevo servicio';
      modalSub.textContent = data.id ? 'Actualiza el nombre visible' : 'Define un nuevo servicio';

      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function cerrarModal() {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      alerta.classList.add('hidden');
      const fd = new FormData(form);
      const res = await fetch('../controllers/servicios_controller.php', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        cerrarModal();
        cargarServicios();
      } else {
        alerta.textContent = json.message || 'Ocurrió un error';
        alerta.className = 'text-red-700 bg-red-50 border border-red-200 px-3 py-2 rounded text-sm';
      }
    });

    // Modal eliminar
    let idEliminar = null;
    const modalEliminar = document.getElementById('modalEliminar');
    function abrirModalEliminar(id) {
      idEliminar = id;
      modalEliminar.classList.remove('hidden');
      modalEliminar.classList.add('flex');
    }
    function cerrarModalEliminar() {
      idEliminar = null;
      modalEliminar.classList.add('hidden');
      modalEliminar.classList.remove('flex');
    }
    document.getElementById('btnConfirmarEliminar').addEventListener('click', async () => {
      if (!idEliminar) return;
      const fd = new FormData();
      fd.append('action', 'eliminar');
      fd.append('id', idEliminar);
      const res = await fetch('../controllers/servicios_controller.php', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        cerrarModalEliminar();
        cargarServicios();
      } else {
        alert(json.message || 'No se pudo eliminar');
      }
    });

    cargarServicios();
  </script>
</body>

</html>
