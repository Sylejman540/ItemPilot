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

/* ─────────── INPUTS ─────────── */
$tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : null;

/* ─────────── HELPERS ─────────── */
function fetch_all_assoc(mysqli $conn, string $sql, string $types = '', array $params = []): array {
  $stmt = $conn->prepare($sql);
  if ($types && $params) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
  return $rows;
}
function scalar(mysqli $conn, string $sql, string $types = '', array $params = []): int|float {
  $stmt = $conn->prepare($sql);
  if ($types && $params) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_row();
  $stmt->close();
  return (int)($res[0] ?? 0);
}

/* ─────────── COMMON WHERE for UNION ─────────── */
if ($tableId) {
  $whereUni  = "WHERE user_id = ? AND table_id = ?";
  $whereSales = "WHERE user_id = ? AND table_id = ?";
  $wTypes = "iiii";
  $wArgs  = [$uid, $tableId, $uid, $tableId];
} else {
  $whereUni  = "WHERE user_id = ?";
  $whereSales = "WHERE user_id = ?";
  $wTypes = "ii";
  $wArgs  = [$uid, $uid];
}

/* ─────────── DAILY LINE: records by day (last 90 days) ─────────── */
$rows = fetch_all_assoc(
  $conn,
  "(SELECT DATE(created_at) AS dt, COUNT(*) AS amt
     FROM universal
     $whereUni AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
     GROUP BY DATE(created_at))
   UNION ALL
   (SELECT DATE(created_at) AS dt, COUNT(*) AS amt
     FROM sales_strategy
     $whereSales AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
     GROUP BY DATE(created_at))
   ORDER BY dt ASC",
  $wTypes, $wArgs
);

/* zero-fill in PHP */
$dealsMap = [];
foreach ($rows as $r) {
  $dealsMap[$r['dt']] = ($dealsMap[$r['dt']] ?? 0) + (int)$r['amt'];
}
$start = new DateTime(date('Y-m-d', strtotime('-89 days')));
$end   = new DateTime(date('Y-m-d')); 
$deals = [];
for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
  $key = $d->format('Y-m-d');
  $deals[] = ['dt'=>$key, 'amt'=>($dealsMap[$key] ?? 0)];
}

/* ─────────── DONUT: by status ─────────── */
$statusData = fetch_all_assoc(
  $conn,
  "(SELECT COALESCE(NULLIF(TRIM(status),''),'(none)') AS status, COUNT(*) AS cnt
     FROM universal
     $whereUni
     GROUP BY status)
   UNION ALL
   (SELECT COALESCE(NULLIF(TRIM(status),''),'(none)') AS status, COUNT(*) AS cnt
     FROM sales_strategy
     $whereSales
     GROUP BY status)",
  $wTypes, $wArgs
);

/* ─────────── BAR: by assignee (top 5, only in universal) ─────────── */
$assigneeData = fetch_all_assoc(
  $conn,
  "SELECT assignee, SUM(cnt) AS cnt
   FROM (
     SELECT COALESCE(NULLIF(TRIM(assignee),''),'(unassigned)') AS assignee, COUNT(*) AS cnt
     FROM universal
     $whereUni
     GROUP BY assignee

     UNION ALL

     SELECT '(unassigned)' AS assignee, COUNT(*) AS cnt
     FROM sales_strategy
     $whereSales
   ) AS combined
   GROUP BY assignee
   ORDER BY cnt DESC
   LIMIT 5",
  $wTypes, $wArgs
);

/* ─────────── KPIs ─────────── */
$totalRecords = scalar(
  $conn,
  "(SELECT COUNT(*) AS c FROM universal $whereUni)
   UNION ALL
   (SELECT COUNT(*) AS c FROM sales_strategy $whereSales)",
  $wTypes, $wArgs
);

$completed = scalar(
  $conn,
  "(SELECT COUNT(*) AS c FROM universal $whereUni AND status = 'completed')
   UNION ALL
   (SELECT COUNT(*) AS c FROM sales_strategy $whereSales AND status = 'completed')",
  $wTypes, $wArgs
);

