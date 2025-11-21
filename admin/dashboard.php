<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if ($_SESSION['rol'] !== 'admin') {
  header('Location: ../tareas.php');
  exit;
}

$mesActual = date('Y-m');
$hoy = date('Y-m-d');

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

// Foco semanal: tareas con entregas próximas
$limiteSemana = date('Y-m-d', strtotime('+7 days'));
$stmt = $pdo->prepare("SELECT titulo, equipo, estado_kanban, fecha_entrega FROM tareas WHERE fecha_entrega BETWEEN ? AND ? ORDER BY fecha_entrega ASC LIMIT 6");
$stmt->execute([$hoy, $limiteSemana]);
$tareasProximas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tareas WHERE fecha_entrega BETWEEN ? AND ?");
$stmt->execute([$hoy, $limiteSemana]);
$totalTareasSemana = max(1, (int)$stmt->fetchColumn());

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tareas WHERE fecha_entrega BETWEEN ? AND ? AND estado_kanban = 'completada'");
$stmt->execute([$hoy, $limiteSemana]);
$tareasSemanaCompletadas = (int)$stmt->fetchColumn();
$avanceSemana = min(100, round(($tareasSemanaCompletadas / $totalTareasSemana) * 100));

// Clientes recientes
$stmt = $pdo->query("SELECT nombre, empresa, estado, creado_en FROM clientes ORDER BY creado_en DESC LIMIT 5");
$clientesRecientes = $stmt->fetchAll();

// Ventas por servicio
$stmt = $pdo->query("SELECT s.nombre AS servicio, SUM(v.monto) AS total FROM ventas v LEFT JOIN servicios s ON s.id = v.servicio_id GROUP BY s.nombre ORDER BY total DESC");
$ventasPorServicio = $stmt->fetchAll();
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

