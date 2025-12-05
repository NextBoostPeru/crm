<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';

$rol = $_SESSION['rol'] ?? 'colaborador';
$current = basename($_SERVER['SCRIPT_NAME']);

function nav_item($href, $label, $icon)
{
  global $current;
  $isActive = $current === basename($href);
  $base = 'px-3 py-2 rounded flex items-center gap-2 transition font-medium';
  $active = $isActive ? 'bg-blue-600 text-white shadow-md' : 'hover:bg-blue-50 text-gray-700';
  return "<a href='{$href}' class='{$base} {$active}'>{$icon} {$label}</a>";
}
?>
<style>
  .sidebar-backdrop {
    opacity: 0;
    pointer-events: none;
  }

  .sidebar-backdrop.visible {
    opacity: 1;
    pointer-events: auto;
  }

  .sidebar {
    transform: translateX(-100%);
  }

  .sidebar.open {
    transform: translateX(0);
  }
</style>

<!-- Botón principal para abrir/cerrar -->
<button id="btnToggleSidebar" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"
  class="fixed top-4 left-4 z-50 bg-white/90 backdrop-blur border border-gray-200 shadow-lg p-3 rounded-full text-gray-700 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
  <i class="fas fa-bars"></i>
</button>

<!-- Fondo para cerrar -->
<div id="sidebarBackdrop"
  class="sidebar-backdrop fixed inset-0 bg-gray-900/40 transition-opacity duration-300 lg:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar"
  class="sidebar fixed top-0 left-0 z-40 w-72 max-w-full h-screen bg-white shadow-xl p-4 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:block">
  <div class="flex items-start justify-between border-b pb-4">
    <div>
      <div class="font-bold text-xl">Next Boost</div>
      <p class="text-xs text-gray-500 mt-1">Hola, <?= htmlspecialchars($_SESSION['usuario'] ?? 'Usuario') ?></p>
    </div>
    <button id="btnCloseSidebar" aria-label="Cerrar menú"
      class="lg:hidden text-gray-500 hover:text-gray-800 p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
      <i class="fas fa-xmark"></i>
    </button>
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
</aside>

<!-- Iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
  const btnToggle = document.getElementById('btnToggleSidebar');
  const btnClose = document.getElementById('btnCloseSidebar');
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');

  const isMobile = () => window.innerWidth < 1024;

  const setSidebarState = (open) => {
    const action = open ? 'add' : 'remove';
    const mobile = isMobile();
    sidebar.classList[action]('open');

    if (mobile) {
      backdrop.classList[action]('visible');
      document.body.classList[open ? 'add' : 'remove']('overflow-hidden');
    } else {
      backdrop.classList.remove('visible');
      document.body.classList.remove('overflow-hidden');
    }

    btnToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    btnToggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
  };

  const toggleSidebar = () => setSidebarState(!sidebar.classList.contains('open'));
  const closeSidebar = () => setSidebarState(false);

  btnToggle?.addEventListener('click', toggleSidebar);
  btnClose?.addEventListener('click', closeSidebar);
  backdrop?.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  sidebar.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.innerWidth < 1024) closeSidebar();
    });
  });

  window.addEventListener('resize', () => {
    setSidebarState(window.innerWidth >= 1024);
  });

  // Garantizar estado inicial acorde a breakpoint
  setSidebarState(window.innerWidth >= 1024);
</script>