$newLast7 = scalar(
  $conn,
  "(SELECT COUNT(*) AS c FROM universal $whereUni AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
   UNION ALL
   (SELECT COUNT(*) AS c FROM sales_strategy $whereSales AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))",
  $wTypes, $wArgs
);

$successPct = $totalRecords ? round($completed / $totalRecords * 100, 1) : 0;

$kpis = [
  ['metric'=>'All Records','value'=>$totalRecords,'dateRange'=>'Scoped to you'.($tableId?' · table '.$tableId:''),'color'=>'text-blue-600'],
  ['metric'=>'Completed','value'=>$completed,'dateRange'=>'Since launch','color'=>'text-emerald-500'],
  ['metric'=>'Impact','value'=>$successPct,'dateRange'=>'Completion rate','color'=>'text-amber-500','isPct'=>true],
];

/* ─────────── PROGRESS (status mix) ─────────── */
$counts = [];
foreach ($statusData as $s) {
  $counts[$s['status']] = ($counts[$s['status']] ?? 0) + (int)$s['cnt'];
}
$den = max(1, $totalRecords);
$pick = function($label) use ($counts) { return $counts[$label] ?? 0; };

$hasNamed = isset($counts['Published']) || isset($counts['published'])
         || isset($counts['To Do'])    || isset($counts['todo'])
         || isset($counts['In Progress']) || isset($counts['in_progress'])
         || isset($counts['Done'])     || isset($counts['done']) || isset($counts['completed']);

if ($hasNamed) {
  $published = $pick('Published') + $pick('published');
  $todo      = $pick('To Do') + $pick('todo');
  $inprog    = $pick('In Progress') + $pick('in_progress');
  $done      = $pick('Done') + $pick('done') + $pick('completed');

  $progress = [
    ['metric'=>'Published Project','value'=>$published,'delta'=>0.0,'pct'=>round($published/$den*100),'bar'=>'bg-rose-500'],
    ['metric'=>'To Do','value'=>$todo,'delta'=>0.0,'pct'=>round($todo/$den*100),'bar'=>'bg-blue-500'],
    ['metric'=>'In Progress','value'=>$inprog,'delta'=>0.0,'pct'=>round($inprog/$den*100),'bar'=>'bg-emerald-500','isPct'=>true],
    ['metric'=>'Done','value'=>$done,'delta'=>0.0,'pct'=>round($done/$den*100),'bar'=>'bg-amber-500'],
  ];
} else {
  $progress = [];
  $palette  = ['bg-blue-500','bg-emerald-500','bg-amber-500','bg-rose-500'];
  foreach (array_slice($statusData, 0, 4) as $i => $s) {
    $pct = round(($s['cnt'] / $den) * 100);
    $progress[] = [
      'metric' => $s['status'],
      'value'  => (int)$s['cnt'],
      'delta'  => 0.0,
      'pct'    => $pct,
      'bar'    => $palette[$i % count($palette)],
    ];
  }
}
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
          <button id="menuBtn" class="top-1 left-2 z-50 text-gray-500 hover:text-blue-500 cursor-pointer">
            <!-- Hamburger (☰) -->
             <svg id="hamburgerIcon" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <rect x="3" y="6"  width="18" height="2.5" rx="1.25" />
              <rect x="3" y="11" width="14" height="2.5" rx="1.25" />
              <rect x="3" y="16" width="10" height="2.5" rx="1.25" />
            </svg>
          </button>

          <button aria-label="Search" class="text-gray-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
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
     class="absolute top-20 left-1/2 transform -translate-x-1/2
            px-6 py-3 rounded-lg shadow-lg text-sm font-medium
            transition-opacity duration-500
            <?php if (empty($_SESSION['flash'])): ?> hidden <?php endif; ?>
            <?php if (!empty($_SESSION['flash']) && str_starts_with($_SESSION['flash'], '✅')): ?>
              text-green-800 bg-green-100 border border-green-300
            <?php else: ?>
              text-red-800 bg-red-100 border border-red-300
            <?php endif; ?>">
  <?php if (!empty($_SESSION['flash'])): ?>
    <?= htmlspecialchars($_SESSION['flash']) ?>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
