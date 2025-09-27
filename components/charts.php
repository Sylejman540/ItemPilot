<?php
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
  $whereUni         = "WHERE u.user_id = ? AND u.table_id = ?";
  $whereSales       = "WHERE s.user_id = ? AND s.table_id = ?";
  $whereGroceries   = "WHERE g.user_id = ? AND g.table_id = ?";
  $whereFootball    = "WHERE f.user_id = ? AND f.table_id = ?";
  $whereApplicants  = "WHERE a.user_id = ? AND a.table_id = ?";
} else {
  $whereUni         = "WHERE u.user_id = ?";
  $whereSales       = "WHERE s.user_id = ?";
  $whereGroceries   = "WHERE g.user_id = ?";
  $whereFootball    = "WHERE f.user_id = ?";
  $whereApplicants  = "WHERE a.user_id = ?";
}

/* ─────────── HEADER CARD METRICS ─────────── */

/* Total tables (tables + dresses_table + groceries_table + football_table + applicants_table) */
$totalTables = fetch_all_assoc(
  $conn,
  "SELECT SUM(cnt) as total FROM (
      SELECT COUNT(*) as cnt FROM tables t             WHERE t.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM dresses_table st     WHERE st.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM groceries_table gt   WHERE gt.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM football_table ft    WHERE ft.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM applicants_table atb WHERE atb.user_id=?
   ) as combined",
  "iiiii", [$uid, $uid, $uid, $uid, $uid]
);
$totalTables = $totalTables[0]['total'] ?? 0;

/* Total records (universal + dresses + groceries + football + applicants) */
$totalRecords = fetch_all_assoc(
  $conn,
  "SELECT SUM(cnt) as total FROM (
      SELECT COUNT(*) as cnt FROM universal u   WHERE u.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM dresses s     WHERE s.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM groceries g   WHERE g.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM football f    WHERE f.user_id=?
      UNION ALL
      SELECT COUNT(*) as cnt FROM applicants a  WHERE a.user_id=?
   ) as combined",
  "iiiii", [$uid, $uid, $uid, $uid, $uid]
);
$totalRecords = $totalRecords[0]['total'] ?? 0;

/* Active this month (new tables created this month; include football_table + applicants_table) */
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
      UNION ALL
      SELECT COUNT(*) as cnt FROM football_table ft
        WHERE ft.user_id=? AND DATE_FORMAT(ft.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
      UNION ALL
      SELECT COUNT(*) as cnt FROM applicants_table atb
        WHERE atb.user_id=? AND DATE_FORMAT(atb.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
   ) as combined",
  "iiiii", [$uid, $uid, $uid, $uid, $uid]
);
$activeThisMonth = $activeThisMonth[0]['total'] ?? 0;

/* Completed tasks — ONLY universal + dresses (football/applicants have no status) */
$completedTasks = fetch_all_assoc(
  $conn,
  "SELECT SUM(cnt) as total FROM (
      SELECT COUNT(*) as cnt FROM universal u WHERE u.user_id=? AND u.status='Done'
      UNION ALL
      SELECT COUNT(*) as cnt FROM dresses s   WHERE s.user_id=? AND s.status='Done'
   ) as combined",
  "ii", [$uid, $uid]
);
$completedTasks = $completedTasks[0]['total'] ?? 0;

/* ─────────── CHART QUERIES ─────────── */

/* 1) Area → New Tables Created (tables + dresses_table + groceries_table + football_table + applicants_table) */
if ($tableId) {
  // 5 tables × (user_id, table_id)
  $typesArea   = "iiiiiiiiii";
  $argsArea    = [$uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId];
  $tableFilter = "AND %s.table_id=?";
} else {
  $typesArea   = "iiiii"; // 5 user_ids
  $argsArea    = [$uid, $uid, $uid, $uid, $uid];
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
       UNION ALL
       SELECT DATE(ft.created_at) AS dt, COUNT(*) AS cnt
         FROM football_table ft
         WHERE ft.user_id=? " . sprintf($tableFilter, 'ft') . "
         GROUP BY DATE(ft.created_at)
       UNION ALL
       SELECT DATE(atb.created_at) AS dt, COUNT(*) AS cnt
         FROM applicants_table atb
         WHERE atb.user_id=? " . sprintf($tableFilter, 'atb') . "
         GROUP BY DATE(atb.created_at)
     ) AS combined
   GROUP BY dt
   ORDER BY dt ASC",
  $typesArea, $argsArea
);

