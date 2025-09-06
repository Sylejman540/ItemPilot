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

/* ─────────── HELPERS ─────────── */
function fetch_all_assoc($conn, $sql, $types = '', $params = []) {
  $stmt = $conn->prepare($sql);
  if ($types && $params) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
  return $rows;
}

/**
 * Fill missing daily dates with null (so Apex breaks lines if gaps exist)
 */
function fillMissingDailyWithNull(array $rows): array {
  if (empty($rows)) return [];
  $map = [];
  foreach ($rows as $r) $map[$r['dt']] = isset($r['cnt']) ? (int)$r['cnt'] : null;

  $startDate = $rows[0]['dt'];
  $endDate   = $rows[count($rows)-1]['dt'];

  $out = [];
  $current = new DateTime($startDate);
  $end     = new DateTime($endDate);

  while ($current <= $end) {
    $dt = $current->format('Y-m-d');
    $out[] = ['dt' => $dt, 'cnt' => $map[$dt] ?? null];
    $current->modify('+1 day');
  }
  return $out;
}

/**
 * Fill missing months with null (so no bar drawn for empty months)
 */
function fillMissingMonthlyWithNull(array $rows): array {
  if (empty($rows)) return [];
  $map = [];
  foreach ($rows as $r) $map[$r['mth']] = isset($r['cnt']) ? (int)$r['cnt'] : null;

  $startMonth = $rows[0]['mth'];
  $endMonth   = $rows[count($rows)-1]['mth'];

  $out = [];
  $current = new DateTime($startMonth . "-01");
  $end     = new DateTime($endMonth . "-01");

  while ($current <= $end) {
    $mth = $current->format('Y-m');
    $out[] = ['mth' => $mth, 'cnt' => $map[$mth] ?? null];
    $current->modify('+1 month');
  }
  return $out;
}

/* ─────────── COMMON WHERE ─────────── */
$tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : null;

if ($tableId) {
  $whereUni       = "WHERE u.user_id = ? AND u.table_id = ?";
  $whereSales     = "WHERE s.user_id = ? AND s.table_id = ?";
  $whereGroceries = "WHERE g.user_id = ? AND g.table_id = ?";
} else {
  $whereUni       = "WHERE u.user_id = ?";
  $whereSales     = "WHERE s.user_id = ?";
  $whereGroceries = "WHERE g.user_id = ?";
}

/* ─────────── HEADER CARD METRICS ─────────── */

// Total tables (tables + sales_table + groceries_table)
$totalTables = fetch_all_assoc(
  $conn,
  "SELECT SUM(cnt) as total FROM (
      SELECT COUNT(*) as cnt FROM tables t           WHERE t.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM dresses_table st     WHERE st.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM groceries_table gt WHERE gt.user_id=?
   ) as combined",
  "iii", [$uid, $uid, $uid]
);
$totalTables = $totalTables[0]['total'] ?? 0;

// Total records (universal + sales_strategy + groceries)
$totalRecords = fetch_all_assoc(
  $conn,
  "SELECT SUM(cnt) as total FROM (
      SELECT COUNT(*) as cnt FROM universal u        WHERE u.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM dresses s   WHERE s.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM groceries g        WHERE g.user_id=?
   ) as combined",
  "iii", [$uid, $uid, $uid]
);
$totalRecords = $totalRecords[0]['total'] ?? 0;

