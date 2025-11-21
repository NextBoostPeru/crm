<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (($_SESSION['rol'] ?? '') !== 'admin') {
  header('Location: ../login.php');
  exit;
}

$usuarios = $pdo->query("SELECT id, nombre, email, rol, creado_en FROM usuarios ORDER BY id DESC")
                ->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Usuarios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <?php include '../includes/sidebar_admin.php'; ?>

  <div class="md:ml-64 p-4 md:p-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-sm text-gray-500">Administra credenciales y roles del equipo</p>
        <h1 class="text-2xl font-bold flex items-center gap-2 text-gray-900">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M5.5 21a4.5 4.5 0 0 1 9 0M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/>
            <path d="M15 10a4 4 0 1 1 7 2.5"/>
            <path d="M16 21h5.5a4.5 4.5 0 0 0-6.86-3.9"/>
          </svg>
          Usuarios
        </h1>
      </div>
      <button onclick="abrirModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow inline-flex items-center gap-2 hover:bg-blue-700 transition">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M12 5v14M5 12h14"/>
        </svg>
        Nuevo usuario
      </button>
    </div>

    <div class="bg-white rounded shadow p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2 text-gray-800 font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
          Usuarios registrados
        </div>
        <span class="text-xs text-gray-500">Total: <?= count($usuarios) ?></span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
            <tr>
              <th class="px-3 py-2">#</th>
              <th class="px-3 py-2">Nombre</th>
              <th class="px-3 py-2">Correo</th>
              <th class="px-3 py-2">Rol</th>
              <th class="px-3 py-2 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u): ?>
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="px-3 py-2 text-gray-600"><?= (int)$u['id'] ?></td>
                <td class="px-3 py-2 font-medium text-gray-900"><?= h($u['nombre']) ?></td>
                <td class="px-3 py-2 text-gray-700"><?= h($u['email']) ?></td>
                <td class="px-3 py-2">
                  <span class="px-2 py-1 rounded-full text-xs font-medium ring-1 ring-inset <?= $u['rol']==='admin' ? 'bg-blue-50 text-blue-700 ring-blue-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200' ?>">
                    <?= h(ucfirst($u['rol'])) ?>
                  </span>
                </td>
                <td class="px-3 py-2">
                  <div class="flex items-center justify-center gap-2">
                    <button class="bg-amber-500 hover:bg-amber-600 text-white px-2 py-1 rounded text-xs inline-flex items-center gap-1"
                            onclick='abrirModal(<?= json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 20h4l10-10a1.5 1.5 0 0 0-3-3L5 17l-1 4Z"/>
                      </svg>
                      Editar
                    </button>
                    <button class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs inline-flex items-center gap-1"
                            onclick='abrirModalEliminar(<?= (int)$u["id"] ?>)'>
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M5 7h14"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6 7l1 12a1 1 0 0 0 1 .9h8a1 1 0 0 0 1-.9L18 7"/>
                      </svg>
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($usuarios)): ?>
              <tr>
                <td colspan="5" class="px-3 py-6 text-center text-gray-500">Aún no hay usuarios registrados.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal Crear/Editar -->
  <div id="modalUsuario" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg w-full max-w-lg p-6 relative">
      <div class="flex items-start justify-between mb-4">
        <div>
          <p class="text-sm text-gray-500" id="modalSub">Agrega un nuevo miembro</p>
          <h3 class="text-xl font-semibold text-gray-900" id="modalTitle">Nuevo usuario</h3>
        </div>
        <button onclick="cerrarModal()" class="text-gray-500 hover:text-gray-800">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M6 6l12 12M6 18L18 6"/>
          </svg>
        </button>
      </div>

      <form id="formUsuario" class="space-y-3">
        <input type="hidden" name="id">
        <input type="hidden" name="action" value="crear">

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
          <input type="text" name="nombre" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
          <input type="email" name="email" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
            <select name="rol" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
              <option value="">Seleccionar</option>
              <option value="admin">Administrador</option>
              <option value="colaborador">Colaborador</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
            <input type="password" name="password" placeholder="••••••••" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Déjala vacía para mantener la actual.</p>
          </div>
        </div>

        <div id="alerta" class="hidden text-sm"></div>

        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded shadow hover:bg-blue-700 transition">Guardar cambios</button>
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
      <h3 class="text-lg font-semibold text-gray-900 mb-2">Eliminar usuario</h3>
      <p class="text-sm text-gray-600 mb-4">Esta acción no se puede deshacer.</p>
      <div class="flex justify-center gap-3">
        <button onclick="cerrarModalEliminar()" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
        <button id="btnConfirmarEliminar" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">Eliminar</button>
      </div>
    </div>
  </div>

  <script>
    const modal = document.getElementById('modalUsuario');
    const form = document.getElementById('formUsuario');
    const alerta = document.getElementById('alerta');
    const modalTitle = document.getElementById('modalTitle');
    const modalSub = document.getElementById('modalSub');

    function abrirModal(data = {}) {
      form.reset();
      alerta.classList.add('hidden');
      alerta.textContent = '';

      form.action.value = data.id ? 'actualizar' : 'crear';
      form.id.value = data.id || '';
      form.nombre.value = data.nombre || '';
      form.email.value = data.email || '';
      form.rol.value = data.rol || '';
      form.password.placeholder = data.id ? 'Opcional: nueva contraseña' : 'Contraseña';

      modalTitle.textContent = data.id ? 'Editar usuario' : 'Nuevo usuario';
      modalSub.textContent = data.id ? 'Actualiza la información de acceso' : 'Agrega un nuevo miembro';

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
      const res = await fetch('../controllers/usuarios_controller.php', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        cerrarModal();
        location.reload();
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
      const res = await fetch('../controllers/usuarios_controller.php', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        cerrarModalEliminar();
        location.reload();
      } else {
        alert(json.message || 'No se pudo eliminar');
      }
    });
  </script>
</body>
</html>
