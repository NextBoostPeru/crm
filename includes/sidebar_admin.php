<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$rol = $_SESSION['rol'] ?? 'colaborador';
$current = basename($_SERVER['SCRIPT_NAME']);

function nav_item($href, $label, $icon)
{
  global $current;
  $isActive = $current === basename($href);
  $base = 'px-3 py-2 rounded flex items-center gap-2 transition';
  $active = $isActive ? 'bg-blue-600 text-white shadow' : 'hover:bg-blue-50 text-gray-700';
  return "<a href='{$href}' class='{$base} {$active}'>{$icon} {$label}</a>";
}
?>
<style>
  @media (max-width: 1024px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
  }
</style>

<!-- Botón hamburguesa solo visible en pantallas pequeñas -->
<button id="btnToggleSidebar" class="lg:hidden fixed top-4 left-4 z-50 bg-white shadow p-2 rounded">
  <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 z-40 w-64 h-screen bg-white shadow-lg p-4 transition-transform duration-300 lg:translate-x-0 lg:block">
  <div class="border-b pb-4">
    <div class="font-bold text-xl">Next Boost</div>
    <p class="text-xs text-gray-500 mt-1">Hola, <?= htmlspecialchars($_SESSION['usuario'] ?? 'Usuario') ?></p>
  </div>
  <nav class="flex flex-col mt-4 space-y-2 text-sm">
    <?php if ($rol === 'admin'): ?>
      <?= nav_item('dashboard.php', 'Dashboard', '<i class="fas fa-tachometer-alt"></i>') ?>
      <?= nav_item('clientes.php', 'Clientes', '<i class="fas fa-user-friends"></i>') ?>
      <?= nav_item('ventas.php', 'Ventas', '<i class="fas fa-dollar-sign"></i>') ?>
      <?= nav_item('usuarios.php', 'Usuarios', '<i class="fas fa-users-cog"></i>') ?>
      <?= nav_item('servicios.php', 'Servicios', '<i class="fas fa-cogs"></i>') ?>
    <?php endif; ?>

    <?= nav_item('tareas.php', 'Tareas', '<i class="fas fa-tasks"></i>') ?>
    <a href="../logout.php" class="text-red-600 hover:bg-red-50 px-3 py-2 rounded flex items-center gap-2 transition">
      <i class="fas fa-sign-out-alt"></i> Cerrar sesión
    </a>
  </nav>
</div>

<!-- Iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
  const btnToggle = document.getElementById('btnToggleSidebar');
  const sidebar = document.getElementById('sidebar');

  btnToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });
</script>