// Active this month (new tables created this month; include groceries_table)
$activeThisMonth = fetch_all_assoc(
  $conn,
  "SELECT SUM(cnt) as total FROM (
      SELECT COUNT(*) as cnt FROM tables t 
        WHERE t.user_id=? AND DATE_FORMAT(t.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
      UNION ALL
      SELECT COUNT(*) as cnt FROM dresses_table st 
        WHERE st.user_id=? AND DATE_FORMAT(st.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
      UNION ALL
      SELECT COUNT(*) as cnt FROM groceries_table gt
        WHERE gt.user_id=? AND DATE_FORMAT(gt.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
   ) as combined",
  "iii", [$uid, $uid, $uid]
);
$activeThisMonth = $activeThisMonth[0]['total'] ?? 0;

// Completed tasks (status='Done') — ONLY universal + sales_strategy
$completedTasks = fetch_all_assoc(
  $conn,
  "SELECT SUM(cnt) as total FROM (
      SELECT COUNT(*) as cnt FROM universal u      WHERE u.user_id=? AND u.status='Done'
      UNION ALL
      SELECT COUNT(*) as cnt FROM dresses s WHERE s.user_id=? AND s.status='Done'
   ) as combined",
  "ii", [$uid, $uid]
);
$completedTasks = $completedTasks[0]['total'] ?? 0;

/* ─────────── CHART QUERIES ─────────── */

// 1) Area → New Tables Created (tables + sales_table + groceries_table)
if ($tableId) {
  $typesArea = "iiiiii";
  $argsArea  = [$uid,$tableId, $uid,$tableId, $uid,$tableId];
  $tableFilter = "AND %s.table_id=?";
} else {
  $typesArea = "iii";
  $argsArea  = [$uid, $uid, $uid];
  $tableFilter = "";
}
$areaData = fetch_all_assoc(
  $conn,
  "SELECT dt, SUM(cnt) AS cnt
     FROM (
       SELECT DATE(t.created_at) AS dt, COUNT(*) AS cnt
         FROM tables t
         WHERE t.user_id=? " . sprintf($tableFilter, 't') . "
         GROUP BY DATE(t.created_at)
       UNION ALL
       SELECT DATE(st.created_at) AS dt, COUNT(*) AS cnt
         FROM dresses_table st
         WHERE st.user_id=? " . sprintf($tableFilter, 'st') . "
         GROUP BY DATE(st.created_at)
       UNION ALL
       SELECT DATE(gt.created_at) AS dt, COUNT(*) AS cnt
         FROM groceries_table gt
         WHERE gt.user_id=? " . sprintf($tableFilter, 'gt') . "
         GROUP BY DATE(gt.created_at)
     ) AS combined
   GROUP BY dt
   ORDER BY dt ASC",
  $typesArea, $argsArea
);

// 2) Polar → Records Per Table (universal + sales_strategy + groceries)
$polarSql = 
  "(SELECT t.table_title AS table_name, COUNT(u.id) AS cnt
     FROM universal u
     JOIN tables t ON t.table_id = u.table_id
     WHERE u.user_id=? " . ($tableId ? "AND u.table_id=?" : "") . "
     GROUP BY t.table_title)
   UNION ALL
   (SELECT st.table_title AS table_name, COUNT(s.id) AS cnt
     FROM dresses s
     JOIN dresses_table st ON st.table_id = s.table_id
     WHERE s.user_id=? " . ($tableId ? "AND s.table_id=?" : "") . "
     GROUP BY st.table_title)
   UNION ALL
   (SELECT gt.table_title AS table_name, COUNT(g.id) AS cnt
     FROM groceries g
     JOIN groceries_table gt ON gt.table_id = g.table_id
     WHERE g.user_id=? " . ($tableId ? "AND g.table_id=?" : "") . "
     GROUP BY gt.table_title)";
if ($tableId) {
  $polarData = fetch_all_assoc($conn, $polarSql, "iiiiii", [$uid,$tableId, $uid,$tableId, $uid,$tableId]);
} else {
  $polarData = fetch_all_assoc($conn, $polarSql, "iii", [$uid, $uid, $uid]);
}

// 3) Bar → Tables Per Month (tables + sales_table + groceries_table)
$barSql =
  "SELECT mth, SUM(cnt) AS cnt
     FROM (
       SELECT DATE_FORMAT(t.created_at, '%Y-%m') AS mth, COUNT(*) AS cnt
         FROM tables t
         WHERE t.user_id=? " . ($tableId ? "AND t.table_id=?" : "") . "
         GROUP BY mth
       UNION ALL
       SELECT DATE_FORMAT(st.created_at, '%Y-%m') AS mth, COUNT(*) AS cnt
         FROM dresses_table st
         WHERE st.user_id=? " . ($tableId ? "AND st.table_id=?" : "") . "
         GROUP BY mth
       UNION ALL
       SELECT DATE_FORMAT(gt.created_at, '%Y-%m') AS mth, COUNT(*) AS cnt
         FROM groceries_table gt
         WHERE gt.user_id=? " . ($tableId ? "AND gt.table_id=?" : "") . "
         GROUP BY mth
     ) AS combined
   GROUP BY mth
   ORDER BY mth ASC";
if ($tableId) {
  $barData = fetch_all_assoc($conn, $barSql, "iiiiii", [$uid,$tableId, $uid,$tableId, $uid,$tableId]);
} else {
  $barData = fetch_all_assoc($conn, $barSql, "iii", [$uid, $uid, $uid]);
}

// 4) Radar → Status Distribution (ONLY universal + sales_strategy)
if ($tableId) {
  $radarTypes = "iiii";
  $radarArgs  = [$uid,$tableId, $uid,$tableId];
} else {
  $radarTypes = "ii";
  $radarArgs  = [$uid, $uid];
}
$radarData = fetch_all_assoc(
  $conn,
  "SELECT status, SUM(cnt) as cnt
   FROM (
     SELECT u.status as status, COUNT(*) as cnt
     FROM universal u
     $whereUni
     GROUP BY u.status
     UNION ALL
     SELECT s.status as status, COUNT(*) as cnt
     FROM dresses s
     $whereSales
     GROUP BY s.status
   ) merged
   GROUP BY status",
  $radarTypes, $radarArgs
);

// 5) Line → Records Over Time (universal + sales_strategy + groceries)
if ($tableId) { 
  $lineTypes = "iiiiii"; 
  $lineArgs  = [$uid,$tableId, $uid,$tableId, $uid,$tableId]; 
} else { 
  $lineTypes = "iii";    
  $lineArgs  = [$uid, $uid, $uid]; 
}
$lineData = fetch_all_assoc(
  $conn,
  "SELECT dt, SUM(cnt) AS cnt
     FROM (
       SELECT DATE(u.created_at) AS dt, COUNT(*) AS cnt
         FROM universal u
         $whereUni
         GROUP BY dt
       UNION ALL
       SELECT DATE(s.created_at) AS dt, COUNT(*) AS cnt
         FROM dresses s
         $whereSales
         GROUP BY dt
       UNION ALL
       SELECT DATE(g.created_at) AS dt, COUNT(*) AS cnt
         FROM groceries g
         $whereGroceries
         GROUP BY dt
     ) AS combined
   GROUP BY dt
   ORDER BY dt ASC",
  $lineTypes, $lineArgs
);

