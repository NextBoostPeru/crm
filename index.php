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
  <title>Next Boost CRM - Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center px-4 py-10 relative overflow-hidden">
  <div class="absolute inset-0 opacity-40 pointer-events-none" aria-hidden="true">
    <div class="absolute -left-10 -top-20 w-72 h-72 bg-indigo-500 rounded-full blur-3xl"></div>
    <div class="absolute right-10 top-10 w-72 h-72 bg-blue-500 rounded-full blur-3xl"></div>
    <div class="absolute -right-24 bottom-10 w-80 h-80 bg-cyan-400 rounded-full blur-3xl"></div>
  </div>

  <div class="relative w-full max-w-xl">
    <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-3xl shadow-2xl overflow-hidden">
      <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-8 py-6 text-white">
        <div class="flex items-center gap-3">
          <div class="h-12 w-12 rounded-2xl bg-white/15 flex items-center justify-center text-xl font-black">NB</div>
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-white/70">Next Boost CRM</p>
            <h1 class="text-2xl font-bold leading-tight">Panel seguro de acceso</h1>
          </div>
        </div>
      </div>

      <form action="procesar_login.php" method="POST" class="bg-white p-8 space-y-6">
        <div class="space-y-2">
          <p class="text-sm font-semibold text-indigo-600">Bienvenido(a)</p>
          <h2 class="text-2xl font-bold text-slate-900">Iniciar sesión</h2>
          <p class="text-slate-500 text-sm">Ingresa tu correo y contraseña para continuar.</p>
        </div>

        <div class="space-y-3">
          <label class="block text-sm font-semibold text-slate-700" for="email">Correo electrónico</label>
          <div class="relative">
            <input type="email" id="email" name="email" autocomplete="email" placeholder="tucorreo@empresa.com" required class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 absolute right-4 top-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6h16v12H4z" /><path d="m4 6 8 7 8-7" /></svg>
          </div>
        </div>

        <div class="space-y-3">
          <label class="block text-sm font-semibold text-slate-700" for="password">Contraseña</label>
          <div class="relative">
            <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required class="w-full border border-slate-200 rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-500 hover:text-indigo-600 focus:outline-none" aria-label="Mostrar u ocultar contraseña">
              <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1.5 12s4-7.5 10.5-7.5S22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" /><circle cx="12" cy="12" r="3" /></svg>
            </button>
          </div>
        </div>

        <button class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-semibold py-3 rounded-xl shadow-lg hover:shadow-xl transition transform hover:-translate-y-0.5" type="submit">Entrar</button>

        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-sm text-slate-600">
          <p class="font-semibold text-slate-800 mb-1">¿Sin acceso?</p>
          <p>Solicita a un administrador tus credenciales para comenzar a trabajar en el CRM.</p>
        </div>
      </form>
    </div>
  </div>

  <div id="toast" class="fixed top-4 right-4 transform transition-all duration-300 translate-y-[-20px] opacity-0 pointer-events-none">
    <div id="toastContent" class="flex items-start gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm"></div>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePassword?.addEventListener('click', () => {
      const isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      eyeIcon.innerHTML = isHidden
        ? '<path d="M3 3l18 18" /><path d="M10.584 10.705a3 3 0 0 1 4.117 4.117" /><path d="M9.88 4.594a8.03 8.03 0 0 1 2.12-.094C18 4.5 22.5 12 22.5 12c-.635 1.1-1.38 2.162-2.24 3.114" /><path d="M6.3 6.3C3.485 8.223 1.5 12 1.5 12a16.571 16.571 0 0 0 3.677 4.39c1.18.93 2.502 1.61 3.823 1.944" />'
        : '<path d="M1.5 12s4-7.5 10.5-7.5S22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" /><circle cx="12" cy="12" r="3" />';
    });

    const params = new URLSearchParams(window.location.search);
    const toast = document.getElementById('toast');
    const toastContent = document.getElementById('toastContent');

    const messages = {
      credenciales: {
        text: 'Acceso denegado. Verifica tu correo y contraseña.',
        classes: 'bg-red-50 text-red-700 border-red-200'
      },
      incompleto: {
        text: 'Completa ambos campos para continuar.',
        classes: 'bg-amber-50 text-amber-700 border-amber-200'
      },
      logout: {
        text: 'Sesión cerrada correctamente.',
        classes: 'bg-emerald-50 text-emerald-700 border-emerald-200'
      },
      bienvenida: {
        text: 'Bienvenido(a), estás entrando al panel.',
        classes: 'bg-indigo-50 text-indigo-700 border-indigo-200'
      }
    };

    const showToast = (type) => {
      const msg = messages[type];
      if (!msg) return;
      toastContent.className = `flex items-start gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm ${msg.classes}`;
      toastContent.innerHTML = `<span class="mt-0.5">•</span><span>${msg.text}</span>`;
      toast.style.transform = 'translateY(0)';
      toast.style.opacity = '1';
      toast.style.pointerEvents = 'auto';
      setTimeout(() => {
        toast.style.transform = 'translateY(-20px)';
        toast.style.opacity = '0';
        toast.style.pointerEvents = 'none';
      }, 3200);
    };

    const error = params.get('error');
    const bye = params.get('logout');
    const welcome = params.get('bienvenida');

    if (error) showToast(error);
    if (bye) showToast('logout');
    if (welcome) showToast('bienvenida');
  </script>
</body>

</html>
