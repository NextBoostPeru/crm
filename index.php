<?php
session_start();
if (isset($_SESSION['usuario'])) {
  header('Location: ' . ($_SESSION['rol'] === 'admin' ? 'admin/dashboard.php' : 'colaborador/dashboard.php'));
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Next Boost CRM - Acceso</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center px-4 py-8">
  <div class="grid lg:grid-cols-2 gap-8 max-w-6xl w-full">
    <section class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl p-8 text-white shadow-2xl">
      <div class="flex items-center gap-3 mb-6">
        <div class="h-12 w-12 rounded-2xl bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-xl font-black">NB</div>
        <div>
          <p class="text-sm uppercase tracking-[0.2em] text-slate-300">Next Boost CRM</p>
          <h1 class="text-2xl font-extrabold leading-tight">Gestiona clientes, tareas y ventas en un solo lugar</h1>
        </div>
      </div>
      <ul class="space-y-3 text-slate-200 text-sm">
        <li class="flex gap-3 items-start"><span class="text-green-400">•</span><span>Panel de control con métricas en tiempo real y gráficos claros para tus decisiones.</span></li>
        <li class="flex gap-3 items-start"><span class="text-green-400">•</span><span>Flujos de tareas estilo Kanban para equipos de diseño y desarrollo.</span></li>
        <li class="flex gap-3 items-start"><span class="text-green-400">•</span><span>Historial centralizado de clientes, servicios y estado de pagos.</span></li>
      </ul>
      <div class="mt-8 grid sm:grid-cols-3 gap-4">
        <div class="bg-white/5 rounded-xl p-4 border border-white/5">
          <p class="text-xs uppercase text-slate-400 tracking-wide">Disponibilidad</p>
          <p class="text-lg font-semibold">24/7</p>
        </div>
        <div class="bg-white/5 rounded-xl p-4 border border-white/5">
          <p class="text-xs uppercase text-slate-400 tracking-wide">Equipos</p>
          <p class="text-lg font-semibold">Diseño & Desarrollo</p>
        </div>
        <div class="bg-white/5 rounded-xl p-4 border border-white/5">
          <p class="text-xs uppercase text-slate-400 tracking-wide">Seguridad</p>
          <p class="text-lg font-semibold">Acceso con roles</p>
        </div>
      </div>
    </section>

    <form action="procesar_login.php" method="POST" class="bg-white rounded-2xl shadow-2xl p-8 space-y-6">
      <div class="space-y-2 text-center">
        <p class="text-sm font-semibold text-indigo-600">Bienvenido(a)</p>
        <h2 class="text-2xl font-bold text-slate-900">Iniciar sesión</h2>
        <p class="text-slate-500 text-sm">Ingresa tus credenciales para continuar.</p>
      </div>

      <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 text-red-700 px-4 py-3 rounded-xl text-sm border border-red-100">
          <strong class="font-semibold">Acceso denegado.</strong> Verifica tu correo y contraseña e inténtalo nuevamente.
        </div>
      <?php endif; ?>

      <div class="space-y-4">
        <label class="block text-sm font-semibold text-slate-700" for="email">Correo</label>
        <input type="email" id="email" name="email" placeholder="tucorreo@empresa.com" required class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

        <label class="block text-sm font-semibold text-slate-700" for="password">Contraseña</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>

      <button class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-semibold py-3 rounded-xl shadow-lg hover:shadow-xl transition" type="submit">Entrar</button>

      <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-sm text-slate-600">
        <p class="font-semibold text-slate-800 mb-1">¿Eres nuevo?</p>
        <p>Solicita a un administrador tus credenciales y comienza a monitorear clientes, servicios y tareas.</p>
      </div>
    </form>
  </div>
</body>

</html>