// 6) Pie → To Do / In Progress / Done (ONLY universal + sales_strategy)
if ($tableId) {
  $pieTypes = "iiii";
  $pieArgs  = [$uid,$tableId, $uid,$tableId];
} else {
  $pieTypes = "ii";
  $pieArgs  = [$uid,$uid];
}
$pieData = fetch_all_assoc(
  $conn,
  "SELECT status, SUM(cnt) as cnt
   FROM (
     SELECT u.status as status, COUNT(*) as cnt 
     FROM universal u
     $whereUni
     GROUP BY u.status
     UNION ALL
     SELECT s.status as status, COUNT(*) as cnt 
     FROM dresses s
     $whereSales
     GROUP BY s.status
   ) merged
   GROUP BY status",
  $pieTypes, $pieArgs
);

/* ─────────── STATUS MAP (for Pie: only status-bearing tables) ─────────── */
$statuses  = ["To Do", "In Progress", "Done"]; // adjust if you have more
$statusMap = array_fill_keys($statuses, 0);
foreach ($pieData as $row) {
  $status = $row['status'];
  $cnt = (int)$row['cnt'];
  if (isset($statusMap[$status])) $statusMap[$status] = $cnt;
}

/* ─────────── FILL MISSING GAPS FOR CHART AXES ─────────── */
$areaData = fillMissingDailyWithNull($areaData);
$lineData = fillMissingDailyWithNull($lineData);
$barData  = fillMissingMonthlyWithNull($barData);

// ✅ Arrays ready for charts: $areaData, $polarData, $barData, $radarData, $lineData, $statusMap
?>

<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
<head>  
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <title>Pilota</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <style>
    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="overflow-x-hidden bg-gray-100">
<style>
  :root { --sbw: 0px; }                  /* updated by your JS when sidebar opens */

#appHeader2{
  position: fixed;
  top: 0;
  left: var(--sbw);                    /* start after the sidebar */
  right: 0;                            /* stretch to right edge */
  width: auto;                         /* ignore any Tailwind w-* */
  max-width: none;                     /* ignore any Tailwind max-w-* */
  margin: 0;                           /* ignore any mx-*, ml-*, mr-* */
  box-sizing: border-box;
  z-index: 50;
  transition:
    left .3s ease, background-color .2s ease, backdrop-filter .2s ease;
}

#appHeader{
  top: 0;
  left: var(--sbw);                    /* start after the sidebar */
  right: 0;                            /* stretch to right edge */
  width: auto;                         /* ignore any Tailwind w-* */
}

@media (max-width: 767px) {
  /* Dark, clickable overlay behind the sidebar */
  #sb-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45); /* slate-900 @ ~45% */
    backdrop-filter: blur(2px);
    z-index: 55; /* below sidebar, above content */
  }

  /* Put the sidebar above the backdrop */
  #sidebar { z-index: 60; }
  #appHeader2 { z-index: 65; } /* header stays above overlay if you want */

  /* When open, blur the main page content (optional, nice touch) */
  .mobile-dim #page {            /* if you have a #page wrapper */
    filter: blur(3px);
    pointer-events: none;        /* prevent interactions under overlay */
  }

  /* Hard scroll lock on body when sidebar open */
  .no-scroll {
    overflow: hidden !important;
    height: 100vh;
    touch-action: none;          /* prevent scroll on touch devices */
  }
}
</style>

