<?php require_once '../includes/auth.php'; ?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Colaborador - Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="p-6">
  <?php include '../includes/sidebar_admin.php'; ?>
  <div class="md:ml-64 p-4 md:p-6">
    <h1 class="text-2xl font-bold mb-4">Hola, <?php echo $_SESSION['usuario']; ?> (Colaborador)</h1>
  </div>

</body>

</html>