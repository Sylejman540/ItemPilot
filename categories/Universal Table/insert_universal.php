<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header("Location: register/login.php"); exit; }

/* ---------- AJAX helpers ---------- */
function is_ajax(): bool {
  return (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
  ) || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
}
function json_out(array $payload, int $code = 200) {
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Location');
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}

/* ---------- Config (UNIVERSAL) ---------- */
$CATEGORY_URL = '/ItemPilot/categories/Universal%20Table';
$UPLOAD_DIR   = __DIR__ . '/uploads/';          // filesystem path
$UPLOAD_URL   = $CATEGORY_URL . '/uploads';     // URL prefix for viewing


/* ---------- Resolve table_id ---------- */
$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($action === 'create_blank') {
  $stmt = $conn->prepare("INSERT INTO tables (user_id, created_at) VALUES (?, NOW())");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $table_id = (int)$stmt->insert_id;
  $stmt->close();
  $_SESSION['current_table_id'] = $table_id;

} elseif ($table_id > 0) {
  $_SESSION['current_table_id'] = $table_id;

} else {
  $table_id = (int)($_SESSION['current_table_id'] ?? 0);

  if ($table_id <= 0) {
    $q = $conn->prepare("SELECT table_id FROM tables WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute(); $q->bind_result($latestId); $q->fetch(); $q->close();
    $table_id = (int)$latestId;
  }
  if ($table_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO tables (user_id, created_at) VALUES (?, NOW())");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$stmt->insert_id;
    $stmt->close();
  }
  $_SESSION['current_table_id'] = $table_id;
}

/* ---------- Create/Update (UNIVERSAL with dynamic fields) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id       = $_POST['id'] ?? '';
  $rec_id   = is_numeric($id) ? (int)$id : 0;
  $table_id = (int)($_POST['table_id'] ?? $table_id);

  // Base columns in `universal`
  $name     = trim($_POST['name'] ?? '');
  $notes    = trim($_POST['notes'] ?? '');
  $assignee = trim($_POST['assignee'] ?? '');
  $status   = trim($_POST['status'] ?? '');

  $attachment_summary = $_POST['existing_attachment'] ?? '';

  // Handle upload (optional)
  if (!empty($_FILES['attachment_summary']) && $_FILES['attachment_summary']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['attachment_summary']['error'] !== UPLOAD_ERR_OK) {
      if (is_ajax()) { json_out(['ok'=>false, 'error'=>'Upload failed (PHP error '.$_FILES['attachment_summary']['error'].')'], 400); }
      $_SESSION['flash_error'] = 'Upload failed.';
      header("Location: /ItemPilot/home.php?autoload=1&type=universal&table_id={$table_id}", true, 303);
      exit;
    }
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
      if (is_ajax()) { json_out(['ok'=>false, 'error'=>'Could not create uploads directory.'], 500); }
      $_SESSION['flash_error'] = 'Could not create uploads directory.';
      header("Location: /ItemPilot/home.php?autoload=1&type=universal&table_id={$table_id}", true, 303);
      exit;
    }
    $tmp   = $_FILES['attachment_summary']['tmp_name'];
    $orig  = basename($_FILES['attachment_summary']['name']);
    $ext   = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $safe  = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $fname = $safe . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($ext ? ".{$ext}" : '');
    $dest  = $UPLOAD_DIR . $fname;
    if (!move_uploaded_file($tmp, $dest)) {
      if (is_ajax()) { json_out(['ok'=>false, 'error'=>'Failed to save uploaded file.'], 500); }
      $_SESSION['flash_error'] = 'Failed to save uploaded file.';
      header("Location: /ItemPilot/home.php?autoload=1&type=universal&table_id={$table_id}", true, 303);
      exit;
    }
    $attachment_summary = $fname;  // store just the filename in DB
  }

  // Dynamic inputs mapping
  $dynIn = $_POST['dyn'] ?? [];
  $mapStmt = $conn->prepare("SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id  DESC");
  $mapStmt->bind_param('ii', $uid, $table_id);
  $mapStmt->execute();
  $mapRes = $mapStmt->get_result();
  while ($m = $mapRes->fetch_assoc()) {
    $key = 'extra_field_' . (int)$m['id'];
    if (array_key_exists($key, $_POST)) {
      $val = $_POST[$key];
      $dynIn[$m['field_name']] = ($val === '') ? null : $val;
    }
  }
  $mapStmt->close();

  // Whitelist dynamic columns in universal_base
  $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universal_base'");
  $validCols = $colRes ? array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME') : [];
  $exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
  $editable  = array_values(array_diff($validCols, $exclude));

  $toSave = [];
  foreach ($dynIn as $k => $v) {
    if (in_array($k, $editable, true)) { $toSave[$k] = ($v === '') ? null : $v; }
  }

  $actionPerformed = ($rec_id <= 0) ? 'create' : 'update';

  if ($rec_id <= 0) {
    // CREATE in `universal`
    $stmt = $conn->prepare("INSERT INTO universal (name, notes, assignee, status, attachment_summary, table_id, user_id) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssii', $name, $notes, $assignee, $status, $attachment_summary, $table_id, $uid);
    $stmt->execute();
    $row_id = (int)$stmt->insert_id;
    $stmt->close();

    // ensure/create universal_base
    if ($toSave) {
      $cols  = array_keys($toSave);
      $place = array_fill(0, count($cols), '?');
      $sql   = "INSERT INTO universal_base (`table_id`,`user_id`,`row_id`,`" . implode("`,`", $cols) . "`) VALUES (?,?,?," . implode(',', $place) . ")";
      $stmt  = $conn->prepare($sql);
      $types = 'iii' . str_repeat('s', count($cols));
      $params = [$table_id, $uid, $row_id];
      foreach ($cols as $c) { $params[] = $toSave[$c]; }
      $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
      call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($params)));
      $stmt->execute();
      $stmt->close();
    } else {
      $insb = $conn->prepare("INSERT INTO universal_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
      $insb->bind_param('iii', $table_id, $uid, $row_id);
      $insb->execute();
      $insb->close();
    }

  } else {
    // UPDATE in `universal`
    $stmt = $conn->prepare("UPDATE universal SET name = ?, notes = ?, assignee = ?, status = ?, attachment_summary = ? WHERE id = ? AND table_id = ? AND user_id = ?");
    $stmt->bind_param('sssssiii', $name, $notes, $assignee, $status, $attachment_summary, $rec_id, $table_id, $uid);
    $stmt->execute();
    $stmt->close();

    // ensure universal_base row exists
    $chk = $conn->prepare("SELECT id FROM universal_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
    $chk->bind_param('iii', $table_id, $uid, $rec_id);
    $chk->execute();
    $base = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$base) {
      $insb = $conn->prepare("INSERT INTO universal_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
      $insb->bind_param('iii', $table_id, $uid, $rec_id);
      $insb->execute();
      $insb->close();
    }

    // UPDATE dynamic columns
    if ($toSave) {
      $setParts = [];
      $vals  = [];
      $types = '';
      foreach ($toSave as $col => $val) {
        if ($val === null) { $setParts[] = "`$col`=NULL"; }
        else { $setParts[] = "`$col`=?"; $vals[] = $val; $types .= 's'; }
      }
      if (in_array('updated_at', $validCols, true)) { $setParts[] = "`updated_at`=NOW()"; }
      if ($setParts) {
        $sql = "UPDATE universal_base SET ".implode(', ', $setParts)." WHERE table_id=? AND user_id=? AND row_id=?";
        $types .= 'iii';
        $vals[]  = $table_id;
        $vals[]  = $uid;
        $vals[]  = $rec_id;
        $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
        $stmt = $conn->prepare($sql);
        call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $byRef($vals)));
        $stmt->execute();
        $stmt->close();
      }
    }

    $row_id = $rec_id;
  }

  /* ---------- AJAX: return rendered row (inline, no partial file) ---------- */
  if (is_ajax()) {
    // Compute totalCols for inline row (same logic as page)
    $fieldsAjax = [];
    $stmtF = $conn->prepare("SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id  DESC");
    $stmtF->bind_param('ii', $uid, $table_id);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    while ($f = $resF->fetch_assoc()) { $fieldsAjax[] = $f; }
    $stmtF->close();

    $validColsAjax = $validCols; // from earlier query
    $dynCount = 0; foreach ($fieldsAjax as $m) { if (in_array($m['field_name'], $validColsAjax, true)) $dynCount++; }
    $fixedCount = 5; $hasAction = true; $totalColsInline = $fixedCount + $dynCount + ($hasAction ? 1 : 0);

    $statusColors = [ 'To Do' => 'bg-red-100 text-red-800', 'In Progress' => 'bg-yellow-100 text-yellow-800', 'Done' => 'bg-green-100 text-green-800' ];
    $colorClass = $statusColors[$status] ?? 'bg-white text-gray-900';

    // Build markup identical to list rows
    ob_start();
    ?>
    <form method="POST"
          action="/ItemPilot/categories/Universal%20Table/edit_tbody.php?id=<?= (int)$row_id ?>"
          enctype="multipart/form-data"
          class="universal-row border-b border-gray-200 hover:bg-gray-50 text-sm"
          style="--cols: <?= (int)$totalColsInline ?>;"
          data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>">

      <input type="hidden" name="id" value="<?= (int)$row_id ?>">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($attachment_summary, ENT_QUOTES) ?>">

      <div class="p-2 text-gray-600" data-col="name">
        <input type="text" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
              class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 text-gray-600" data-col="notes">
        <input type="text" name="notes" value="<?= htmlspecialchars($notes, ENT_QUOTES) ?>"
              class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 text-gray-600" data-col="assignee">
        <input type="text" name="assignee" value="<?= htmlspecialchars($assignee, ENT_QUOTES) ?>"
              class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 text-xs font-semibold" data-col="status" data-rows-for="ut-<?= (int)$table_id ?>">
        <select name="status" style="appearance:none;" class="w-full px-2 py-1 rounded-xl <?= $colorClass ?>">
          <option value="To Do"       <?= $status === 'To Do' ? 'selected' : '' ?>>To Do</option>
          <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="Done"        <?= $status === 'Done' ? 'selected' : '' ?>>Done</option>
        </select>
      </div>

      <div class="p-2 text-gray-600" data-col="attachment">
        <?php if (!empty($attachment_summary)): ?>
          <?php $src = $UPLOAD_URL . '/' . rawurlencode($attachment_summary); ?>
          <img src="<?= htmlspecialchars($src, ENT_QUOTES) ?>" class="thumb" alt="Attachment">
        <?php else: ?>
          <span class="italic text-gray-400 ml-[5px]">📎 None</span>
        <?php endif; ?>
      </div>

      <div class="p-2 text-gray-600" data-col="dyn">
        <?php
          // Fetch dynamic values for this new row (could be empty on create)
          $baseRowAjax = [];
          $stmtX = $conn->prepare("SELECT * FROM universal_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
          $stmtX->bind_param('iii', $table_id, $uid, $row_id);
          $stmtX->execute();
          $baseRowAjax = $stmtX->get_result()->fetch_assoc() ?: [];
          $stmtX->close();
          foreach ($fieldsAjax as $colMeta) {
            $colName = $colMeta['field_name'];
            $val = $baseRowAjax[$colName] ?? '';
            ?>
            <input type="text" name="dyn[<?= htmlspecialchars($colName, ENT_QUOTES) ?>]"
                   value="<?= htmlspecialchars($val, ENT_QUOTES) ?>"
                   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
            <?php
          }
        ?>
      </div>

      <div class="p-2">
        <a href="<?= $CATEGORY_URL ?>/delete.php?id=<?= (int)$row_id ?>&table_id=<?= (int)$table_id ?>" class="icon-btn" aria-label="Delete row">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6m2 4H7l1 12h8l1-12z"/>
          </svg>
        </a>
      </div>
    </form>
    <?php
    $row_html = ob_get_clean();

    json_out([
      'ok'        => true,
      'action'    => $actionPerformed,
      'row_id'    => $row_id,
      'table_id'  => $table_id,
      'row_html'  => $row_html
    ]);
  }

  // Non-AJAX fallback
  header("Location: /ItemPilot/home.php?autoload=1&type=universal&table_id={$table_id}");
  exit;
}

// --------- Table title ----------
$stmt = $conn->prepare("SELECT table_title FROM tables WHERE user_id = ? AND table_id = ? LIMIT 1");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$tableTitle = $row['table_title'] ?? 'Untitled table';

// --------- THEAD (load or create default) ----------
$stmt = $conn->prepare("SELECT id, table_id, thead_name, thead_notes, thead_assignee, thead_status, thead_attachment FROM universal_thead WHERE user_id = ? AND table_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$theadRes = $stmt->get_result();
if ($theadRes && $theadRes->num_rows) {
  $thead = $theadRes->fetch_assoc();
} else {
  $thead = [ 'thead_name' => 'Name', 'thead_notes' => 'Notes', 'thead_assignee' => 'Assignee', 'thead_status' => 'Status', 'thead_attachment' => 'Attachment' ];
  $ins = $conn->prepare("INSERT INTO universal_thead (user_id, table_id, thead_name, thead_notes, thead_assignee, thead_status, thead_attachment) VALUES (?,?,?,?,?,?,?)");
  $ins->bind_param('iisssss', $uid, $table_id, $thead['thead_name'], $thead['thead_notes'], $thead['thead_assignee'], $thead['thead_status'], $thead['thead_attachment']);
  $ins->execute(); $ins->close();
}

// --------- Column metadata for the page ----------
$limit  = 10;
$page   = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM universal WHERE user_id = ? AND table_id = ?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute(); $countStmt->bind_result($totalRows); $countStmt->fetch(); $countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("SELECT id, name, notes, assignee, status, attachment_summary FROM universal WHERE user_id = ? AND table_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
$dataStmt->bind_param('iiii', $uid, $table_id, $limit, $offset);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();
$hasRecord = count($rows) > 0;

// Dynamic fields for header/body
$sql  = "SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$result = $stmt->get_result();
$fields = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$colRes    = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universal_base'");
$validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
$dynCount  = 0; foreach ($fields as $m) { if (in_array($m['field_name'], $validCols, true)) $dynCount++; }
$fixedCount = 5; $hasAction = true; $totalCols  = $fixedCount + $dynCount + ($hasAction ? 1 : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Universal Table</title>
</head>
<body>

<header id="appHeader" class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out" style="padding-left:1.25rem;padding-right:1.25rem;">
  <section class="flex mt-6 justify-between ml-3">
    <form method="POST" action="<?= $CATEGORY_URL ?>/edit.php" class="rename-table-form flex gap-2 thead-form">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="text" name="table_title" value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>" class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" placeholder="Untitled table"/>
    </form>

    <button id="addIcon" type="button" class="flex items-center gap-1 bg-blue-600 py-[10px] cursor-pointer hover:bg-blue-700 px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">New Record</span>
    </button>
  </section>

<main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden">
  <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-[80rem]">

  <div class="flex justify-between">
    <div>
      <input id="rowSearchU" type="search" placeholder="Search rows…" data-rows=".universal-row" data-count="#countU" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-72"/>
      <span id="countU" class="ml-2 text-xs text-gray-600"></span>
    </div>

    <svg xmlns="http://www.w3.org/2000/svg" id="actionMenuBtn" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>

    <!-- Action menu -->
  <div id="actionMenuList"
     role="dialog" aria-modal="true" aria-labelledby="moreTitle"
     class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white/95 backdrop-blur shadow-2xl ring-1 ring-black/5 overflow-hidden">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
      <div class="flex items-center gap-3">
        <div class="grid h-8 w-8 place-items-center rounded-full bg-blue-100 text-blue-700">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20Zm1 13h-2v-2h2v2Zm0-4h-2V7h2v4Z"/>
          </svg>
        </div>
        <div>
          <h3 id="moreTitle" class="text-sm font-semibold text-gray-900">More</h3>
          <p class="mt-0.5 text-[11px] text-gray-500">Table actions</p>
        </div>
      </div>

      <button data-close-add class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/>
        </svg>
      </button>
    </div>

    <!-- Body -->
    <div class="p-2 md:w-100 w-90 space-y-5">

      <!-- Add fields (FLEX) -->
      <div id="addColumnBtn" class="cursor-pointer group flex flex-col md:flex-row md:items-center justify-between gap-3 p-3 rounded-xl border border-transparent ring-1 ring-transparent hover:bg-blue-50/60 hover:border-blue-200 hover:ring-blue-100 transition">
        <div class="flex items-start gap-3 md:pr-3 flex-1">
          <div class="mt-0.5 grid place-items-center w-8 h-8 rounded-full bg-blue-100 text-blue-700 shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6Z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-[13px] font-medium text-gray-900">Add fields</h4>
            <p class="text-[11px] leading-4 text-gray-500">Create a new column with a default value.</p>
          </div>
        </div>
        <button id="addFieldsBtn" class="shrink-0 w-full md:w-auto px-4 py-2 text-[12px] font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Add</button>
      </div>

      <div class="h-px bg-gray-100"></div>

      <!-- Danger chip -->
      <div class="flex items-center gap-2 px-0.5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 text-red-700 border border-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2 1 21h22L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V9h2v4Z"/>
          </svg>
          Danger
        </span>
      </div>

      <!-- Delete fields (FLEX) -->
      <div id="addDeleteBtn" class="cursor-pointer group flex flex-col md:flex-row md:items-center justify-between gap-3 p-3 rounded-xl border border-transparent ring-1 ring-transparent hover:bg-red-50/60 hover:border-red-200 hover:ring-red-100 transition">
        <div class="flex items-start gap-3 md:pr-3 flex-1">
          <div class="mt-0.5 grid place-items-center w-8 h-8 rounded-full bg-red-100 text-red-600 shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-3 6h12l-1 10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 9Zm4 2v8h2v-8h-2Zm4 0v8h2v-8h-2Z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h4 class="text-[13px] font-medium text-gray-900">Delete fields</h4>
            <p class="text-[11px] leading-4 text-gray-500">Remove selected fields from this table.</p>
          </div>
        </div>
        <button id="deleteFieldsBtn" class="shrink-0 w-full md:w-auto px-4 py-2 text-[12px] font-medium rounded-md bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">Delete</button>
      </div>

    </div>
  </div>

  <!-- Add Field modal -->
  <div id="addColumnPop" class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[min(92vw,28rem)] rounded-2xl bg-white/95 backdrop-blur shadow-2xl ring-1 ring-black/5">
  <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <svg class="h-6 w-6 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
        </svg>
        <h3 class="text-sm font-semibold text-gray-900">Add new field</h3>
      </div>
      <button data-close-add type="button" id="closeAddColumnPop" class="cursor-pointer p-1.5 rounded-md hover:bg-gray-100 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <div class="px-5 py-4">
    <form action="<?= $CATEGORY_URL ?>/add_fields.php" method="post" class="add-field-form space-y-3">
      <input type="hidden" name="table_id" value="<?= (int)($table_id ?? 0) ?>">
      <label for="field_name" class="block text-sm font-medium text-gray-700">Field name</label>
      <input id="field_name" name="field_name" required class="w-full rounded-xl border border-gray-300 bg-slate-50 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 placeholder:text-gray-400" type="text" placeholder="e.g. Price, SKU, Notes" />
      <div class="pt-2">
        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-white text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Add Field</button>
      </div>
    </form>
  </div>
  </div>

  <!-- Delete Fields modal -->
  <div id="addDeletePop" class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[min(92vw,32rem)] rounded-2xl bg-white/95 backdrop-blur shadow-2xl ring-1 ring-black/5">
  <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex items-center justify-between gap-3">
      <h3 class="text-sm font-semibold text-gray-900">Delete fields</h3>
      <button data-close-add type="button" id="closeDeleteFieldsPop" class="p-1.5 rounded-md hover:bg-gray-100 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <div class="mt-3 flex items-start gap-2 px-1">
      <svg class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <circle cx="12" cy="12" r="9"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v6M12 17h.01"/>
      </svg>
      <p class="text-xs text-gray-600">Select a field to delete. <span class="font-medium text-gray-800">This action can’t be undone.</span></p>
    </div>
  </div>

  <div class="px-5 py-4">
    <form action="<?= $CATEGORY_URL ?>/delete_fields.php" method="post" class="space-y-3">
      <input type="hidden" name="table_id" value="<?= (int)($table_id ?? 0) ?>">
      <?php
        $sql2 = "SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param('ii', $uid, $table_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $fields2 = $result2->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();
      ?>
      <div class="divide-y divide-gray-100 rounded-xl overflow-hidden ring-1 ring-gray-100">
        <?php foreach ($fields2 as $field): ?>
          <div class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-gray-50 transition">
            <input type="text" readonly name="extra_field_<?= (int)$field['id'] ?>" value="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-1 py-1 text-sm text-gray-900 pointer-events-none focus:outline-none" />
            <a href="<?= $CATEGORY_URL ?>/delete_fields.php?id=<?= (int)$field['id'] ?>&table_id=<?= (int)$table_id ?>" class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="Delete" title="Delete">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/>
              </svg>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="pt-3 mt-1 border-t border-gray-100 flex items-center justify-end">
        <button type="button" data-close-add class="px-3 py-1.5 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Cancel</button>
      </div>
    </form>
  </div>
  </div>
  </div>

  <?php $dynFields = $fields; ?>

  <!-- THEAD (editable) -->
  <div class="universal-table" id="ut-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
    <form action="<?= $CATEGORY_URL ?>/edit_thead.php" method="post" class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="hidden" name="row_id" value="<?= (int)($row_id ?? 0) ?>">
      <input type="hidden" name="id" value="<?= (int)($thead['id'] ?? 0) ?>">

      <div class="app-grid gap-2 text-xs font-semibold text-black uppercase" style="--cols: <?= (int)$totalCols ?>;">
        <div class="p-2"><input name="thead_name" value="<?= htmlspecialchars($thead['thead_name'] ?? 'Name', ENT_QUOTES) ?>" placeholder="Name" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
        <div class="p-2"><input name="thead_notes" value="<?= htmlspecialchars($thead['thead_notes'] ?? 'Notes', ENT_QUOTES) ?>" placeholder="Notes" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
        <div class="p-2"><input name="thead_assignee" value="<?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee', ENT_QUOTES) ?>" placeholder="Assignee" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
        <div class="p-2"><input name="thead_status" value="<?= htmlspecialchars($thead['thead_status'] ?? 'Status', ENT_QUOTES) ?>" placeholder="Status" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
        <div class="p-2"><input name="thead_attachment" value="<?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment', ENT_QUOTES) ?>" placeholder="Attachment" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
        <?php foreach ($dynFields as $field): ?>
          <div class="p-2"><input type="text" name="extra_field_<?= (int)$field['id'] ?>" value="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES) ?>" placeholder="Field" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
        <?php endforeach; ?>
        <?php if ($hasAction): ?><div class="p-2"></div><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- TBODY (rows wrapper for JS prepend) -->
  <div id="rows-<?= (int)$table_id ?>" class="w-full divide-y divide-gray-200">
  <?php if ($hasRecord): foreach ($rows as $r): ?>
    <?php
      $statusColors = [ 'To Do' => 'bg-red-100 text-red-800', 'In Progress' => 'bg-yellow-100 text-yellow-800', 'Done' => 'bg-green-100 text-green-800' ];
      $colorClass = $statusColors[$r['status'] ?? ''] ?? 'bg-white text-gray-900';
    ?>
    <form method="POST" action="/ItemPilot/categories/Universal%20Table/edit_tbody.php?id=<?= (int)$r['id'] ?>" enctype="multipart/form-data" class="universal-row border-b border-gray-200 hover:bg-gray-50 text-sm" style="--cols: <?= (int)$totalCols ?>;" data-status="<?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES) ?>">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment_summary'] ?? '', ENT_QUOTES) ?>">

      <div class="p-2 text-gray-600" data-col="name"><input type="text" name="name" value="<?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES) ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2 text-gray-600" data-col="notes"><input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES) ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2 text-gray-600" data-col="assignee"><input type="text" name="assignee" value="<?= htmlspecialchars($r['assignee'] ?? '', ENT_QUOTES) ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>

      <div class="p-2 text-xs font-semibold" data-col="status" data-rows-for="ut-<?= (int)$table_id ?>">
        <select name="status" style="appearance:none;" data-autosave="1" class="w-full px-2 py-1 rounded-xl <?= $colorClass ?>">
          <option value="To Do"       <?= ($r['status'] ?? '') === 'To Do' ? 'selected' : '' ?>>To Do</option>
          <option value="In Progress" <?= ($r['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="Done"        <?= ($r['status'] ?? '') === 'Done' ? 'selected' : '' ?>>Done</option>
        </select>
      </div>

      <div class="p-2 text-gray-600" data-col="attachment">
        <?php if (!empty($r['attachment_summary'])): ?>
          <?php $src = $UPLOAD_URL . '/' . rawurlencode($r['attachment_summary']); ?>
          <img src="<?= htmlspecialchars($src, ENT_QUOTES) ?>" class="thumb" alt="Attachment">
        <?php else: ?>
          <span class="italic text-gray-400 ml-[5px]">📎 None</span>
        <?php endif; ?>
      </div>

      <div class="p-2 text-gray-600" data-col="dyn">
        <?php
          $row_id_loop  = (int)$r['id'];
          $baseRow = [];
          if ($table_id > 0 && $uid > 0 && $row_id_loop > 0) {
            $stmtX = $conn->prepare("SELECT * FROM universal_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
            $stmtX->bind_param('iii', $table_id, $uid, $row_id_loop);
            $stmtX->execute();
            $baseRow = $stmtX->get_result()->fetch_assoc() ?: [];
            $stmtX->close();
          }
          foreach ($dynFields as $colMeta): $colName = $colMeta['field_name']; ?>
          <input type="text" name="dyn[<?= htmlspecialchars($colName, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($baseRow[$colName] ?? '', ENT_QUOTES) ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        <?php endforeach; ?>
      </div>

      <div class="p-2">
        <a href="<?= $CATEGORY_URL ?>/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>" class="icon-btn" aria-label="Delete row">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6m2 4H7l1 12h8l1-12z"/>
          </svg>
        </a>
      </div>
    </form>
    <?php endforeach; else: ?>
      <div class="empty-state px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300"
          data-empty="1">No records found.</div>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="pagination my-4 flex justify-start md:justify-center space-x-2">
      <?php if ($page > 1): ?><a href="insert_universal.php?page=<?= $page-1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">« Prev</a><?php endif; ?>
      <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="insert_universal.php?page=<?= $i ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded transition <?= $i===$page ? 'bg-blue-600 text-white border-blue-600 font-semibold' : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?><a href="insert_universal.php?page=<?= $page+1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">Next »</a><?php endif; ?>
    </div>
  <?php endif; ?>
</main>
</header>

<!-- Add New Record (modal) -->
<div id="addForm" class="min-h-screen flex items-center justify-center p-2 hidden relative mt-13">
  <div class="bg-white w-full max-w-md p-5 rounded-2xl shadow-lg" id="signup">
    <div class="flex justify-between">
      <a href="#" data-close-add>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="6" y1="6" x2="18" y2="18" />
          <line x1="6" y1="18" x2="18" y2="6" />
        </svg>
      </a>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </div>

    <form action="<?= $CATEGORY_URL ?>/insert_universal.php" method="POST" enctype="multipart/form-data" class="new-record-form space-y-6">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <h1 class="w-full px-4 py-2 text-center text-2xl"><?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?></h1>

      <div class="mt-5">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?></label>
        <input type="text" name="name" placeholder="<?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?></label>
        <input type="text" name="notes" placeholder="<?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?></label>
        <input type="text" name="assignee" placeholder="<?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['thead_status'] ?? 'Status') ?></label>
        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="To Do">To Do</option>
          <option value="In Progress">In Progress</option>
          <option value="Done">Done</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment') ?></label>
        <input id="attachment_summary" type="file" name="attachment_summary" accept="image/*" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
      </div>

      <?php
        $sql3 = "SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param('ii', $uid, $table_id);
        $stmt3->execute();
        $result3 = $stmt3->get_result();
        $fields3 = $result3->fetch_all(MYSQLI_ASSOC);
        $stmt3->close();
      ?>
      <?php foreach ($fields3 as $field): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" name="extra_field_<?= (int)$field['id'] ?>" placeholder="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2"/>
        </div>
      <?php endforeach; ?>

      <div>
        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">Create New Record</button>
      </div>
    </form>
  </div>
</div>

<style>
.custom-select { appearance: none; }
</style>

</body>
</html>