<header id="appHeader2" class="fixed top-0 z-50 flex justify-between md:px-10 py-[19px] md:py-3 px-3 backdrop-blur-xl bg-transparent">
        <!-- Left Side Of The Header -->
        <article class="flex items-center gap-4">
          <button id="menuBtn" class="top-1 left-2 z-50 text-blue-900 cursor-pointer">
            <!-- Hamburger (☰) -->
             <svg id="hamburgerIcon" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <rect x="3" y="6"  width="18" height="2.5" rx="1.25" />
              <rect x="3" y="11" width="14" height="2.5" rx="1.25" />
              <rect x="3" y="16" width="10" height="2.5" rx="1.25" />
            </svg>
          </button>
        </article>
        <?php
          // --- Resolve user from session by id or email ---
          $id    = $_SESSION['id']    ?? $_SESSION['user_id'] ?? null;
          $email = $_SESSION['email'] ?? null;

          $user = null;
          if ($id !== null) {
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
          } elseif (!empty($email)) {
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
          }

          if (isset($stmt)) {
            $stmt->execute();
            $res  = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();
          }

          // --- Derive avatar label ---
          $label = 'U'; // fallback
          if ($user) {
            if (!empty($user['name'])) {
              $parts = preg_split('/\s+/', trim($user['name']));
              $label = strtoupper(substr($parts[0], 0, 1));
            } elseif (!empty($user['email'])) {
              $label = strtoupper(substr($user['email'], 0, 1));
            } elseif (!empty($user['id'])) {
              $label = 'U';
            }
          }

          // Notification badge (ensure variable exists)
          $notif_count = isset($notif_count) ? (int)$notif_count : 0;
        ?>
        <article style="position: relative; display: inline-block;">
          <!-- Trigger -->
          <button class="px-3 py-1 rounded-full bg-[#B5707D] text-white" id="profile-dropdown"> <?= htmlspecialchars($label) ?></button>

          <!-- Menu -->
          <section style="position: absolute; right: 0; z-index: 1000;" class="hidden bg-white text-black p-4 md:p-5 rounded-lg shadow-lg w-60 mt-2" id="profile-dropdown-menu">
            <!-- ACCOUNT -->
            <div class="px-1 py-2">
              <p class="text-xs font-semibold text-gray-400 tracking-wide uppercase">Account</p>
              <div class="py-2">
                <div class="mt-2 flex items-center">
                  <div class="w-8 h-8 bg-[#B5707D] rounded-full flex items-center justify-center text-white font-bold">
                    <?= htmlspecialchars($label) ?>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm font-medium text-gray-800">
                      <?= $user && !empty($user['name']) ? htmlspecialchars($user['name']) : 'User' ?>
                    </p>
                  </div>
                </div>

                <div class="mt-3 space-y-1">
                  <button id="manageTab" class="flex items-center px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 rounded">
                    Manage account
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="w-4 h-4 ml-auto text-gray-400"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.828 10.172a4 4 0 010 5.656m-1.414-1.414a2 2 0 112.828-2.828"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12h.01"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- HELP -->
            <div class="px-1 py-2 space-y-1 border-t border-gray-100">
              <a href="./dropdown/help.php" class="block px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 rounded">
                Help
              </a>
            </div>

            <!-- LOG OUT -->
            <div class="px-1 py-2 bg-gray-50 border-t border-gray-100 rounded-b-lg">
              <form action="./register/logout.php" method="POST">
                <button type="submit" class="w-full text-left px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 rounded">
                  Log out
                </button>
              </form>
            </div>
          </section>
        </article>
  </header>

<div id="success-message"
     class="fixed top-20 md:left-1/2 md:ml-0 ml-20 transform md:-translate-x-1/2
            px-6 py-3 rounded-lg shadow-lg text-sm font-medium
            transition-opacity duration-500
            <?php if (empty($_SESSION['flash'])): ?> hidden <?php endif; ?>
            <?php if (!empty($_SESSION['flash']) && str_starts_with($_SESSION['flash'], '✅')): ?>
              text-zinc-800 bg-zinc-50 border border-zinc-200
            <?php else: ?>
              text-red-800 bg-red-50 border border-red-200
            <?php endif; ?>">
  <?php if (!empty($_SESSION['flash'])): ?>
    <?= htmlspecialchars($_SESSION['flash']) ?>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
</div>



  
<div id="account" class="hidden inset-0 md:fixed absolute mt-14 md:ml-150 h-screen overflow-none md:ml-0 md:mr-0 ml-3 mr-3">
  <div class="w-full max-w-md bg-white px-8 py-5 rounded-2xl border border-gray-200">
  <h1 class="text-3xl font-extrabold text-center text-[#263544] mb-4 mt-10">Manage Your Account</h1>

    <form action="/ItemPilot/account/manage-account.php" method="POST" class="space-y-6">
      <!-- Name -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Name</label>
        <input type="text" name="name" required
               class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
               value="<?= htmlspecialchars($user['name'] ?? '') ?>">
      </div>

      <!-- Password change (verify old first) -->
      <fieldset class="space-y-4">
        <legend class="text-sm font-semibold text-gray-700">Change Password (optional)</legend>

        <div>
          <label class="block text-sm text-gray-700 mb-1">Current Password</label>
          <input type="password" name="current_password"
                 class="w-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Enter your current password">
        </div>

        <div>
          <label class="block text-sm text-gray-700 mb-1">New Password</label>
          <input type="password" name="new_password"
                 class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="At least 8 characters">
        </div>

        <div>
          <label class="block text-sm text-gray-700 mb-1">Confirm New Password</label>
          <input type="password" name="new_password_confirm"
                 class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Re-enter new password">
        </div>
      </fieldset>

      <!-- Actions -->
      <div class="flex justify-between items-center">
        <button type="submit"
                  class="bg-[#263544] cursor-pointer hover:bg-slate-800 text-white px-6 py-3 rounded-full font-semibold transition">
          Save Changes
        </button>

        <!-- Make delete actually post -->
        <button type="submit" name="delete_account" value="1"
                class="text-red-600 hover:underline text-sm font-medium"
                onclick="return confirm('Are you sure you want to delete your account? This cannot be undone.');">
          Delete Account
        </button>
      </div>
    </form>
  </div>
  </div>
   
  <?php require_once __DIR__ . '/components/header.php'; ?>

  <?php require_once __DIR__ . '/components/dashboard.php'; ?>

  <?php require_once __DIR__ . '/components/table.php'; ?>

  <?php require_once __DIR__ . '/components/contact.php'; ?>

  <?php require_once __DIR__ . '/components/categories.php'; ?>

  <?php require_once __DIR__ . '/components/insight.php'; ?>