</div>


  
<div id="account" class="hidden inset-0 md:fixed absolute mt-14 md:ml-150 h-screen overflow-none md:ml-0 md:mr-0 ml-3 mr-3">
  <div class="w-full max-w-md bg-white px-8 py-5 rounded-2xl shadow-md border border-gray-200">
  <h1 class="text-3xl font-extrabold text-center text-[#263544] mb-4 mt-20">Manage Your Account</h1>

    <form action="/ItemPilot/account/manage-account.php" method="POST" class="space-y-6">
      <!-- Name -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Name</label>
        <input type="text" name="name" required
               class="w-full border border-gray-300 p-3 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
               value="<?= htmlspecialchars($user['name'] ?? '') ?>">
      </div>

      <!-- Password change (verify old first) -->
      <fieldset class="space-y-4">
        <legend class="text-sm font-semibold text-gray-700">Change Password (optional)</legend>

        <div>
          <label class="block text-sm text-gray-700 mb-1">Current Password</label>
          <input type="password" name="current_password"
                 class="w-full border border-gray-300 p-3 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Enter your current password">
        </div>

        <div>
          <label class="block text-sm text-gray-700 mb-1">New Password</label>
          <input type="password" name="new_password"
                 class="w-full border border-gray-300 p-3 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="At least 8 characters">
        </div>

        <div>
          <label class="block text-sm text-gray-700 mb-1">Confirm New Password</label>
          <input type="password" name="new_password_confirm"
                 class="w-full border border-gray-300 p-3 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Re-enter new password">
        </div>
      </fieldset>

      <!-- Actions -->
      <div class="flex justify-between items-center">
        <button type="submit"
                  class="bg-[#263544] cursor-pointer hover:bg-slate-800 text-white px-6 py-3 rounded-full font-semibold shadow-md transition">
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

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function() {
  const eventRight   = document.getElementById("event-right");
  const homeRight    = document.getElementById("home-right");
  const contactRight = document.getElementById("contact-right");
  const blank        = document.getElementById("blank");        // "Create New" button
  const universal    = document.getElementById("universal");    // optional "Open current table" button
  const dropdown     = document.getElementById("dropdown");     // the tables list (UL)
  const sales        = document.getElementById("sales-strategy"); // sales template card
  const strategy     = document.getElementById("strategy");       // optional open strategy button

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
        eventRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        eventRight.style.display = "block";
        currentPage = page;
      });
  }

  function newTable(page = 1) {
    fetch(`categories/Universal%20Table/insert_universal.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        eventRight.innerHTML = html;
        if (homeRight)    homeRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        eventRight.style.display = "block";
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
    fetch(`categories/Sales%20Strategy/insert_sales.php?page=${page}&table_id=${salesId}`)
      .then(r => r.text())
      .then(html => {
        eventRight.innerHTML = html;
        if (homeRight) homeRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        eventRight.style.display = "block";
        currentPage = page;
      });
  }

  function newStrategy(page = 1) {
    fetch(`categories/Sales%20Strategy/insert_sales.php?action=create_blank&page=${page}`)
      .then(r => r.text())
      .then(html => {
        eventRight.innerHTML = html;
        if (homeRight) homeRight.style.display = "none";
        if (contactRight) contactRight.style.display = "none";
        eventRight.style.display = "block";
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

  // -------- Dropdown delegation (detect src) --------
  if (dropdown && eventRight) {
    dropdown.addEventListener("click", e => {
      const link = e.target.closest(".js-table-link, .js-strategy-link");
      if (!link) return;
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");

      const tableId = parseInt(link.dataset.tableId || "", 10);
      const src     = link.dataset.src;

      if (!isNaN(tableId)) {
        if (src === "sales_table") {
          loadStrategy(tableId, 1);
        } else {
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
  const OFFSET_PX = 1;
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
  }

  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    isHidden() ? openSidebar() : closeSidebar();
  });

  // Prevent flicker on load / AJAX
  closeSidebar();
  }


  // -------- tabs --------
  const homeTab    = document.getElementById("home");
  const contactTab = document.getElementById("contact");
  const eventsTab  = document.getElementById("events");
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
    show(homeRight); hide(contactRight); hide(eventRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (contactTab) contactTab.addEventListener('click', (e) => {
    e.preventDefault?.();
    show(contactRight); hide(homeRight); hide(eventRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (eventsTab) eventsTab.addEventListener("click", (e) => {
    e.preventDefault?.();
    show(eventRight); hide(homeRight); hide(contactRight); hide(document.getElementById("account"));
    requestAnimationFrame(resetScroll);
  });

  if (manageTab) manageTab.addEventListener("click", (e) => {
    e.preventDefault?.();
    show(document.getElementById("account")); hide(eventRight); hide(homeRight); hide(contactRight);
    requestAnimationFrame(resetScroll);
  });

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

  // -------- Enter submits form --------
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const form = e.target.closest('form');
      if (form) form.submit();
    }
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
    function open() {
      if ($dd.is(':visible')) return;
      $dd.stop(true,true).slideDown(160,()=> $dd.removeClass('hidden'));
      $chev.addClass('rotate-90');
      $arrowBtn.attr('aria-expanded','true');
    }
    function close() {
      if (!$dd.is(':visible')) return;
      $dd.stop(true,true).slideUp(160,()=> $dd.addClass('hidden'));
      $chev.removeClass('rotate-90');
      $arrowBtn.attr('aria-expanded','false');
    }
    $arrowBtn.on('click', e=>{ e.preventDefault(); e.stopPropagation(); $dd.is(':visible')?close():open(); });
    $(document).on('click', e=>{ if(!$(e.target).closest('#dropdown,#tablesItem').length) close(); });
    $(document).on('keydown', e=>{ if(e.key==='Escape') close(); });
  });

  // -------- autoload --------
  const params = new URLSearchParams(window.location.search);
  const shouldAutoload = params.get("autoload");
  const tableIdFromUrl = parseInt(params.get("table_id")) || null;
  const tableType      = params.get("type");

  if (shouldAutoload && tableIdFromUrl) {
    if (tableType === "sales") {
      loadStrategy(tableIdFromUrl, currentPage);
    } else {
      loadTable(tableIdFromUrl, currentPage);
    }
  }
})();

(() => {
  // Helper: safely render a chart if the target exists
  function renderChart(selector, options) {
    const el = document.querySelector(selector);
    if (!el) return; // silently skip if the container isn't on the page
    try {
      new ApexCharts(el, options).render();
    } catch (err) {
      console.error(`ApexCharts render failed for ${selector}:`, err);
    }
  }

  // ---- Deals (area) ----
  const dealsSeries = [{
    name: 'Deals',
    data: <?= json_encode(
      array_map(fn($r) => [(new DateTime($r['dt']))->format('Y-m-d'), (int)$r['amt']], $deals),
      JSON_UNESCAPED_SLASHES
    ) ?>.map(([d, v]) => ({ x: d, y: v }))
  }];

  renderChart('#dealsChart', {
    chart:  { type: 'area', height: 330, toolbar: { show: false } },
    series: dealsSeries,
    xaxis:  { type: 'datetime' },
    stroke: { curve: 'smooth', width: 2 },
    colors: ['#3b82f6']
  });

  // ---- Status (donut) ----
  renderChart('#statusChart', {
    chart:  { type: 'donut', height: 330 },
    series: <?= json_encode(array_column($statusData, 'cnt')) ?>,
    labels: <?= json_encode(array_column($statusData, 'status')) ?>,
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6b7280']
  });

  // ---- Assignee (bar) ----
  renderChart('#assigneeChart', {
    chart:  { type: 'bar', height: 330 },
    series: [{ name: 'Records', data: <?= json_encode(array_column($assigneeData, 'cnt')) ?> }],
    xaxis:  { categories: <?= json_encode(array_column($assigneeData, 'assignee')) ?> },
    plotOptions: { bar: { distributed: true } },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6366f1']
  });

  // ---- Completion (radial) ----
  renderChart('#completionChart', {
    chart:  { type: 'radialBar', height: 330 },
    series: [<?= $successPct ?>],
    labels: ['Completion %'],
    colors: ['#10b981'],
    plotOptions: { radialBar: { hollow: { size: '60%' } } }
  });
})();
</script>


</body>
</html>
