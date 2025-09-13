<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0) { header("Location: register/login.php"); exit; }

$CATEGORY_URL = '/ItemPilot/categories/Universal%20Table';
$UPLOAD_DIR   = __DIR__ . '/uploads/';
$UPLOAD_URL   = $CATEGORY_URL . '/uploads';

$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($action === 'create_blank') {
  $stmt = $conn->prepare("INSERT INTO `tables` (user_id, created_at) VALUES (?, NOW())");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $table_id = (int)$conn->insert_id;
  $stmt->close();
  $_SESSION['current_table_id'] = $table_id;

} elseif ($table_id > 0) {
  $_SESSION['current_table_id'] = $table_id;

} else {
  $table_id = (int)($_SESSION['current_table_id'] ?? 0);

  if ($table_id <= 0) {
    $q = $conn->prepare("SELECT table_id FROM `tables` WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute(); $q->bind_result($latestId); $q->fetch(); $q->close();
    $table_id = (int)$latestId;
  }

  if ($table_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO `tables` (user_id, created_at) VALUES (?, NOW())");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$conn->insert_id;
    $stmt->close();
  }

  $_SESSION['current_table_id'] = $table_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id        = $_POST['id'] ?? '';
  $name      = $_POST['name'] ?? '';
  $notes     = $_POST['notes'] ?? '';
  $assignee  = $_POST['assignee'] ?? '';
  $status    = $_POST['status'] ?? '';
  $attachment_summary = $_POST['existing_attachment'] ?? '';

  $dynIn = $_POST['dyn'] ?? [];

  $mapStmt = $conn->prepare("SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC");
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

  if (isset($_FILES['attachment_summary']) && $_FILES['attachment_summary']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
      die("Could not create uploads directory.");
    }
    $tmp  = $_FILES['attachment_summary']['tmp_name'];
    $orig = basename($_FILES['attachment_summary']['name']);
    $dest = $UPLOAD_DIR . $orig;
    if (!move_uploaded_file($tmp, $dest)) {
      die("Failed to save uploaded file.");
    }
    $attachment_summary = $orig;
  }

  $rec_id = is_numeric($id) ? (int)$id : 0;

  if ($rec_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO universal (name, notes, assignee, status, attachment_summary, table_id, user_id) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('ssssssi', $name, $notes, $assignee, $status, $attachment_summary, $table_id, $uid);
    $stmt->execute();
    $row_id = (int)$stmt->insert_id; 
    $stmt->close();

    $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universal_base'");
    $validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
    $exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
    $editable  = array_values(array_diff($validCols, $exclude));

    $toSave = [];
    foreach ($dynIn as $k => $v) {
      if (in_array($k, $editable, true)) {
        $toSave[$k] = ($v === '') ? null : $v;
      }
    }

    if ($toSave) {
      $cols  = array_keys($toSave);
      $place = array_fill(0, count($cols), '?');
      $sql   = "INSERT INTO universal_base (`table_id`,`user_id`,`row_id`,`" . implode("`,`", $cols) . "`) VALUES (?,?,?," . implode(',', $place) . ")";
      $stmt  = $conn->prepare($sql);

      $types  = 'iii' . str_repeat('s', count($cols));
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
    $stmt = $conn->prepare("UPDATE universal SET name=?, notes=?, assignee=?, status=?, attachment_summary=? WHERE id=? AND table_id=? AND user_id=?");
    $stmt->bind_param('sssssiii', $name, $notes, $assignee, $status, $attachment_summary, $rec_id, $table_id, $uid);
    $stmt->execute();
    $stmt->close();

    if (!empty($dynIn)) {
      $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universal_base'");
      $validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
      $exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
      $editable  = array_values(array_diff($validCols, $exclude));

      $toSave = [];
      foreach ($dynIn as $k => $v) {
        if (in_array($k, $editable, true)) {
          $toSave[$k] = ($v === '') ? null : $v;
        }
      }

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

      if ($toSave) {
        $set = [];
        $vals = [];
        $types = '';
        foreach ($toSave as $col => $val) {
          if ($val === null) { $set[] = "`$col`=NULL"; }
          else { $set[] = "`$col`=?"; $vals[] = $val; $types .= 's'; }
        }
        if (in_array('updated_at', $validCols, true)) { $set[] = "`updated_at`=NOW()"; }
        if ($set) {
          $sql = "UPDATE universal_base SET " . implode(', ', $set) . " WHERE table_id=? AND user_id=? AND row_id=?";
          $types .= 'iii';
          $vals[] = $table_id; $vals[] = $uid; $vals[] = $rec_id;

          $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
          $stmt = $conn->prepare($sql);
          call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($vals)));
          $stmt->execute();
          $stmt->close();
        }
      }
    }
  }

    header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;
}