<body class="bg-gray-50">
  <?php include '../includes/sidebar_admin.php'; ?>
  <div class="lg:ml-64 p-4 space-y-6">
    <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl text-white p-6 shadow-xl flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <p class="text-sm uppercase tracking-[0.3em] text-white/80">Panel ejecutivo</p>
        <h1 class="text-3xl font-bold mt-2 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 20h18M7 16v-5M12 16V8M17 16v-3"/></svg>
          Dashboard Next Boost
        </h1>
        <p class="text-white/80 mt-2 max-w-3xl">Monitorea clientes, ventas y tareas desde un vistazo rápido con gráficos y listas accionables.</p>
      </div>
      <div class="flex gap-3">
        <div class="bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-center">
          <p class="text-xs text-white/80">Ventas hoy</p>
          <p class="text-2xl font-bold"><?= $totalVentasHoy ?></p>
        </div>
        <div class="bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-center">
          <p class="text-xs text-white/80">Tareas creadas hoy</p>
          <p class="text-2xl font-bold"><?= $totalTareasHoy ?></p>
        </div>
        <div class="bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-center">
          <p class="text-xs text-white/80">Seguimientos hoy</p>
          <p class="text-2xl font-bold"><?= $totalSeguimientosHoy ?></p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h2 class="text-lg font-semibold flex items-center gap-2 text-gray-800">
          <span class="bg-blue-100 text-blue-700 rounded-lg px-2 py-1 text-sm">Clientes</span>
          Este mes
        </h2>
        <p class="text-3xl font-bold mt-3 text-gray-900"><?= $totalClientesMes ?></p>
        <p class="text-sm text-gray-500">Nuevos registros en <?= date('F') ?></p>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h2 class="text-lg font-semibold flex items-center gap-2 text-gray-800">
          <span class="bg-indigo-100 text-indigo-700 rounded-lg px-2 py-1 text-sm">Ventas</span>
          Este mes
        </h2>
        <p class="text-3xl font-bold mt-3 text-gray-900">S/ <?= number_format($totalVentasMes, 2) ?></p>
        <p class="text-sm text-gray-500">Total facturado</p>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">Ventas anuales</h2>
        <p class="text-3xl font-bold mt-3 text-gray-900">S/ <?= number_format($totalVentasAnio, 2) ?></p>
        <p class="text-sm text-gray-500">Enero - Diciembre</p>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">Actividad de hoy</h2>
        <p class="mt-2 text-gray-700">📌 Tareas: <?= $totalTareasHoy ?></p>
        <p class="text-gray-700">📝 Seguimientos: <?= $totalSeguimientosHoy ?></p>
        <p class="text-gray-700">🛒 Ventas: <?= $totalVentasHoy ?></p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-lg font-semibold flex items-center gap-2 text-gray-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 20h18M7 16v-5M12 16V8M17 16v-3"/></svg>
            Ventas por Mes
          </h2>
          <span class="text-sm text-gray-500">Actualizado <?= date('d/m/Y') ?></span>
        </div>
        <canvas id="ventasChart"></canvas>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h2 class="text-lg font-semibold mb-2 text-gray-800 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.5" d="M3 20h18M7 16v-5M12 16V8M17 16v-3"/></svg>
          Estado de Tareas
        </h2>
        <canvas id="tareasChart"></canvas>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-1">
          <h2 class="text-lg font-semibold text-gray-800">Foco semanal</h2>
          <span class="text-xs text-gray-500">Próximos 7 días</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-3 mb-3">
          <div class="h-3 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full" style="width: <?= $avanceSemana ?>%"></div>
        </div>
        <p class="text-sm text-gray-600 mb-3">Avance de tareas entregables: <?= $avanceSemana ?>% (<?= $tareasSemanaCompletadas ?>/<?= $totalTareasSemana ?>).</p>
        <ul class="divide-y">
          <?php foreach ($tareasProximas as $t): ?>
            <li class="py-2 flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-gray-800 leading-tight"><?= htmlspecialchars($t['titulo']) ?></p>
                <p class="text-xs text-gray-500">Equipo: <?= htmlspecialchars(ucfirst($t['equipo'])) ?> · <?= htmlspecialchars($t['estado_kanban']) ?></p>
              </div>
              <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg border border-indigo-100"><?= date('d/m', strtotime($t['fecha_entrega'])) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (empty($tareasProximas)): ?>
            <li class="py-2 text-sm text-gray-500">No hay entregas programadas en los próximos días.</li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h2 class="text-lg font-semibold mb-2 text-gray-800">Clientes recientes</h2>
        <ul class="space-y-3">
          <?php foreach ($clientesRecientes as $c): ?>
            <li class="flex items-start justify-between">
              <div>
                <p class="font-semibold text-gray-800"><?= htmlspecialchars($c['nombre']) ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($c['empresa']) ?> · <?= htmlspecialchars($c['estado']) ?></p>
              </div>
              <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded-lg border border-gray-200"><?= date('d/m', strtotime($c['creado_en'])) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (empty($clientesRecientes)): ?>
            <li class="text-sm text-gray-500">Aún no hay registros recientes.</li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-lg font-semibold text-gray-800">Ventas por servicio</h2>
          <span class="text-xs text-gray-500">Top</span>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b">
              <th class="py-2">Servicio</th>
              <th class="py-2">Total (S/)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ventasPorServicio as $s): ?>
              <tr class="border-b">
                <td class="py-2 text-gray-800"><?= htmlspecialchars($s['servicio'] ?? 'Sin servicio') ?></td>
                <td class="py-2 text-gray-900 font-semibold">S/ <?= number_format($s['total'] ?? 0, 2) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($ventasPorServicio)): ?>
              <tr><td colspan="2" class="py-2 text-sm text-gray-500">Aún no hay ventas registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
      <h2 class="text-lg font-semibold mb-2 text-gray-800 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M8 7h8M8 12h8M8 17h8"/>
          <rect x="4" y="4" width="16" height="16" rx="2" ry="2"/>
        </svg>
        Últimas tareas
      </h2>
      <ul class="divide-y">
        <?php foreach ($tareasRecientes as $t): ?>
          <li class="py-2 flex items-center justify-between">
            <div>
              <p class="font-semibold text-gray-800"><?= htmlspecialchars($t['titulo']) ?></p>
              <p class="text-xs text-gray-500"><?= ucfirst($t['equipo']) ?> · <?= $t['estado'] ?? $t['estado_kanban'] ?></p>
            </div>
            <?php if (!empty($t['fecha_entrega'])): ?>
              <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded-lg border border-gray-200">Entrega <?= date('d/m', strtotime($t['fecha_entrega'])) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
      <h2 class="text-lg font-semibold mb-2 text-gray-800">Últimas acciones del sistema</h2>
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
              <td class="py-1 text-gray-800"><?= htmlspecialchars($l['usuario']) ?></td>
              <td class="text-gray-700"><?= htmlspecialchars($l['accion']) ?></td>
              <td class="text-gray-700"><?= htmlspecialchars($l['modulo']) ?></td>
              <td class="text-gray-600"><?= htmlspecialchars($l['fecha']) ?></td>
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
