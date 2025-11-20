<?php require_once '../includes/auth.php';
require_once '../includes/db.php';

if ($_SESSION['rol'] !== 'admin') {
  header('Location: ../login.php');
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
  <div class="md:ml-64 p-4 md:p-6">
    <h2 class="text-2xl font-bold mb-4">➕ Agregar Servicios</h2>

    <form id="formServicio" class="flex gap-2 mb-4">
      <input type="text" name="nombre" placeholder="Nombre del servicio" required class="border p-2 rounded w-full">
      <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Guardar</button>
    </form>

    <div id="serviciosLista"></div>
  </div>

  <script>
    const form = document.getElementById('formServicio');
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const data = new FormData(form);
      const res = await fetch('../controllers/servicios_controller.php', {
        method: 'POST',
        body: data
      });
      const json = await res.json();
      if (json.success) {
        form.reset();
        cargarServicios();
      }
    });

    async function cargarServicios() {
      const res = await fetch('../controllers/servicios_controller.php');
      const html = await res.text();
      document.getElementById('serviciosLista').innerHTML = html;
    }
    cargarServicios();
  </script>
</body>

</html>