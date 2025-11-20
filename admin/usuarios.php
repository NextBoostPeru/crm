<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if ($_SESSION['rol'] !== 'admin') {
  header('Location: ../login.php');
  exit;
}

// Obtener usuarios existentes
$usuarios = $pdo->query("SELECT id, nombre, email, rol FROM usuarios ORDER BY id DESC")->fetchAll();
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

  <div class="md:ml-64 p-4 md:p-6">
    <h2 class="text-2xl font-bold mb-6">👤 Crear Usuario</h2>

    <!-- Formulario -->
    <form id="formUsuario" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-white p-4 rounded shadow mb-6">
      <input type="text" name="nombre" placeholder="Nombre completo" class="border p-2 rounded w-full" required>
      <input type="email" name="email" placeholder="Correo electrónico" class="border p-2 rounded w-full" required>
      <input type="password" name="password" placeholder="Contraseña" class="border p-2 rounded w-full" required>
      <select name="rol" class="border p-2 rounded w-full" required>
        <option value="">Seleccionar rol</option>
        <option value="admin">Administrador</option>
        <option value="colaborador">Colaborador</option>
      </select>
      <div class="md:col-span-2">
        <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition" type="submit">
          Registrar
        </button>
      </div>
    </form>

    <div id="mensaje" class="text-sm text-green-600 mb-6"></div>

    <!-- Tabla de usuarios -->
    <div class="bg-white p-4 rounded shadow overflow-x-auto">
      <h3 class="text-lg font-semibold mb-4">📋 Usuarios registrados</h3>
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="border-b text-gray-700">
            <th class="py-2 px-2">#</th>
            <th class="py-2 px-2">Nombre</th>
            <th class="py-2 px-2">Correo</th>
            <th class="py-2 px-2">Rol</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr class="border-b hover:bg-gray-100">
              <td class="py-2 px-2"><?= $u['id'] ?></td>
              <td class="py-2 px-2"><?= htmlspecialchars($u['nombre']) ?></td>
              <td class="py-2 px-2"><?= htmlspecialchars($u['email']) ?></td>
              <td class="py-2 px-2 capitalize"><?= $u['rol'] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($usuarios)): ?>
            <tr>
              <td colspan="4" class="py-4 text-center text-gray-500">No hay usuarios registrados.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    const form = document.getElementById('formUsuario');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = new FormData(form);
      const res = await fetch('../controllers/usuarios_controller.php', {
        method: 'POST',
        body: data
      });
      const json = await res.json();
      document.getElementById('mensaje').innerText = json.message;
      if (json.success) {
        form.reset();
        setTimeout(() => location.reload(), 1000); // recarga para mostrar el nuevo usuario
      }
    });
  </script>
</body>

</html>