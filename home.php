<?php
/* ──────────────  SESSION + DB  ────────────── */
session_start();
require_once __DIR__ . '/db.php';
require_once "register/register.php";
$uid = $_SESSION['user_id'] ?? 0;

/* ──────────────  RECORDS QUERY  ────────────── */
$tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : null;

$sql = "
  SELECT id,name,notes,assignee,status,attachment_summary
    FROM universal
   WHERE user_id = ?
";
$params = [$uid];
if ($tableId) {
  $sql .= " AND id = ?";
  $params[] = $tableId;
}
$sql .= " ORDER BY id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($params)), ...$params);
$stmt->execute();
$rows       = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hasRecord  = !empty($rows);
$first      = $hasRecord ? $rows[0] : null;
$stmt->close();

/* ──────────────  KPI METRICS  ────────────── */
$totalRecords = (int) $conn->query("SELECT COUNT(*) FROM universal")->fetch_row()[0];
$completed    = (int) $conn->query("SELECT COUNT(*) FROM universal WHERE status='completed'")->fetch_row()[0];
$newLast7     = (int) $conn->query("SELECT COUNT(*) FROM universal WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_row()[0];
$activeUsers  = (int) $conn->query("SELECT COUNT(DISTINCT user_id) FROM universal WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_row()[0];

/* ──────────────  DAILY LINE-CHART DATA  ────────────── */
$deals = $conn->query("
  SELECT DATE(created_at) AS dt,
         COUNT(*)         AS amt
    FROM universal
   GROUP BY dt
   ORDER BY dt ASC
")->fetch_all(MYSQLI_ASSOC);

/* ──────────────  KPI CARDS CONFIG  ────────────── */
$kpis = [
  ['metric'=>'All Records','value'=>$totalRecords,'dateRange'=>'Last 30 days','color'=>'text-blue-600'],
  ['metric'=>'Completed','value'=>$completed,'dateRange'=>'Since launch','color'=>'text-emerald-500'],
  ['metric'=>'Impact','value'=>$totalRecords?round($completed/$totalRecords*100,1):0,
   'dateRange'=>'Success-rate','color'=>'text-amber-500','isPct'=>true],
];

/* ──────────────  PROGRESS BAR CONFIG  ──────────────
   (replace with real numbers if you track them) */
$progress = [
  ['metric'=>'Published Project','value'=>532,'delta'=>+1.69,'pct'=>45,'bar'=>'bg-rose-500'],
  ['metric'=>'To Do','value'=>4569,'delta'=>-0.50,'pct'=>70,'bar'=>'bg-blue-500'],
  ['metric'=>'In Progress','value'=>89,'delta'=>+0.99,'pct'=>89,'bar'=>'bg-emerald-500','isPct'=>true],
  ['metric'=>'Done','value'=>365,'delta'=>+0.35,'pct'=>60,'bar'=>'bg-amber-500'],
];
?>

<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
<head>  
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <title>Pilota</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <style>
    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="overflow-x-hidden">
    <!-- Header -->
      <header class="flex md:hidden justify-between md:bg-white bg-slate-800 md:px-10 py-[19px] md:py-3 px-3">
        <!-- Left Side Of The Header -->
        <article class="flex items-center gap-4">
          <button aria-label="Search" class="text-white md:text-black">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
          </button>

          <button id="menuBtn" class="top-1 left-2 z-50 md:hidden text-white md:text-black">
            <!-- Hamburger (☰) -->
            <svg id="hamburgerIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M4 6h16M4 12h16M4 18h16"/>  </svg>

            <!-- Close (✕), hidden by default -->
            <svg id="closeIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M6 18L18 6M6 6l12 12"/> </svg>
          </button>
        </article>

        <!-- Right Side Of The Header -->
        <article class="flex items-center gap-8">
          <!-- three-dot menu (horizontal) -->
          <button class="p-2 text-gray-200 hover:text-white">
            <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 6" width="18" height="6" fill="currentColor" aria-label="More options" role="img">
              <circle cx="3"  cy="3" r="3"/>
              <circle cx="12" cy="3" r="3"/>
              <circle cx="21" cy="3" r="3"/>
            </svg>
          </button>
          <button aria-label="Notifications" class="relative md:block hidden">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <!-- badge -->
          <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-rose-500 rounded-full">5</span>
          </button>

          <button aria-label="Messages" class="relative hidden md:block">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <!-- badge -->
            <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-emerald-500 rounded-full">3</span>
          </button>
            
          <!-- Profile -->
          <div class="rounded-full bg-gray-200 w-10 h-10 py-2 px-4 md:block hidden">S</div>
        </article>
      </header>
  <main class="flex">
    <!-- Aside -->
    <aside id="sidebar" class="w-60 md:block hidden bg-[#263544] overflow-x-none min-h-screen bg-slate-800">
    <a href="/ItemPilot/home.php">
      <div class="flex items-center gap-2 px-4 py-4">
        <!-- Logo icon -->
        <img src="images/icon(1).png" alt="Pilota logo" class="h-6 w-6 md:h-8 md:w-8 shrink-0"/>

        <!-- Brand name -->
        <span class="text-white font-semibold text-lg tracking-wide">Pilota</span>
      </div>
    </a>
      <nav>
        <ul>
          <!-- DASHBOARD -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-5" id="home">
            <svg class="w-4 h-4 mt-[2px] text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M3 11l9-7 9 7" />   
            <path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10" />
            <path d="M9 21V12h6v9" />          
            </svg>
            <li><a href="#home" class="text-gray-400">Dashboard</a></li>
          </div>
          <!-- TABLES -->
          <button class="w-60 px-2 ml-4 mt-3 py-1 flex items-center justify-start">
            <div id="events" class="select-none">
              <span class="flex justify-center items-center gap-2 text-gray-400">
                <!-- icon -->
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                  <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span class="text-gray-400">Tables</span>
              </span>
            </div>
            <div id="tablesItem" type="button">
              <svg class="chev text-gray-400 w-4 h-4 transition-transform ml-10" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M6 9l6 6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            </button>
            <!-- submenu (INDENTED, NOT absolute) -->
            <ul id="dropdown" class="hidden pl-8 mt-1 space-y-1">
              <?php $res = $conn->query("SELECT id, title FROM universal WHERE user_id = {$uid} ORDER BY id ASC LIMIT 1");
                if ($res->num_rows): while ($row = $res->fetch_assoc()): ?>
                <li>
                  <a href="#" id="universal" class="block px-4 py-2 text-gray-300 hover:text-white">
                    <?= htmlspecialchars($row['title']) ?>
                  </a>
                </li>
              <?php endwhile; else: ?>
                <li class="px-4 py-2 italic text-gray-400">No tables yet.</li>
              <?php endif; ?>
            </ul>
          </div>
          <!-- CONTACT US -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="contact">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/>
            </svg>
            <li><a href="#contact" class="text-gray-400">Contact Us</a></li>
          </div>
          <!-- DATA TOOLS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="data-tools">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/>
            </svg>
            <li><a href="#data-tools" class="text-gray-400">Data Tools</a></li>
          </div>
          <!-- INSIGHTS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="insights">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <polyline points="3 3 21 3 21 21"/><line x1="3" y1="17" x2="21" y2="17"/>
              <line x1="3" y1="11" x2="21" y2="11"/>
            </svg>
            <li><a href="#insights" class="text-gray-400">Insights</a></li>
          </div>
          <!-- USER TOOLS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="user-tools">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <li><a href="#user-tools" class="text-gray-400">User Tools</a></li>
          </div>
          <!-- SETTINGS -->
          <div class="w-60 py-1 cursor-pointer px-2 flex justify-start ml-4 gap-2 mt-3" id="settings">
            <svg class="w-4 h-4 text-gray-400 mt-[2px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="3"/>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            <li><a href="#settings" class="text-gray-400">Settings</a></li>
          </div>  
        </ul>
      </nav>  
    </aside>

    <!-- amCharts 5 -->
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

    <section class="bg-gray-100 rounded-md w-full" id="home-right">
      <!-- Header -->
      <header class="md:flex hidden justify-between md:bg-white bg-slate-800 md:px-10 py-[19px] md:py-3 px-3">
        <!-- Left Side Of The Header -->
        <article class="flex items-center gap-4">
          <button aria-label="Search" class="text-white md:text-black">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
          </button>

          <button id="menuBtn" class="top-1 left-2 z-50 md:hidden text-white md:text-black">
            <!-- Hamburger (☰) -->
            <svg id="hamburgerIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M4 6h16M4 12h16M4 18h16"/>  </svg>

            <!-- Close (✕), hidden by default -->
            <svg id="closeIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M6 18L18 6M6 6l12 12"/> </svg>
          </button>
        </article>

        <!-- Right Side Of The Header -->
        <article class="flex items-center gap-8">
          <!-- three-dot menu (horizontal) -->
          <button class="p-2 text-gray-200 hover:text-white">
            <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 6" width="18" height="6" fill="currentColor" aria-label="More options" role="img">
              <circle cx="3"  cy="3" r="3"/>
              <circle cx="12" cy="3" r="3"/>
              <circle cx="21" cy="3" r="3"/>
            </svg>
          </button>
          <button aria-label="Notifications" class="relative md:block hidden">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <!-- badge -->
          <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-rose-500 rounded-full">5</span>
          </button>

          <button aria-label="Messages" class="relative hidden md:block">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <!-- badge -->
            <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-emerald-500 rounded-full">3</span>
          </button>
            
          <!-- Profile -->
          <div class="rounded-full bg-gray-200 w-10 h-10 py-2 px-4 md:block hidden">S</div>
        </article>
      </header>

      <article class="md:ml-15 md:mr-25 ml-5 mr-5 mb-10">
        <div class="flex mt-10 mb-5">
          <img src="images/home-dashboard.png" alt="Home Dashboard" class="w-10 h-10 rounded-full mr-3">
          <h4 class="md:text-sm text-md font-medium mt-3 text-gray-600">Overview</h4>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-6">Deals Analytics</h3>
            <div id="dealsChart" style="height:330px;"></div>
          </div>

          <div class="flex flex-col gap-6">
            <?php foreach ($kpis as $k): ?>
              <div class="bg-white p-6 rounded-xl shadow flex justify-between items-center">
                <div>
                  <div class="text-sm text-gray-500"><?= $k['metric'] ?></div>
                  <div class="text-2xl font-bold <?= $k['color'] ?>">
                    <?= $k['isPct'] ?? false ? $k['value'].'%' : number_format($k['value']) ?>
                  </div>
                  <div class="text-xs text-gray-500"><?= $k['dateRange'] ?></div>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center
                  <?php echo str_contains($k['color'],'blue')   ? 'bg-blue-500/20 text-blue-600'   :
                  (str_contains($k['color'],'emerald') ? 'bg-emerald-500/20 text-emerald-600' :
                  (str_contains($k['color'],'amber')   ? 'bg-amber-500/20 text-amber-600' : 'bg-gray-200'));?>">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="9"></circle>
                  <path d="M12 8v4l2 2"></path>
                  </svg>
                </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mt-8 bg-white p-6 rounded-xl shadow">
            <div class="grid lg:grid-cols-4 gap-8">
              <?php foreach ($progress as $i => $p): ?>
                <div>
                  <div class="font-medium"><?= $p['metric'] ?></div>
                  <div class="text-lg font-bold">
                  <?= $p['isPct'] ?? false ? $p['value'].'%' : number_format($p['value']) ?>
                  <span class="<?= $p['delta'] >= 0 ? 'text-emerald-500' : 'text-rose-500' ?> text-sm pl-1">
                  <?= $p['delta'] >= 0 ? '+' : '' ?><?= $p['delta'] ?>%
                  </span>
                </div>
                <div class="h-2 bg-gray-200 rounded mt-2 relative overflow-hidden">
                  <div class="h-2 <?= $p['bar'] ?> absolute left-0 top-0" style="width:<?= $p['pct'] ?>%"></div>
                  <div class="absolute -top-1.5 left-[calc(<?= $p['pct'] ?>%-6px)] w-3 h-3 rounded-full border border-white bg-white shadow"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </article>
      </section>  


  <!-- Contact Us -->
  <section class="bg-gray-100 rounded-md hidden w-full" id="contact-right">
    <!-- Header -->
    <header class="md:flex hidden justify-between md:bg-white bg-slate-800 md:px-10 py-[19px] md:py-3 px-3">
      <!-- Left Side Of The Header -->
      <article class="flex items-center gap-4">
        <button aria-label="Search" class="text-white md:text-black">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="7" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
        </button>

        <button id="menuBtn" class="top-1 left-2 z-50 md:hidden text-white md:text-black">
          <!-- Hamburger (☰) -->
          <svg id="hamburgerIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M4 6h16M4 12h16M4 18h16"/>  </svg>

          <!-- Close (✕), hidden by default -->
          <svg id="closeIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M6 18L18 6M6 6l12 12"/> </svg>
        </button>
      </article>

      <!-- Right Side Of The Header -->
      <article class="flex items-center gap-8">
        <!-- three-dot menu (horizontal) -->
        <button class="p-2 text-gray-200 hover:text-white">
          <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 6" width="18" height="6" fill="currentColor" aria-label="More options" role="img">
            <circle cx="3"  cy="3" r="3"/>
            <circle cx="12" cy="3" r="3"/>
            <circle cx="21" cy="3" r="3"/>
          </svg>
        </button>
        <button aria-label="Notifications" class="relative md:block hidden">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <!-- badge -->
        <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-rose-500 rounded-full">5</span>
        </button>

        <button aria-label="Messages" class="relative hidden md:block">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          <!-- badge -->
          <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-emerald-500 rounded-full">3</span>
        </button>
            
        <!-- Profile -->
        <div class="rounded-full bg-gray-200 w-10 h-10 py-2 px-4 md:block hidden">S</div>
      </article>
    </header>
    
    <div class="flex gap-2 md:px-50 mt-20">
      <img src="images/contact.png" alt="Contact" class="w-10 h-10 rounded-full mr-3">
      <h4 class="md:text-sm text-md font-medium mt-3 text-gray-600">Contact Us</h4>
    </div>
    <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50 md:mr-0 ml-4 mr-4"></div>

      <!-- Contact Form Is Here -->
      <form action="" class="md:ml-0 md:mr-0 ml-4 mr-4">
        <div class="md:flex md:text-start text-center md:gap-50 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Name</h1>
            <p class="text-sm text-gray-600">Your full name so we know who’s reaching out.</p>
          </div>
          <input type="text" class="border-1 bg-white border-gray-400 h-8 rounded-lg w-100 md:mt-9 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-64 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Email Address</h1>
            <p class="text-sm text-gray-600">We’ll use this to reply to your message.</p>
          </div>
          <input type="email" class="border-1 bg-white border-gray-400 h-8 rounded-lg w-100 md:mt-9 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-54 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Subject</h1>
            <p class="text-sm text-gray-600">A brief summary of your question or request.</p>
          </div>
          <input type="text" class="border-1 border-gray-400 h-20 bg-white rounded-lg w-100 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
        <div class="md:flex md:text-start text-center md:gap-82 md:px-50">
          <div class="grid mt-6 mb-6">
            <h1 class="text-lg font-medium">Message</h1>
            <p class="text-sm text-gray-600">Tell us how we can help you.</p>
          </div>
          <input type="text" class="border-1 border-gray-400 h-20 bg-white rounded-lg w-100 mt-3 px-2 text-sm">
        </div>
        <div class="h-[1px] md:ml-50 bg-gray-200 md:w-240 w-100 mt-2 md:ml-50"></div>
          
        <button class="bg-blue-600 hover:bg-blue-500 mb-5 text-white rounded-lg py-1 px-4 cursor-pointer mt-4 md:ml-50" type="submit">Reach Out</button>
      </form>
  </section>

  <!-- Tables Section --->
  <section class="bg-gray-100 rounded-md hidden w-full" id="event-right">
    <!-- Header -->
      <header class="md:flex hidden justify-between md:bg-white bg-slate-800 md:px-10 py-[19px] md:py-3 px-3">
        <!-- Left Side Of The Header -->
        <article class="flex items-center gap-4">
          <button aria-label="Search" class="text-white md:text-black">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
          </button>

          <button id="menuBtn" class="top-1 left-2 z-50 md:hidden text-white md:text-black">
            <!-- Hamburger (☰) -->
            <svg id="hamburgerIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M4 6h16M4 12h16M4 18h16"/>  </svg>

            <!-- Close (✕), hidden by default -->
            <svg id="closeIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">  <path stroke-linecap="round" stroke-linejoin="round"  d="M6 18L18 6M6 6l12 12"/> </svg>
          </button>
        </article>

        <!-- Right Side Of The Header -->
        <article class="flex items-center gap-8 mr-9">
          <!-- three-dot menu (horizontal) -->
          <button class="p-2 text-gray-200 hover:text-white" id="threeDots">
            <svg class="md:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 6" width="18" height="6" fill="currentColor" aria-label="More options" role="img">
              <circle cx="3"  cy="3" r="3"/>
              <circle cx="12" cy="3" r="3"/>
              <circle cx="21" cy="3" r="3"/>
            </svg>
          </button>
        <div class="flex items-center gap-8 mr-9" id="mobileNav">  
          <button aria-label="Notifications" class="relative md:block hidden">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <!-- badge -->
          <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-rose-500 rounded-full">5</span>
          </button>

          <button aria-label="Messages" class="relative hidden md:block">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <!-- badge -->
            <span class="absolute -top-2 -right-2 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-emerald-500 rounded-full">3</span>
          </button>
            
          <!-- Profile -->
          <div class="rounded-full bg-gray-200 w-10 h-10 py-2 px-4 md:block hidden">S</div>
        </div>
        </article>
      </header>

    <div class="md:flex md:justify-between md:px-50 md:ml-0 md:mr-0 ml-4 mr-4 mt-20 md:mb-15 mb-5">
      <div class="flex gap-5">
        <input type="search" placeholder="Search tables..." class="rounded-lg px-2 border-1 bg-white border-gray-300 h-10 w-80">
        <select name="" id="" class="border-1 border-gray-300 bg-white rounded-lg px-2">
          <option value="name">Sort by name</option>
          <option value="date">Sort by date</option>
          <option value="status">Sort by status</option>
        </select>
      </div>

      <div class="flex justify-center md:block">
        <button class="bg-blue-600 hover:bg-blue-500 text-white rounded-lg py-2 px-4 cursor-pointer md:mt-0 mt-5 modal-open" type="submit" data-modal-target="categories">Choose a template</button>
      </div>
    </div>

    <div class="h-[1px] bg-gray-200 md:w-240 w-100 mt-2 md:ml-50 md:mr-0 ml-4 mr-4"></div>
    
    <div class="bg-white rounded-lg md:ml-10 md:mr-15 py-10 md:mt-0 mt-5 overflow-x-auto mb-5">
      <div class="flex gap-1 ml-5 mb-3">
        <img src="images/table.png" alt="Contact" class="w-10 h-10 rounded-full">
        <h4 class="md:text-sm text-md font-medium mt-3 text-gray-600">All Tables</h4>
      </div>

      <table class="w-300 text-sm ml-5 mr-5">
        <!-- invisible header just to lock column widths -->
        <thead class="sr-only">
          <tr>
            <th>Name</th><th>Notes</th><th>Assignee</th><th>Status</th><th>Attachment</th>
          </tr>
        </thead>

        <tbody>
        <?php
          $res = $conn->query("SELECT id, name, notes, assignee, status, attachment_summary FROM universal WHERE user_id = {$uid} ORDER BY id ASC");
          if ($res->num_rows):
          while ($row = $res->fetch_assoc()):
        ?>
          <tr class="border-b border-gray-200 hover:bg-gray-100 cursor-pointer">
            <!-- Name -->
            <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
              <?= htmlspecialchars($row['name']) ?>
            </td>

            <!-- Notes -->
            <td class="px-4 py-2 text-gray-700">
              <?= htmlspecialchars($row['notes']) ?>
            </td>

            <!-- Assignee -->
            <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
              <?= htmlspecialchars($row['assignee']) ?>
            </td>

            <!-- Status badge -->
            <td class="px-4 py-2">
              <?php
                $badge = match ($row['status']) {
                  'Done'        => 'bg-green-100 text-green-800',
                  'In Progress' => 'bg-yellow-100 text-yellow-800',
                  default       => 'bg-gray-100 text-gray-800',
                };
              ?>
              <span class="inline-block px-2 py-1 rounded-full <?= $badge ?>">
                <?= htmlspecialchars($row['status']) ?>
              </span>
            </td>

            <!-- Attachment preview -->
            <td class="px-4 py-2 text-gray-500">
              <?php if ($row['attachment_summary']): ?>
                <img src="/ItemPilot/categories/Universal Table/uploads/<?= htmlspecialchars($row['attachment_summary']) ?>"
                    class="w-20 h-10 rounded-md object-cover" alt="Attachment">
              <?php else: ?>
                <span class="italic text-gray-400">None</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php
            endwhile;
          else:
        ?>
          <tr>
            <td colspan="5" class="px-4 py-4 text-center italic text-gray-400">
              No tables yet.
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- CATEGORIES MODAL -->
  <main id="categories" class="fixed inset-0 z-50 hidden max-w-md mx-auto p-12 overflow-auto shadow-md rounded-lg mt-5 mb-5 bg-white/100">
    <!-- Header -->
    <header class="flex justify-between">
      <h1 class="text-xl font-semibold">Choose a template</h1>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 modal-close cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </header>

    <!-- Some Templates Will Be Shown/Add Here -->
    <section class="mt-20 space-y-5">
      <!-- Universal Table -->
      <article class="flex justify-between items-center mb-4" id="blank">
        <div class="border rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19" stroke-linecap="round" stroke-linejoin="round"/><line x1="5" y1="12" x2="19" y2="12" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg text-start">Start with a blank base</h1>
          <p class="text-sm text-gray-300 text-start">Create custom tables, fields, views</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>
      
      <!-- Groceries Table -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-yellow-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13a2 2 0 100 4 2 2 0 000-4m10 0a2 2 0 100 4 2 2 0 000-4"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Grocery List</h1>
          <p class="text-sm text-gray-300">Organize shopping list,for market</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Sales Strategy Table -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-blue-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V5m0 14v-3"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Sales Strategy</h1>
          <p class="text-sm text-gray-300">Unify sales, marketing, products...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Blog Editorial Calendar -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-red-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Blog Editorial Calendar</h1>
          <p class="text-sm text-gray-300">Organize article ideas and flexibly</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Study Guides -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-blue-200 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 8.25h6M9 11.25h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 4.5h13.5a.75.75 0 01.75.75v14.25a.75.75 0 01-.75.75H5.25a.75.75 0 01-.75-.75V5.25a.75.75 0 01.75-.75z"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Study Guides</h1>
          <p class="text-sm text-gray-300">Create structured notes for subject</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Job Hunting -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-purple-600 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.5h19.5V18a2.25 2.25 0 01-2.25 2.25H4.5A2.25 2.25 0 012.25 18V7.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6a3.75 3.75 0 017.5 0v1.5"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Job Hunting</h1>
          <p class="text-sm text-gray-300">Stay organized during ur job search</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Home Remodel -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-orange-600 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l7-8 11 8v8a2 2 0 01-2 2H5a2 2 0 01-2-2v-8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 21V12h6v9"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Home Remodel</h1>
          <p class="text-sm text-gray-300">Manage all aspect of the remodeling</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Car Buying -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-gray-600 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13l1-5h16l1 5M5 16a2 2 0 100 4 2 2 0 000-4m14 0a2 2 0 100 4 2 2 0 000-4"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Car Buying</h1>
          <p class="text-sm text-gray-300">Monitor anique dealers, pricing and..</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Pet Meidcal History -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-pink-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 14a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0zm-3-4a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm9 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm-3-3a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m2-2h-4"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Pet Medical History</h1>
          <p class="text-sm text-gray-300">Track pet medications and appoint...</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>

      <!-- Expense Tracking -->
      <article class="flex justify-between items-center mb-4">
        <div class="bg-pink-400 rounded-sm px-3 py-2 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="2" ry="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M2 11h20"/></svg>
        </div>
        <div class="grid">
          <h1 class="text-lg">Expense Tracking</h1>
          <p class="text-sm text-gray-300">Capture and organize all reciepts a..</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </article>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function() {
  const eventRight   = document.getElementById("event-right");
  const homeRight    = document.getElementById("home-right");
  const contactRight = document.getElementById("contact-right");
  const blank        = document.getElementById("blank");
  const universal    = document.getElementById("universal");
  const openTable    = document.getElementById("openTable");

  let currentPage = parseInt(new URLSearchParams(window.location.search).get("page")) || 1;
  let currentId = parseInt(new URLSearchParams(window.location.search).get("table_id")) || 1;

  function loadTable(page) {
    fetch(`categories/Universal Table/insert_universal.php?page=${page}&table_id=${currentId}`)
      .then(r => r.text())
      .then(html => {
        eventRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        eventRight.style.display = "block";
        currentPage = page;
      });
  }

  document.querySelectorAll('.template-item').forEach(el => {
    el.addEventListener('click', () => {
      const id   = el.dataset.id;
      const name = el.dataset.name;
      const sel  = document.getElementById('selectedTemplate');
      if (sel) sel.textContent = name;
      window.location.href = `home.php?table_id=${id}&page=${currentPage}`;
    });
  });

  const menuBtn = document.getElementById('menuBtn');
  if (menuBtn) {
    const sidebar       = document.getElementById('sidebar');
    const hamburgerIcon = document.getElementById('hamburgerIcon');
    const closeIcon     = document.getElementById('closeIcon');
    menuBtn.addEventListener('click', () => {
      const nowVisible = !sidebar.classList.toggle('hidden');
      if (hamburgerIcon && closeIcon) {
        hamburgerIcon.classList.toggle('hidden', nowVisible);
        closeIcon.classList.toggle('hidden', !nowVisible);
      }
    });
  }

  const homeTab    = document.getElementById("home");
  const contactTab = document.getElementById("contact");
  const eventsTab  = document.getElementById("events");

  if (homeTab)    homeTab.addEventListener('click', () => { homeRight.style.display = "block"; contactRight.style.display = "none"; eventRight.style.display = "none"; });
  if (contactTab) contactTab.addEventListener('click', () => { homeRight.style.display = "none"; contactRight.style.display = "block"; eventRight.style.display = "none"; });
  if (eventsTab)  eventsTab.addEventListener('click', () => { homeRight.style.display = "none"; contactRight.style.display = "none"; eventRight.style.display = "block"; });

  document.querySelectorAll('[data-modal-target]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const tgt = document.getElementById(btn.dataset.modalTarget);
      if (tgt) tgt.classList.remove('hidden');
    });
  });

  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.fixed');
      if (modal) modal.classList.add('hidden');
    });
  });

  ['register-modal','login-modal'].forEach(id => {
    const modal = document.getElementById(id);
    if (modal) {
      modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.add('hidden');
      });
    }
  });

  [blank, universal].forEach(el => {
    if (el && eventRight) {
      el.addEventListener("click", e => {
        e.preventDefault();
        const categories = document.getElementById("categories");
        if (categories) categories.classList.add("hidden");

        if (el === blank) {
          loadTable(1);
        } else {
          loadTable(currentId);
        }
      });
    }
  });

  document.body.addEventListener('click', e => {
    const pg = e.target.closest('.pagination a');
    if (pg) {
      e.preventDefault();
      const url = new URL(pg.href, window.location.origin);
      const p   = parseInt(url.searchParams.get('page')) || 1;
      loadTable(p);
      return;
    }

    const addBtn = e.target.closest('#addIcon');
    if (addBtn) {
      e.preventDefault();
      const addForm = document.getElementById('addForm');
      if (addForm) addForm.classList.remove('hidden');
    }

    const closeAdd = e.target.closest('[data-close-add]');
    if (closeAdd) {
      const addForm = document.getElementById('addForm');
      if (addForm) addForm.classList.add('hidden');
    }

    const editBtn = e.target.closest('#openForm');
    if (editBtn) {
      e.preventDefault();
      const wrap = document.getElementById('editFormWrapper');
      if (wrap) wrap.classList.remove('hidden');
    }

    const closeModal = e.target.closest('[data-close-modal]');
    if (closeModal) {
      const wrap = document.getElementById('editFormWrapper');
      if (wrap) wrap.classList.add('hidden');
    }

    const openThead = e.target.closest('#openTbodyForm');
    if (openThead) {
      e.preventDefault();
      const theadF = document.getElementById('theadForm');
      if (theadF) theadF.classList.remove('hidden');
    }

    const closeTheadBtn = e.target.closest('[data-close-thead]');
    if (closeTheadBtn) {
      const theadF = document.getElementById('theadForm');
      if (theadF) theadF.classList.add('hidden');
      const tbodyF = document.getElementById('tbodyForm');
      if (tbodyF) tbodyF.classList.add('hidden');
    }
  });

  am5.ready(function() {
    // ---------- root ----------
    var root = am5.Root.new("dealsChart");
    root.setThemes([am5themes_Animated.new(root)]);

    // ---------- chart ----------
    var chart = root.container.children.push(
      am5xy.XYChart.new(root, {
        panX: false, panY: false, wheelX: "none", wheelY: "none",
        layout: root.verticalLayout
      })
    );

    // ---------- axes ----------
    var xAxis = chart.xAxes.push(
      am5xy.DateAxis.new(root, {
        baseInterval: { timeUnit: "day", count: 1 },
        renderer: am5xy.AxisRendererX.new(root, {})
      })
    );
    var yAxis = chart.yAxes.push(
      am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, {})
      })
    );

    // ---------- series ----------
    var series = chart.series.push(
      am5xy.LineSeries.new(root, {
        name: "Deals",
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "amt",
        valueXField: "dt",
        strokeWidth: 2,
        fill: am5.color(0x3b82f6),
        stroke: am5.color(0x3b82f6),
        tooltip: am5.Tooltip.new(root, { labelText: "{valueY}" })
      })
    );
    series.fills.template.setAll({ fillOpacity: 0.15 });

    // ---------- scrollbar ----------
    chart.set("scrollbarX", am5.Scrollbar.new(root, {
      orientation: "horizontal",
      height: 40
    }));

    // ---------- data ----------
    series.data.setAll(
      <?php echo json_encode(array_map(function($d){
        return ['dt'=>strtotime($d['dt'])*1000,'amt'=>$d['amt']];
      }, $deals)); ?>
    );
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault(); // prevent default Enter action
      const form = e.target.closest('form'); // find the form for the current field
      if (form) form.submit();
    }
  });

  $(function () {
  const $arrowBtn = $('#tablesItem');
  const $dd       = $('#dropdown');
  const $chev     = $('#tablesItem .chev');

  function open() {
    if ($dd.is(':visible')) return;
    $dd.stop(true, true).slideDown(160, () => $dd.removeClass('hidden'));
    $chev.addClass('rotate-90');
    $arrowBtn.attr('aria-expanded', 'true');
  }
  function close() {
    if (!$dd.is(':visible')) return;
    $dd.stop(true, true).slideUp(160, () => $dd.addClass('hidden'));
    $chev.removeClass('rotate-90');
    $arrowBtn.attr('aria-expanded', 'false');
  }

  // Toggle via arrow
  $arrowBtn.on('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $dd.is(':visible') ? close() : open();
  });

  // Close only if clicking OUTSIDE dropdown and arrow
  $(document).on('click', function (e) {
    if ($(e.target).closest('#dropdown, #tablesItem').length === 0) {
      close();
    }
  });

  // Esc to close
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
});


  const shouldAutoload = new URLSearchParams(window.location.search).get("autoload");
  if (shouldAutoload) {
    loadTable(currentPage);
  }
})();

</script>
</body>
</html>
