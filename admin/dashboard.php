<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if ($_SESSION['rol'] !== 'admin') {
  header('Location: ../tareas.php');
  exit;
}

$mesActual = date('Y-m');
$hoy = date('Y-m-d');

// Ventas desde mayo (05) a diciembre (12) desde la BD
$stmt = $pdo->prepare("SELECT DATE_FORMAT(fecha, '%m') as mes, SUM(monto) AS total 
                       FROM ventas 
                       WHERE YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) > 4
                       GROUP BY mes");
$ventasDesdeMayo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Ventas manuales de enero a abril
$ventasFijas = [
  '01' => 3525,
  '02' => 5686,
  '03' => 4080,
  '04' => 7880
];

// Ventas manuales de enero a abril
$ventasFijas = [
  '01' => 3525,
  '02' => 5686,
  '03' => 4080,
  '04' => 7880
];

// Traer ventas desde mayo (05) a diciembre (12) desde la BD
$stmt = $pdo->prepare("
  SELECT LPAD(MONTH(fecha), 2, '0') as mes, SUM(monto) AS total 
  FROM ventas 
  WHERE YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) >= 5
  GROUP BY mes
");
$stmt->execute();
$ventasDesdeMayo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['05' => monto, ..., '12' => monto]

// Combinar fijos + BD
$ventasAnuales = [];

for ($i = 1; $i <= 12; $i++) {
  $mes = str_pad($i, 2, '0', STR_PAD_LEFT);
  if (isset($ventasFijas[$mes])) {
    $ventasAnuales[$mes] = $ventasFijas[$mes];
  } elseif (isset($ventasDesdeMayo[$mes])) {
    $ventasAnuales[$mes] = $ventasDesdeMayo[$mes];
  } else {
    $ventasAnuales[$mes] = 0;
  }
}

$totalVentasAnio = array_sum($ventasAnuales);


// Clientes del mes actual
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE DATE_FORMAT(creado_en, '%Y-%m') = ?");
$stmt->execute([$mesActual]);
$totalClientesMes = $stmt->fetchColumn();

// Ventas del mes actual
$stmt = $pdo->prepare("SELECT SUM(monto) FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = ?");
$stmt->execute([$mesActual]);
$totalVentasMes = $stmt->fetchColumn();

// Tareas por estado
$stmt = $pdo->query("SELECT estado, COUNT(*) AS cantidad FROM tareas GROUP BY estado");
$tareasPorEstado = $stmt->fetchAll();

// Últimas tareas
$stmt = $pdo->query("SELECT * FROM tareas ORDER BY creado_en DESC LIMIT 5");
$tareasRecientes = $stmt->fetchAll();

// Últimos logs
$stmt = $pdo->query("SELECT * FROM logs ORDER BY fecha DESC LIMIT 10");
$logs = $stmt->fetchAll();

// Totales de hoy
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE fecha = ?");
$stmt->execute([$hoy]);
$totalVentasHoy = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tareas WHERE DATE(creado_en) = ?");
$stmt->execute([$hoy]);
$totalTareasHoy = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE DATE(creado_en) = ?");
$stmt->execute([$hoy]);
$totalSeguimientosHoy = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
</head>

<body class="bg-gray-100">
  <?php include '../includes/sidebar_admin.php'; ?>
  <div class="lg:ml-64 p-4 space-y-6">
    <h1 class="text-2xl font-bold mb-4"><svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 20h18M7 16v-5M12 16V8M17 16v-3"/></svg> Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="text-lg font-semibold"><svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 20a8 8 0 0116 0"/></svg> Clientes este mes</h2>
        <p class="text-2xl font-bold mt-2"><?= $totalClientesMes ?></p>
      </div>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="text-lg font-semibold"><svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M12 3v18M7 7.5A3.5 3.5 0 0010.5 11h3A3.5 3.5 0 0117 14.5a3.5 3.5 0 01-3.5 3.5H7"/></svg> Ventas este mes</h2>
        <p class="text-2xl font-bold mt-2">S/ <?= number_format($totalVentasMes, 2) ?></p>
      </div>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="text-lg font-semibold">📆 Ventas este año</h2>
        <p class="text-2xl font-bold mt-2">S/ <?= number_format($totalVentasAnio, 2) ?></p>
      </div>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="text-lg font-semibold">🔎 Actividad de hoy</h2>
        <p class="mt-1">📌 Tareas: <?= $totalTareasHoy ?></p>
        <p>📝 Seguimientos: <?= $totalSeguimientosHoy ?></p>
        <p>🛒 Ventas: <?= $totalVentasHoy ?></p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="text-lg font-semibold mb-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 20h18M7 16v-5M12 16V8M17 16v-3"/></svg> Estado de Tareas</h2>
        <canvas id="tareasChart"></canvas>
      </div>

      <div class="bg-white p-4 rounded shadow">
        <h2 class="text-lg font-semibold mb-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 20h18M7 16l4-4 3 3 5-5"/></svg> Ventas por Mes</h2>
        <canvas id="ventasChart"></canvas>
      </div>
    </div>

    <div class="bg-white p-4 rounded shadow">
      <h2 class="text-lg font-semibold mb-2">📋 Últimas tareas</h2>
      <ul class="divide-y">
        <?php foreach ($tareasRecientes as $t): ?>
          <li class="py-2"><?= $t['titulo'] ?> <span class="text-sm text-gray-500">(<?= ucfirst($t['equipo']) ?> - <?= $t['estado'] ?>)</span></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="bg-white p-4 rounded shadow overflow-x-auto">
      <h2 class="text-lg font-semibold mb-2"> Últimas acciones del sistema</h2>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600 border-b">
            <th class="py-2">Usuario</th>
            <th>Acción</th>
            <th>Módulo</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $l): ?>
            <tr class="border-b">
              <td class="py-1"><?= $l['usuario'] ?></td>
              <td><?= $l['accion'] ?></td>
              <td><?= $l['modulo'] ?></td>
              <td><?= $l['fecha'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    const tareasChart = new Chart(document.getElementById('tareasChart'), {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_column($tareasPorEstado, 'estado')) ?>,
        datasets: [{
          data: <?= json_encode(array_column($tareasPorEstado, 'cantidad')) ?>,
          backgroundColor: ['#60A5FA', '#34D399', '#FBBF24', '#F87171']
        }]
      }
    });

    const ventasChart = new Chart(document.getElementById('ventasChart'), {
      type: 'bar',
      data: {
        labels: [
          'Enero', 'Febrero', 'Marzo', 'Abril',
          'Mayo', 'Junio', 'Julio', 'Agosto',
          'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ],
        datasets: [{
          label: 'S/ Ventas',
          data: <?= json_encode(array_values($ventasAnuales)) ?>,
          backgroundColor: '#4F46E5'
        }]
      },
      options: {
        plugins: {
          legend: {
            display: false
          },
          datalabels: {
            color: '#111',
            anchor: 'end',
            align: 'top',
            formatter: value => 'S/ ' + value.toLocaleString()
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => 'S/ ' + value
            }
          }
        }
      },
      plugins: [ChartDataLabels]
    });
  </script>
</body>

</html>