$stmt = $conn->prepare("SELECT table_title FROM `tables` WHERE user_id = ? AND table_id = ? LIMIT 1");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$tableTitle = $row['table_title'] ?? 'Untitled table';

$stmt = $conn->prepare("SELECT id, table_id, thead_name, thead_notes, thead_assignee, thead_status, thead_attachment FROM universal_thead WHERE user_id = ? AND table_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$theadRes = $stmt->get_result();
if ($theadRes && $theadRes->num_rows) {
  $thead = $theadRes->fetch_assoc();
} else {
  $thead = [
    'thead_name'       => 'Name',
    'thead_notes'      => 'Notes',
    'thead_assignee'   => 'Assignee',
    'thead_status'     => 'Status',
    'thead_attachment' => 'Attachment',
  ];
  $ins = $conn->prepare("INSERT INTO universal_thead (user_id, table_id, thead_name, thead_notes, thead_assignee, thead_status, thead_attachment) VALUES (?,?,?,?,?,?,?)");
  $ins->bind_param('iisssss', $uid, $table_id,
    $thead['thead_name'], $thead['thead_notes'], $thead['thead_assignee'],
    $thead['thead_status'], $thead['thead_attachment']
  );
  $ins->execute(); $ins->close();
}
$stmt->close();

$limit  = 10;
$page   = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM universal WHERE user_id = ? AND table_id = ?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute(); $countStmt->bind_result($totalRows); $countStmt->fetch(); $countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("SELECT id, name, notes, assignee, status, attachment_summary FROM universal WHERE user_id = ? AND table_id = ? ORDER BY id ASC LIMIT ? OFFSET ?");
$dataStmt->bind_param('iiii', $uid, $table_id, $limit, $offset);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();
$hasRecord = count($rows) > 0;
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
    <form method="POST" action="<?= $CATEGORY_URL ?>/edit.php" class="flex gap-2">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="text" name="table_title" value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>" class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" placeholder="Untitled table"/>
    </form>

    <button id="addIcon" type="button" class="flex items-center gap-1 bg-blue-800 py-[10px] cursor-pointer hover:bg-blue-700 px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">New Record</span>
    </button>
  </section>

<main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden">
  <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-[60rem]">

  <div class="flex justify-between">
    <div>
      <input id="rowSearchU" type="search" placeholder="Search rows…" data-rows=".universal-row" data-count="#countU" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-72"/>
      <span id="countU" class="ml-2 text-xs text-gray-600"></span>
    </div>

    <svg xmlns="http://www.w3.org/2000/svg" id="actionMenuBtn" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>

    <div class="hidden fixed z-[70] left-1/2 top-1/2 p-3 -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white shadow-xl ring-1 ring-black/5" id="actionMenuList">
      <div class="flex items-center justify-between px-5 py-2 border-b border-gray-100">
        <h3 id="moreTitle" class="text-base font-semibold text-gray-900">More</h3>
        <button data-close-add class="p-2 rounded-md hover:bg-gray-100 cursor-pointer" aria-label="Close">
          <!-- X icon -->
          <svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/>
          </svg>
        </button>
      </div>

      <div class="space-y-4">
        <!-- Add fields row -->
        <div class="md:flex items-start gap-3 border-b border-gray-100 p-3 rounded-xl hover:bg-gray-50 cursor-pointer" id="addColumnBtn">
          <!-- icon -->
          <svg class="w-5 h-5 mt-0.5 text-blue-600 mt-2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6Z"/>
          </svg>
          <div class="flex-1">
            <h4 class="text-sm font-medium text-gray-900">Add fields</h4>
            <p class="text-xs text-gray-500">Create a new column with type and default value.</p>
          </div>
          <button id="addFieldsBtn" class="px-3 py-1.5 text-sm font-medium text-blue-800 rounded-lg hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500/30">Add</button>
        </div>

        <!-- Delete fields row -->
        <div class="md:flex items-start gap-3 p-3 rounded-xl mt-2 hover:bg-gray-50 border-b border-gray-100 cursor-pointer" id="addDeleteBtn">
          <!-- icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="mt-2" width="20" height="20" viewBox="0 0 24 24" fill="#ef4444" aria-hidden="true">
            <path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-3 6h12l-1 10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 9Zm4 2v8h2v-8h-2Zm4 0v8h2v-8h-2Z"/>
          </svg>
          <div class="flex-1">
            <h4 class="text-sm font-medium text-gray-900">Delete fields</h4>
            <p class="text-xs text-gray-500">Remove selected fields from this table.</p>
          </div>
          <button id="deleteFieldsBtn" class="px-3 py-1.5 text-sm font-medium text-red-600 rounded-lg hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500/30">Delete
          </button>
        </div>
      </div>
    </div>


    <div id="addColumnPop" class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-80 rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
      <div class="px-4 py-3 border-b border-gray-100">
        <div class="flex justify-between items-center gap-2">
          <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
          <h3 class="text-sm font-semibold text-gray-900">Add new field</h3>
          <button data-close-add type="button" class="cursor-pointer p-1 rounded-md hover:bg-gray-100" aria-label="Close" id="closeAddColumnPop">
            <svg class="h-5 w-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <form action="/ItemPilot/categories/Universal%20Table/add_fields.php" method="post">
          <input type="hidden" name="table_id" value="<?= (int)($table_id ?? 0) ?>">

          <label for="field_name" class="block text-sm font-medium text-gray-700 mt-4">Field Name</label>
          <input type="text" id="field_name" name="field_name" required class="w-full mt-1 mb-3 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>

          <button type="submit" class="w-full bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700 transition">Add Field</button>
        </form>
      </div>
    </div>

    <div id="addDeletePop" class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-96 max-w-[90vw] rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
      <div class="px-4 py-3 border-b border-gray-100">
        <div class="flex justify-between items-center gap-2">
          <h3 class="text-sm font-semibold text-gray-900">Delete fields</h3>
          <button data-close-add type="button" class="p-1 rounded-md hover:bg-gray-100" aria-label="Close" id="closeAddColumnPop">
            <svg class="h-5 w-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="flex items-start mt-4 gap-2">
          <svg class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="9"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v6M12 17h.01"/>
          </svg>
          <p class="text-xs text-gray-500">Select a field to delete. This action can’t be undone.</p>
        </div>

        <div class="w-full bg-gray-100 h-[1px] mt-2"></div>
        <form action="/ItemPilot/categories/Universal%20Table/delete_fields.php" method="post" class="mt-3">
          <input type="hidden" name="table_id" value="<?= (int)($table_id ?? 0) ?>">

          <?php
          $uid = (int)($_SESSION['user_id'] ?? 0);
          $table_id = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);

          $sql = "SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param('ii', $uid, $table_id);
          $stmt->execute();
          $result = $stmt->get_result();
          $fields = $result->fetch_all(MYSQLI_ASSOC);
          $stmt->close();
          ?>

          <div class="divide-y divide-gray-100">
            <?php foreach ($fields as $field): ?>
              <div class="flex items-center justify-between">
                <input type="text" readonly name="extra_field_<?= (int)$field['id'] ?>" value="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent px-1 py-1 text-base text-gray-900"/>

                <a href="/ItemPilot/categories/Universal%20Table/delete_fields.php?id=<?= (int)$field['id'] ?>&table_id=<?= (int)$table_id ?>" onclick="return confirm('Delete this field?')" class="inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="Delete" title="Delete">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                      fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/>
                  </svg>
                </a>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="pt-3 mt-2 border-t border-gray-100 flex items-center justify-end">
            <button type="button" data-close-add class="px-3 py-1.5 text-xs rounded-md border bg-blue-600 hover:bg-blue-700 text-white cursor-pointer">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<!-- ===== Helpers (add once) ===== -->
