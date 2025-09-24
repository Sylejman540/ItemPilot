<?php
/* ─────────── SESSION + DB ─────────── */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/register/register.php';

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0) {
  header("Location: register/login.php");
  exit;
}
?>
<?php require_once __DIR__ . '/components/charts.php'; ?>
<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
<head>  
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <title>An2table</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <link href="images/icon.png" rel="icon">
  <link rel="stylesheet" href="style.css">
</head>
<body class="overflow-x-hidden bg-gray-100">
  
<div id="account" class="hidden inset-0 md:fixed absolute mt-14 md:ml-150 h-screen overflow-none md:ml-0 md:mr-0 ml-3 mr-3">
  <div class="w-full max-w-md bg-white px-8 py-5 rounded-2xl border border-gray-200">
  <h1 class="text-3xl font-extrabold text-center text-[#263544] mb-4 mt-10">Manage Your Account</h1>

    <form action="/ItemPilot/account/manage-account.php" method="POST" class="space-y-6">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Name</label>
        <input type="text" name="name" required class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300" value="<?= htmlspecialchars($user['name'] ?? '') ?>">
      </div>

      <fieldset class="space-y-4">
        <legend class="text-sm font-semibold text-gray-700">Change Password (optional)</legend>

        <div>
          <label class="block text-sm text-gray-700 mb-1">Current Password</label>
          <input type="password" name="current_password" class="w-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-blue-300" placeholder="Enter your current password">
        </div>

        <div>
          <label class="block text-sm text-gray-700 mb-1">New Password</label>
          <input type="password" name="new_password" class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300" placeholder="At least 8 characters">
        </div>

        <div>
          <label class="block text-sm text-gray-700 mb-1">Confirm New Password</label>
          <input type="password" name="new_password_confirm" class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300" placeholder="Re-enter new password">
        </div>
      </fieldset>

      <div class="flex justify-between items-center">
        <button type="submit" class="bg-[#263544] cursor-pointer hover:bg-slate-800 text-white px-6 py-3 rounded-full font-semibold transition">Save Changes</button>

        <button type="submit" name="delete_account" value="1" class="text-red-600 hover:underline text-sm font-medium"  onclick="return confirm('Are you sure you want to delete your account? This cannot be undone.');">Delete Account</button>
      </div>
    </form>
  </div>
  </div>

  <?php require_once __DIR__ . '/components/nav.php'; ?>
   
  <?php require_once __DIR__ . '/components/header.php'; ?>

  <?php require_once __DIR__ . '/components/dashboard.php'; ?>

  <?php require_once __DIR__ . '/components/table.php'; ?>

  <?php require_once __DIR__ . '/components/contact.php'; ?>

  <?php require_once __DIR__ . '/components/categories.php'; ?>

  <?php require_once __DIR__ . '/components/insight.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(() => {
  const render = (id, opt) => {
    const el = document.querySelector(id);
    if (!el) return;
    new ApexCharts(el, opt).render();
  };

  /* 1. Area Chart - New Tables */
  render('#areaChart', {
  chart: { type: 'area', height: 330, toolbar: { show: false } },
  series: [{
    name: 'Tables',
    data: <?= json_encode(array_map(fn($r)=>['x'=>$r['dt'], 'y'=>$r['cnt']], $areaData)) ?>
  }],
  xaxis: { type: 'datetime' },
  stroke: { curve: 'smooth', width: 2 },
  markers: { size: 4 },
  colors: ['#3b82f6'],
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } }
});


  /* 2. Polar Area - Records Per Table */
  render('#polarAreaChart', {
    chart: { type: 'polarArea', height: 330 },
    series: <?= json_encode(array_column($polarData, 'cnt')) ?>,
    labels: <?= json_encode(array_column($polarData, 'table_name')) ?>,
    colors: ['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1'],
    stroke: { colors: ['#fff'] }
  });

  /* 3. Bar Chart - Records Per Month */
  render('#barChart', {
    chart: { type: 'bar', height: 330 },
    series: [{ name: 'Records', data: <?= json_encode(array_column($barData, 'cnt')) ?> }],
    xaxis: { categories: <?= json_encode(array_column($barData, 'mth')) ?> },
    plotOptions: { bar: { columnWidth: '10%', borderRadius: 4 } },
    colors: ['#10b981'],
    dataLabels: { enabled: false }
  });

  /* 4. Radar Chart - Status Distribution */
  render('#radarChart', {
    chart: { type: 'radar', height: 330 },
    series: [{
      name: 'Records',
      data: <?= json_encode(array_column($radarData, 'cnt')) ?>
    }],
    labels: <?= json_encode(array_column($radarData, 'status')) ?>,
    colors: ['#f59e0b'],
    stroke: { width: 2 }
  });

  /* 5. Gradient Line Chart - Records Over Time */
  render('#gradientLineChart', {
    chart: { type: 'line', height: 330, toolbar: { show: false } },
    series: [{
      name: 'Records',
      data: <?= json_encode(array_map(fn($r)=>['x'=>$r['dt'], 'y'=>$r['cnt']], $lineData)) ?>
    }],
    xaxis: { type: 'datetime' },
    stroke: { curve: 'smooth', width: 3 },
    markers: { size: 4 },
    colors: ['#3b82f6'],
    fill: { type: 'gradient', gradient: { shade: 'light', type: "vertical", opacityFrom: 0.7, opacityTo: 0.1 } }
  });

  /* 6. Pie Chart - Status Breakdown */
  render('#pieChart', {
  chart: { type: 'pie', height: 330 },
  series: <?= json_encode(array_values($statusMap)) ?>, // [toDo, inProgress, done]
  labels: <?= json_encode(array_keys($statusMap)) ?>,   // ["To Do","In Progress","Done"]
  colors: ['#3b82f6','#10b981','#f59e0b','#ef4444'],
  legend: { position: 'bottom' }
  });
})();
</script>
<script src="ajax.js"></script>
<script src="sidebar.js"></script>
<script src="tables.js"></script>
</body>
</html>
