<?php session_start();
if (isset($_SESSION['usuario'])) {
  header('Location: ' . ($_SESSION['rol'] === 'admin' ? 'admin/dashboard.php' : 'colaborador/dashboard.php'));
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
  <form action="procesar_login.php" method="POST" class="bg-white p-6 rounded shadow w-full max-w-sm">
    <h2 class="text-xl font-bold mb-4 text-center">Iniciar Sesión</h2>
    <?php if (isset($_GET['error'])): ?>
      <p class="text-red-600 text-sm text-center mb-4">Correo o contraseña incorrectos</p>
    <?php endif; ?>
    <input type="email" name="email" placeholder="Correo" required class="w-full mb-3 border px-3 py-2 rounded">
    <input type="password" name="password" placeholder="Contraseña" required class="w-full mb-3 border px-3 py-2 rounded">
    <button class="bg-blue-600 text-white px-4 py-2 rounded w-full" type="submit">Entrar</button>
  </form>
</body>

</html>