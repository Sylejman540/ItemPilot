<?php
/* ──────────────  SESSION + DB  ────────────── */
session_start();
require_once __DIR__ . '/db.php';
require_once "register/register.php";
$uid = $_SESSION['user_id'] ?? 0;

/* ──────────────  RECORDS QUERY  ────────────── */
$tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : null;

$sql = "SELECT id,name,notes,assignee,status,attachment_summary FROM universal WHERE user_id = ?";
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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <style>
    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="overflow-x-hidden">
   
  <?php require_once __DIR__ . '/components/header.php'; ?>

  <?php require_once __DIR__ . '/components/dashboard.php'; ?>

  <?php require_once __DIR__ . '/components/table.php'; ?>

  <?php require_once __DIR__ . '/components/contact.php'; ?>

  <?php require_once __DIR__ . '/components/categories.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function() {
  const eventRight   = document.getElementById("event-right");
  const homeRight    = document.getElementById("home-right");
  const contactRight = document.getElementById("contact-right");
  const blank        = document.getElementById("blank");        // "Create New" button
  const universal    = document.getElementById("universal");    // optional "Open current table" button
  const dropdown     = document.getElementById("dropdown");     // the tables list (UL)

  let currentPage = parseInt(new URLSearchParams(window.location.search).get("page")) || 1;
  let currentId   = parseInt(new URLSearchParams(window.location.search).get("table_id")) || null;

  // -------- core loaders --------
  function loadTable(tableId, page = 1) {
    if (!tableId) return; // nothing to load yet
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

  // -------- actions: create/open tables --------
  if (blank && eventRight) {
    blank.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      newTable(1);
    });
  }

  // If you keep a single "universal" button, let it open its data-table-id or the currentId
  if (universal && eventRight) {
    universal.addEventListener("click", e => {
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const idFromBtn = parseInt(universal.dataset.tableId || "", 10);
      loadTable(!isNaN(idFromBtn) ? idFromBtn : currentId, currentPage || 1);
    });
  }

  // Delegate all clicks inside the dropdown list to items with .js-table-link
  if (dropdown && eventRight) {
    dropdown.addEventListener("click", e => {
      const link = e.target.closest(".js-table-link");
      if (!link) return;
      e.preventDefault();
      document.getElementById("categories")?.classList.add("hidden");
      const tableId = parseInt(link.dataset.tableId || "", 10);
      if (!isNaN(tableId)) loadTable(tableId, 1);
    });
  }

  // -------- template picker (unchanged) --------
  document.querySelectorAll('.template-item').forEach(el => {
    el.addEventListener('click', () => {
      const id   = el.dataset.id;
      const name = el.dataset.name;
      const sel  = document.getElementById('selectedTemplate');
      if (sel) sel.textContent = name;
      window.location.href = `home.php?table_id=${id}&page=${currentPage}`;
    });
  });

  // -------- sidebar menu toggle (unchanged) --------
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

  // -------- tabs (unchanged) --------
  const homeTab    = document.getElementById("home");
  const contactTab = document.getElementById("contact");
  const eventsTab  = document.getElementById("events");

  if (homeTab)    homeTab.addEventListener('click', () => { homeRight.style.display = "block"; contactRight.style.display = "none"; eventRight.style.display = "none"; });
  if (contactTab) contactTab.addEventListener('click', () => { homeRight.style.display = "none"; contactRight.style.display = "block"; eventRight.style.display = "none"; });
  if (eventsTab)  eventsTab.addEventListener('click', () => { homeRight.style.display = "none"; contactRight.style.display = "none"; eventRight.style.display = "block"; });

  // -------- modals (unchanged) --------
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

  // -------- page-level delegation (pagination, forms) --------
  document.body.addEventListener('click', e => {
    // Pagination links inside loaded table
    const pg = e.target.closest('.pagination a');
    if (pg) {
      e.preventDefault();
      const url = new URL(pg.href, window.location.origin);
      const p   = parseInt(url.searchParams.get('page')) || 1;
      loadTable(currentId, p);   // <-- IMPORTANT: keep currentId
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

  // -------- amCharts (unchanged) --------
  am5.ready(function() {
    var root = am5.Root.new("dealsChart");
    root.setThemes([am5themes_Animated.new(root)]);

    var chart = root.container.children.push(
      am5xy.XYChart.new(root, {
        panX: false, panY: false, wheelX: "none", wheelY: "none",
        layout: root.verticalLayout
      })
    );

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

    chart.set("scrollbarX", am5.Scrollbar.new(root, {
      orientation: "horizontal",
      height: 40
    }));

    series.data.setAll(
      <?php echo json_encode(array_map(function($d){
        return ['dt'=>strtotime($d['dt'])*1000,'amt'=>$d['amt']];
      }, $deals)); ?>
    );
  });

  // -------- Enter submits current inline form (unchanged) --------
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const form = e.target.closest('form');
      if (form) form.submit();
    }
  });

  document.addEventListener('change', (e) => {
    if (e.target.matches('select[name="status"]') && e.target.value === 'Done', 'To Do', 'In Progress') {
      e.target.form?.submit();
    }
  });

  // -------- jQuery dropdown open/close (unchanged) --------
  $(function () {
    const $arrowBtn = $('#tablesItem');
    const $dd       = $('#dropdown');
    const $chev     = $('#tablesItem .chev');

    function open() {
      if ($dd.is(':visible')) return;
      $dd.stop(true, true).slideDown(160, () => $dd.removeClass('hidden'));
      $chev.addClass('rotate-180');
      $arrowBtn.attr('aria-expanded', 'true');
    }
    function close() {
      if (!$dd.is(':visible')) return;
      $dd.stop(true, true).slideUp(160, () => $dd.addClass('hidden'));
      $chev.removeClass('rotate-180');
      $arrowBtn.attr('aria-expanded', 'false');
    }

    $arrowBtn.on('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      $dd.is(':visible') ? close() : open();
    });

    $(document).on('click', function (e) {
      if ($(e.target).closest('#dropdown, #tablesItem').length === 0) {
        close();
      }
    });

    $(document).on('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  });

  // -------- autoload if requested --------
  const shouldAutoload = new URLSearchParams(window.location.search).get("autoload");
  if (shouldAutoload) {
    // load whatever table id came via URL (if any)
    if (currentId) loadTable(currentId, currentPage);
  }
})();
</script>

</body>
</html>