<style>
  .no-scroll {
  overflow: hidden;     /* block scrolling */
  height: 100vh;        /* lock viewport height */
  position: fixed;      /* freeze body so it doesn't shift */
  width: 100%;          /* prevent content shift */
}
.row-dim{opacity:.45}
.cell-hit{background:rgba(59,130,246,.06);box-shadow:0 0 0 2px rgba(59,130,246,.15) inset;border-radius:.5rem}
.ctrl-hit{outline:2px solid rgba(59,130,246,.45);background:rgba(59,130,246,.06)}

</style>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function() {
  const eventRight   = document.getElementById("event-right");
  const homeRight    = document.getElementById("home-right");
  const contactRight = document.getElementById("contact-right");
  const insightRight = document.getElementById("insight-right");
  const blank        = document.getElementById("blank");        // "Create New" button
  const universal    = document.getElementById("universal");    // optional "Open current table" button
  const dropdown     = document.getElementById("dropdown");     // the tables list (UL)
  const sales        = document.getElementById("sales-strategy");  // sales template card
  const strategy     = document.getElementById("strategy");       // optional open strategy button
  const groceries    = document.getElementById("groceries");
  const football     = document.getElementById("football");
  const footballBtn = document.getElementById("club");

  let currentPage    = parseInt(new URLSearchParams(window.location.search).get("page")) || 1;
  let currentId      = parseInt(new URLSearchParams(window.location.search).get("table_id")) || null;
  let currentSalesId = null;

  // -------- core loaders (Universal) --------
  function loadTable(tableId, page = 1) {
    if (!tableId) return;
    currentId = tableId;
    fetch(`categories/Universal%20Table/insert_universal.php?page=${page}&table_id=${tableId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newTable(page = 1) {
    fetch(`categories/Universal%20Table/insert_universal.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  // -------- actions: create/open universal --------
  if (blank && eventRight) {
    blank.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newTable(1);
    });
  }

  if (universal && eventRight) {
    universal.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(universal.dataset.tableId || "", 10);
      loadTable(!isNaN(idFromBtn) ? idFromBtn : currentId, currentPage || 1);
    });
  }

  // -------- SALES STRATEGY loaders --------
  function loadStrategy(salesId, page = 1) {
    if (!salesId) return;
    currentSalesId = salesId;
    fetch(`categories/Dresses/insert_dresses.php?page=${page}&table_id=${salesId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newStrategy(page = 1) {
    fetch(`categories/Dresses/insert_dresses.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight) eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  if (sales && eventRight) {
    sales.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newStrategy(1);
    });
  }

  if (strategy && eventRight) {
    strategy.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(strategy.dataset.tableId || "", 10);
      loadStrategy(!isNaN(idFromBtn) ? idFromBtn : currentSalesId, currentPage || 1);
    });
  }

  const G_PATH = 'categories/Groceries%20Table/insert_groceries.php';

  function loadGroceriesTable(groceryId, page = 1) {
    if (!groceryId) return;
    currentId = groceryId;
    fetch(`${G_PATH}?page=${page}&table_id=${groceryId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)   homeRight.style.display = "none";
        if (eventRight)  eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newGroceriesTable(page = 1) {
    fetch(`${G_PATH}?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)   homeRight.style.display = "none";
        if (eventRight)  eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  if (groceries && eventRight) {
    groceries.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newGroceriesTable(1);
    });
  }

  // 2) Open existing (from sidebar/list links with class="js-groceries-link")
  document.addEventListener("click", e => {
    const link = e.target.closest(".js-groceries-link");
    if (!link || !eventRight) return;

    e.preventDefault();
    document.getElementById("categories")?.classList.add("hidden");

    const idFromBtn = parseInt(link.dataset.tableId || "", 10);
    loadGroceriesTable(!Number.isNaN(idFromBtn) ? idFromBtn : currentId, currentPage || 1);
  });

  // -------- FOOTBALL loaders --------
  function loadFootball(footballId, page = 1) {
    if (!footballId) return;
    currentFootballId = footballId;
    fetch(`categories/Football%20Table/insert_football.php?page=${page}&table_id=${footballId}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight)   eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  function newFootball(page = 1) {
    fetch(`categories/Football%20Table/insert_football.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        insightRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (eventRight)   eventRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        insightRight.style.display = "block";
        currentPage = page;
      });
  }

  if (football && eventRight) {
    football.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newFootball(1);
    });
  }

  if (footballBtn && eventRight) {
    footballBtn.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(footballBtn.dataset.tableId || "", 10);
      loadFootball(!isNaN(idFromBtn) ? idFromBtn : currentFootballId, currentPage || 1);
    });
  }


  // -------- Dropdown delegation (detect src) --------
  if (dropdown && eventRight) {
    dropdown.addEventListener("click", e => {
      const link = e.target.closest(".js-table-link, .js-strategy-link, .js-groceries-link, .js-football-link");
      if (!link) return;
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");

      const tableId = parseInt(link.dataset.tableId || "", 10);
      const src     = link.dataset.src;

      if (!isNaN(tableId)) {
        if (src === "sales_table") {
          loadStrategy(tableId, 1);
        } else if(src === "groceries_table"){
          loadGroceriesTable(tableId, 1);
        } else if(src === "football_table"){
          loadFootball(tableId, 1);
        }else {
          loadTable(tableId, 1);
        }
      }
    });
  }

  // -------- template picker --------
  document.querySelectorAll('.template-item').forEach(el => {
    el.addEventListener('click', () => {
      const id   = el.dataset.id;
      const name = el.dataset.name;
      const sel  = document.getElementById('selectedTemplate');
      if (sel) sel.textContent = name;
      window.location.href = `home.php?table_id=${id}&page=${currentPage}`;
    });
  });

  // -------- profile dropdown --------
  const profileDropdown = document.getElementById('profile-dropdown');
  const profileDropdownMenu = document.getElementById('profile-dropdown-menu');
  if (profileDropdown && profileDropdownMenu) {
    profileDropdown.addEventListener('click', (event) => {
      event.stopPropagation();
      profileDropdownMenu.style.display =
        profileDropdownMenu.style.display === "block" ? "none" : "block";
    });
    document.body.addEventListener('click', () => {
      profileDropdownMenu.style.display = "none";
    });
  }

  // -------- success message fadeout --------
  document.addEventListener("DOMContentLoaded", () => {
    const msg = document.getElementById("success-message");
    if (msg && !msg.classList.contains("hidden")) {
      setTimeout(() => {
        msg.style.opacity = "0";
        setTimeout(() => msg.remove(), 500);
      }, 3000);
    }
  });
  
  // const showTemplates = document.getElementById('showTemplates');

  //   showTemplates.addEventListener('click', () =>{
  //   const templates = document.getElementById('templates');

  //   templates.style.display = 'block';
  // })

  const menuBtn   = document.getElementById('menuBtn');
  const sidebar   = document.getElementById('sidebar');
  const root      = document.documentElement;
  const appHeader = document.getElementById('appHeader');

  if (menuBtn && sidebar) {
  const OFFSET_PX = -2;
  const PEEK_PX   = 20;

  const isHidden = () => !sidebar.classList.contains('show');
  const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

  function sidebarWidth() {
    sidebar.classList.add('show'); // temporarily show
    const w = Math.ceil(sidebar.getBoundingClientRect().width);
    if (isHidden()) sidebar.classList.remove('show');
    return w;
  }

  function addBackdrop() {
    if (document.getElementById('sb-backdrop')) return;
    const el = document.createElement('div');
    el.id = 'sb-backdrop';
    el.addEventListener('click', closeSidebar);
    document.body.appendChild(el);
    document.body.classList.add('no-scroll');
    root.classList.add('mobile-dim');
  }

  function removeBackdrop() {
    const el = document.getElementById('sb-backdrop');
    if (el) el.remove();
    document.body.classList.remove('no-scroll');
    root.classList.remove('mobile-dim');
  }

  function openSidebar() {
    const w = sidebarWidth();
    sidebar.classList.add('show');
    root.style.setProperty('--sbw', Math.max(0, w - OFFSET_PX) + 'px');
    menuBtn.setAttribute('aria-expanded', 'true');
    if (isMobile()) addBackdrop();
    if (appHeader) {
      appHeader.classList.remove('w-[400px]');
      appHeader.classList.add('max-w-lg');
    }
    localStorage.setItem('sidebarState', 'open'); // ✅ remember
  }

  function closeSidebar() {
    const w = sidebarWidth();
    sidebar.classList.remove('show');
    root.style.setProperty('--sbw', '0px');
    menuBtn.setAttribute('aria-expanded', 'false');
    if (isMobile()) removeBackdrop();
    if (appHeader) {
      appHeader.classList.remove('max-w-lg');
      appHeader.classList.add('w-[400px]');
    }
    localStorage.setItem('sidebarState', 'closed'); // ✅ remember
  }

  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    isHidden() ? openSidebar() : closeSidebar();
  });

  // ✅ Restore state from history
  const savedState = localStorage.getItem('sidebarState');
  if (savedState === 'open') {
    openSidebar();
  } else if (savedState === 'closed') {
    closeSidebar();
  } else {
    // default if nothing saved yet
    closeSidebar(); // start closed (you can change to openSidebar() for desktop default)
  }

  // ✅ Handle redirect after insert/edit/delete
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("action") === "done") {
    if (isMobile()) {
      closeSidebar(); // auto-close mobile
    }
    // desktop stays as last saved
  }
}

  // -------- tabs --------
  const homeTab    = document.getElementById("home");
  const contactTab = document.getElementById("contact");
  const eventsTab  = document.getElementById("events");
  const insightTab = document.getElementById("insight");
  const manageTab  = document.getElementById("manageTab");

  function show(el) { if (el) el.style.display = "block"; }
  function hide(el) { if (el) el.style.display = "none"; }

  function resetScroll() {
    window.scrollTo({ top: 0, behavior: "smooth" });
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    [document.querySelector('#main'), document.querySelector('.main'),
     document.querySelector('.content'), document.getElementById("account"),
     homeRight, contactRight, eventRight].filter(Boolean)
     .forEach(el => el.scrollTop = 0);
  }

  if (homeTab) homeTab.addEventListener('click', (e) => {
    e.preventDefault?.();
    show(homeRight); hide(contactRight); hide(eventRight); hide(insightRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (contactTab) contactTab.addEventListener('click', (e) => {
    e.preventDefault?.();
    show(contactRight); hide(homeRight); hide(eventRight); hide(insightRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (eventsTab) eventsTab.addEventListener("click", (e) => {
    e.preventDefault?.();
    show(eventRight); hide(homeRight); hide(contactRight); hide(insightRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (insightTab) insightTab.addEventListener("click", (e) => {
    e.preventDefault?.();
    show(insightRight); hide(homeRight); hide(eventRight); hide(contactRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (manageTab) manageTab.addEventListener("click", (e) => {
    show(document.getElementById("account")); hide(homeRight); hide(eventRight); hide(contactRight); hide(insightRight);
    requestAnimationFrame(resetScroll);
  })

  // -------- modals --------
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

  // -------- page-level delegation --------
  document.body.addEventListener('click', e => {
    const pg = e.target.closest('.pagination a');
    if (pg) {
      e.preventDefault();
      const url = new URL(pg.href, window.location.origin);
      const p   = parseInt(url.searchParams.get('page')) || 1;
      if (pg.closest('.strategy-section')) {
        loadStrategy(currentSalesId, p);
      } else if(pg.closest('.grocery')){ 
        loadGroceriesTable(currentId, p);
      } else if(pg.closest('.football')){
        loadFootball(currentFootballId, p);
      } else {
        loadTable(currentId, p);
      }
      return;
    }
    const addBtn = e.target.closest('#addIcon');
    if (addBtn) {
      e.preventDefault();
      document.getElementById('addForm')?.classList.remove('hidden');
    }
    const closeAdd = e.target.closest('[data-close-add]');
    if (closeAdd) document.getElementById('addForm')?.classList.add('hidden');
  });

  // Enter submits form (leave everything else as-is)
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const form = e.target.closest('form');
    if (!form) return;
    if (e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return; // optional
    e.preventDefault();
    form.requestSubmit(); // or form.submit() if you don't want validation
  });

  document.addEventListener('change', (e) => {
    const s = e.target.closest('select[data-autosave="1"]');
    if (s) s.form?.requestSubmit();
  });


  // -------- autosave status --------
  document.addEventListener('change', (e) => {
    if (e.target.matches('select.status--autosave') &&
        ['To Do', 'In Progress', 'Done'].includes(e.target.value)) {
      e.target.form?.submit();
    }
  });
  
  // -------- dropdown open/close --------
$(function () {
  const $arrowBtn = $('#tablesItem');
  const $dd       = $('#dropdown');
  const $chev     = $('#tablesItem .chev');
  const KEY       = 'tablesDropdownState:v1';

  function open(skipAnim = false) {
    if (skipAnim) {
      $dd.stop(true,true).show().removeClass('hidden');
    } else {
      if ($dd.is(':visible')) return;
      $dd.stop(true,true).slideDown(160, () => $dd.removeClass('hidden'));
    }
    $chev.addClass('rotate-90');
    $arrowBtn.attr('aria-expanded','true');
    localStorage.setItem(KEY, 'open');
  }

  function close(skipAnim = false) {
    if (skipAnim) {
      $dd.stop(true,true).hide().addClass('hidden');
    } else {
      if (!$dd.is(':visible')) return;
      $dd.stop(true,true).slideUp(160, () => $dd.addClass('hidden'));
    }
    $chev.removeClass('rotate-90');
    $arrowBtn.attr('aria-expanded','false');
    localStorage.setItem(KEY, 'closed');
  }

  $arrowBtn.on('click', e => {
    e.preventDefault(); e.stopPropagation();
    $dd.is(':visible') ? close() : open();
  });

  $(document).on('click', e => {
    if (!$(e.target).closest('#dropdown,#tablesItem').length) close();
  });

  $(document).on('keydown', e => { if (e.key === 'Escape') close(); });

  // Restore last state without animation
  const saved = localStorage.getItem(KEY);
  if (saved === 'open') open(true);
  else close(true); // default closed
});


  // -------- autoload --------
  const params = new URLSearchParams(window.location.search);
  const shouldAutoload = params.get("autoload");
  const tableIdFromUrl = parseInt(params.get("table_id")) || null;
  const tableType      = params.get("type");

  if (shouldAutoload && tableIdFromUrl) {
    if (tableType === "sales") {
      loadStrategy(tableIdFromUrl, currentPage);
    } else if (tableType === "groceries") {
      loadGroceriesTable(tableIdFromUrl, currentPage); 
    }else if(tableType === "football"){
      loadFootball(tableIdFromUrl, currentPage);
    } else {
      loadTable(tableIdFromUrl, currentPage); // universal
    }
  }

})();

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

(function($){
  if (!window.jQuery) return;

  function cellText($cell){
    const $ctrl = $cell.find('input,textarea,select');
    return $ctrl.length ? String($ctrl.val() ?? '') : String($cell.text() ?? '');
  }
  function highlightCell($cell, q){
    const $ctrl = $cell.find('input,textarea,select');
    const t = cellText($cell).toLowerCase();
    const hit = q && t.includes(q.toLowerCase());
    $cell.removeClass('cell-hit'); $ctrl.removeClass('ctrl-hit');
    if (!q) return;
    if (hit) ($ctrl.length ? $ctrl.addClass('ctrl-hit') : $cell.addClass('cell-hit'));
  }

  function runFilter($input){
    const rowsSel  = $input.data('rows');
    const countSel = $input.data('count');
    const scopeSel = $input.data('scope');
    const $scope   = scopeSel ? $(scopeSel) : $(document);
    const $rows    = $scope.find(rowsSel);

    if (!$rows.length) return; // nothing to do for this input yet

    const q = String($input.val() ?? '').trim();
    let visible = 0;

    $rows.each(function(){
      const $r = $(this);
      const $cells = $r.find('[data-col]');
      const hay = $cells.map((_,c)=>cellText($(c))).get().join(' ').toLowerCase();
      const match = q ? hay.includes(q.toLowerCase()) : true;

      $r.toggleClass('hidden', !match);
      if (match) visible++;

      // only highlight visible matches
      $cells.each(function(){ highlightCell($(this), match ? q : ''); });

    });

    if (countSel) $(countSel).text(visible ? `${visible} match${visible===1?'':'es'}` : 'No matches');
  }

  // One handler for all search inputs that declare data-rows
  $(document).on('input', '[data-rows]', function(){
    runFilter($(this));
  });

  // Initial render after DOM ready
  $(function(){
    $('[data-rows]').each(function(){ runFilter($(this)); });
  });

  // If rows/sections are injected later (AJAX/tabs), re-run once:
  // $('[data-rows]').each(function(){ runFilter($(this)); });
})(jQuery);

(function () {
  // parse numbers from "$20", "20$", "20.50", "20,50", etc.
  function toNumber(v){
    const s = String(v || '').replace(/\s/g,'');
    const m = s.match(/-?\d+(?:[.,]\d+)?/);
    return m ? parseFloat(m[0].replace(',', '.')) : 0;
  }
  // pick currency + whether it’s prefix ($20) or suffix (20$). Default: "$" suffix.
  function currencyInfo(sample){
    const s = String(sample || '').trim();
    const lead = s.match(/^([$€£])/);
    const tail = s.match(/([$€£])$/);
    if (lead) return { sym: lead[1], pos: 'prefix' };
    if (tail) return { sym: tail[1], pos: 'suffix' };
    return { sym: '$', pos: 'suffix' };
  }
  function fmt(n, a='', b=''){
    const r = Math.round(n * 100) / 100;
    const str = Number.isInteger(r) ? r.toFixed(0) : r.toFixed(2);
    const {sym,pos} = currencyInfo(a) || currencyInfo(b);
    return pos === 'prefix' ? (sym + str) : (str.replace(/\.00$/,'') + sym);
  }

  function wireAddModal(form){
    const pri = form.querySelector('#priorityAdd') || form.querySelector('input[name="priority"]');
    const own = form.querySelector('#ownerAdd')    || form.querySelector('input[name="owner"]');
    const fit = form.querySelector('#deadlineAdd'); // hidden input
    if (!pri || !own || !fit) return;

    const recalc = () => {
      const p = toNumber(pri.value);
      const o = toNumber(own.value);
      fit.value = (p || o) ? fmt(p - o, pri.value, own.value) : '';
    };
    ['input','change'].forEach(ev => {
      pri.addEventListener(ev, recalc);
      own.addEventListener(ev, recalc);
    });
    form.addEventListener('submit', recalc);
    recalc();
  }

  function wireRow(row){
    const pri = row.querySelector('input[name="priority"]');
    const own = row.querySelector('input[name="owner"]');
    const fit = row.querySelector('input[name="deadline"]'); // read-only
    if (!pri || !own || !fit) return;

    const recalc = () => {
      const p = toNumber(pri.value);
      const o = toNumber(own.value);
      fit.value = (p || o) ? fmt(p - o, pri.value, own.value) : '';
    };
    ['input','change'].forEach(ev => {
      pri.addEventListener(ev, recalc);
      own.addEventListener(ev, recalc);
    });
    // ensure correct on initial render
    recalc();
  }

  function wire(root){
    const addForm = root.querySelector('#addSalesForm');
    if (addForm) wireAddModal(addForm);
    root.querySelectorAll('.sales-row').forEach(wireRow);
  }

  // Run now
  wire(document);

  // Expose for AJAX-injected content:
  window.initProfitCalc = function(root=document){ wire(root); };

  // If you already use initSalesEnhancements(root), call us from there:
  if (typeof window.initSalesEnhancements === 'function') {
    const oldEnh = window.initSalesEnhancements;
    window.initSalesEnhancements = function(root=document){
      oldEnh(root);
      wire(root);
    };
  }
})();
</script>

</body>
</html>