<!-- ====== Equal-width table (each column = 100% / N) ====== -->
<style>
  .ut-eq{display:grid;grid-template-columns:repeat(var(--cols),minmax(0,1fr));align-items:center;column-gap:.75rem}
  .ut-row{padding:.5rem 0;border-bottom:1px solid #e5e7eb}
  .ut-head{font-weight:700;font-size:.75rem;color:#111827}
  .ut-cell{min-width:0}
  .ut-field{width:100%;height:36px;padding:0 .75rem;border:none;background:transparent;border-radius:.5rem}
  .ut-field:focus{outline:none;box-shadow:0 0 0 2px rgba(59,130,246,.45)}
  .ut-truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

  .ut-pill{height:28px;border:0;border-radius:9999px;padding:0 .5rem;font-size:.75rem;line-height:28px;appearance:none;outline:0}
  .pill-todo{background:#fee2e2;color:#991b1b}
  .pill-progress{background:#fef3c7;color:#92400e}
  .pill-done{background:#dcfce7;color:#166534}
</style>

<?php
// ---------- shared data for columns ----------
$uid      = (int)($_SESSION['user_id'] ?? 0);
$table_id = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);

// dynamic field names configured by the user
$stmt = $conn->prepare("SELECT id, field_name FROM universal_fields WHERE user_id=? AND table_id=? ORDER BY id ASC");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// only include dynamic columns that exist in universal_base
$colsRes   = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universal_base'");
$validCols = array_column($colsRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
$dynCols   = array_values(array_filter($fields, fn($m)=>in_array($m['field_name'], $validCols, true)));

// fixed columns (match your UI)
$fixedCols  = ['name','notes','assignee','status','attachment'];
$hasActions = true; // trash icon column

$totalCols = count($fixedCols) + count($dynCols) + ($hasActions ? 1 : 0);
?>

<!-- ================= THEAD ================= -->
<div class="universal-table" id="ut-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
  <form action="<?= $CATEGORY_URL ?>/edit_thead.php" method="post" class="thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">
    <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
    <input type="hidden" name="row_id"   value="<?= (int)($row_id ?? 0) ?>">
    <input type="hidden" name="id"       value="<?= (int)($thead['id'] ?? 0) ?>">

    <div class="ut-eq ut-row ut-head" style="--cols: <?= (int)$totalCols ?>;">
      <div class="ut-cell"><input name="thead_name"       class="ut-field ut-truncate" placeholder="Names"      value="<?= htmlspecialchars($thead['thead_name'] ?? 'Names', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="ut-cell"><input name="thead_notes"      class="ut-field ut-truncate" placeholder="Notes"      value="<?= htmlspecialchars($thead['thead_notes'] ?? 'Notes', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="ut-cell"><input name="thead_assignee"   class="ut-field ut-truncate" placeholder="Assignee"   value="<?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="ut-cell"><input name="thead_status"     class="ut-field ut-truncate" placeholder="Status"     value="<?= htmlspecialchars($thead['thead_status'] ?? 'Status', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="ut-cell"><input name="thead_attachment" class="ut-field ut-truncate" placeholder="Attachment" value="<?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment', ENT_QUOTES, 'UTF-8') ?>"></div>

      <?php foreach ($dynCols as $dc): ?>
        <div class="ut-cell">
          <input type="text" name="extra_field_<?= (int)$dc['id'] ?>" class="ut-field ut-truncate"
                 placeholder="Field" value="<?= htmlspecialchars($dc['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
      <?php endforeach; ?>

      <?php if ($hasActions): ?><div class="ut-cell"></div><?php endif; ?>
    </div>
  </form>
</div>

<!-- ================= TBODY ================= -->
<div class="w-full">
  <?php if ($hasRecord): foreach ($rows as $r): ?>
    <?php
      // load dynamic values for this row
      $row_id  = (int)$r['id'];
      $user_id = (int)($_SESSION['user_id'] ?? 0);
      $baseRow = [];
      if ($table_id && $user_id && $row_id) {
        $stmt = $conn->prepare("SELECT * FROM `universal_base` WHERE `table_id`=? AND `user_id`=? AND `row_id`=? LIMIT 1");
        $stmt->bind_param('iii', $table_id, $user_id, $row_id);
        $stmt->execute();
        $baseRow = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
      }
      $s = $r['status'] ?? '';
      $pill = $s==='Done' ? 'pill-done' : ($s==='In Progress' ? 'pill-progress' : 'pill-todo');
    ?>

    <form method="POST" action="/ItemPilot/categories/Universal%20Table/edit_tbody.php?id=<?= (int)$r['id'] ?>"
          enctype="multipart/form-data"
          class="ut-eq ut-row hover:bg-gray-50 text-sm"
          style="--cols: <?= (int)$totalCols ?>;">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment_summary'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="ut-cell"><input name="name"     class="ut-field ut-truncate" value="<?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="ut-cell"><input name="notes"    class="ut-field ut-truncate" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="ut-cell"><input name="assignee" class="ut-field ut-truncate" value="<?= htmlspecialchars($r['assignee'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>

      <div class="ut-cell">
        <select name="status" class="ut-pill <?= $pill ?>">
          <option value="To Do"       <?= $s==='To Do'?'selected':'' ?>>To Do</option>
          <option value="In Progress" <?= $s==='In Progress'?'selected':'' ?>>In Progress</option>
          <option value="Done"        <?= $s==='Done'?'selected':'' ?>>Done</option>
        </select>
      </div>

      <div class="ut-cell">
        <?php if (!empty($r['attachment_summary'])): $src="/ItemPilot/categories/Universal%20Table/uploads/".rawurlencode($r['attachment_summary']); ?>
          <img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" class="w-8 h-8 rounded-md" alt="Attachment">
        <?php else: ?>
          <span class="italic text-gray-400">📎 None</span>
        <?php endif; ?>
      </div>

      <?php foreach ($dynCols as $dc): $col=$dc['field_name']; ?>
        <div class="ut-cell">
          <input name="dyn[<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>]"
                 class="ut-field ut-truncate"
                 value="<?= htmlspecialchars($baseRow[$col] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
      <?php endforeach; ?>

      <?php if ($hasActions): ?>
        <div class="ut-cell">
          <a href="<?= $CATEGORY_URL ?>/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>"
             onclick="return confirm('Are you sure?')" class="inline-block text-red-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-500 hover:text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6m2 4H7l1 12h8l1-12z"/>
            </svg>
          </a>
        </div>
      <?php endif; ?>
    </form>

  <?php endforeach; else: ?>
    <div class="px-4 py-4 text-center text-gray-500 border-b border-gray-300">No records found.</div>
  <?php endif; ?>
</div>


  <?php if ($totalPages > 1): ?>
    <div class="pagination my-4 flex justify-start md:justify-center space-x-2">
      <?php if ($page > 1): ?>
        <a href="insert_universal.php?page=<?= $page-1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">« Prev</a>
      <?php endif; ?>
      <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="insert_universal.php?page=<?= $i ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded transition <?= $i===$page ? 'bg-blue-600 text-white border-blue-600 font-semibold' : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="insert_universal.php?page=<?= $page+1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">Next »</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
</header>

<!-- Add New Record -->
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

    <form action="<?= $CATEGORY_URL ?>/insert_universal.php" method="POST" enctype="multipart/form-data" class="space-y-6">
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
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $table_id = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);

        $sql = "SELECT id, field_name FROM universal_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $uid, $table_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $fields = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
      ?>
      <?php foreach ($fields as $field): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </label>
          <input type="text" name="extra_field_<?= (int)$field['id'] ?>" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2"/>
        </div>
      <?php endforeach; ?>

      <div>
        <button type="submit" class="w-full py-3 bg-blue-800 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
          Create New Record
        </button>
      </div>
    </form>
  </div>
</div>

<style>
.custom-select { appearance: none; }
</style>

</body>
</html>