/* 2) Polar → Records Per Table (universal + dresses + groceries + football + applicants) */
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
     GROUP BY gt.table_title)
   UNION ALL
   (SELECT ftb.table_title AS table_name, COUNT(f.id) AS cnt
     FROM football f
     JOIN football_table ftb ON ftb.table_id = f.table_id
     WHERE f.user_id=? " . ($tableId ? "AND f.table_id=?" : "") . "
     GROUP BY ftb.table_title)
   UNION ALL
   (SELECT atb.table_title AS table_name, COUNT(a.id) AS cnt
     FROM applicants a
     JOIN applicants_table atb ON atb.table_id = a.table_id
     WHERE a.user_id=? " . ($tableId ? "AND a.table_id=?" : "") . "
     GROUP BY atb.table_title)";
if ($tableId) {
  $polarData = fetch_all_assoc(
    $conn, $polarSql,
    "iiiiiiiiii",
    [$uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId]
  );
} else {
  $polarData = fetch_all_assoc($conn, $polarSql, "iiiii", [$uid, $uid, $uid, $uid, $uid]);
}

/* 3) Bar → Tables Per Month (tables + dresses_table + groceries_table + football_table + applicants_table) */
$barSql =
  "SELECT mth, SUM(cnt) AS cnt
     FROM (
       SELECT DATE_FORMAT(t.created_at,  '%Y-%m') AS mth, COUNT(*) AS cnt
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
       UNION ALL
       SELECT DATE_FORMAT(ft.created_at, '%Y-%m') AS mth, COUNT(*) AS cnt
         FROM football_table ft
         WHERE ft.user_id=? " . ($tableId ? "AND ft.table_id=?" : "") . "
         GROUP BY mth
       UNION ALL
       SELECT DATE_FORMAT(atb.created_at, '%Y-%m') AS mth, COUNT(*) AS cnt
         FROM applicants_table atb
         WHERE atb.user_id=? " . ($tableId ? "AND atb.table_id=?" : "") . "
         GROUP BY mth
     ) AS combined
   GROUP BY mth
   ORDER BY mth ASC";

if ($tableId) {
  $barData = fetch_all_assoc(
    $conn, $barSql,
    "iiiiiiiiii",
    [$uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId]
  );
} else {
  $barData = fetch_all_assoc(
    $conn, $barSql,
    "iiiii",
    [$uid, $uid, $uid, $uid, $uid]
  );
}

// ✅ Build months array regardless of $tableId
$year = !empty($barData) 
  ? substr(max(array_column($barData, 'mth')), 0, 4) 
  : date('Y');

$months = [];
for ($i = 1; $i <= 12; $i++) {
    $key = sprintf('%s-%02d', $year, $i);
    $months[$key] = 0;
}

foreach ($barData as $row) {
    $months[$row['mth']] = (int)$row['cnt'];
}

$categories = array_keys($months);
$values     = array_values($months);


/* 4) Radar → Status Distribution (ONLY universal + dresses; football/applicants excluded) */
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

/* 5) Line → Records Over Time (universal + dresses + groceries + football + applicants) */
if ($tableId) { 
  $lineTypes = "iiiiiiiiii";
  $lineArgs  = [$uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId, $uid,$tableId]; 
} else { 
  $lineTypes = "iiiii";    
  $lineArgs  = [$uid, $uid, $uid, $uid, $uid]; 
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
       UNION ALL
       SELECT DATE(f.created_at) AS dt, COUNT(*) AS cnt
         FROM football f
         $whereFootball
         GROUP BY dt
       UNION ALL
       SELECT DATE(a.created_at) AS dt, COUNT(*) AS cnt
         FROM applicants a
         $whereApplicants
         GROUP BY dt
     ) AS combined
   GROUP BY dt
   ORDER BY dt ASC",
  $lineTypes, $lineArgs
);

/* 6) Pie → To Do / In Progress / Done (ONLY universal + dresses; football/applicants excluded) */
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
$statuses  = ["To Do", "In Progress", "Done"];
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