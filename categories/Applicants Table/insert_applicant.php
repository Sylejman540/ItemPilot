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

/* ---------- Config (DRESSES) ---------- */
$CATEGORY_URL = '/ItemPilot/categories/Applicants%20Table';
$UPLOAD_DIR   = __DIR__ . '/uploads/';
$UPLOAD_URL   = $CATEGORY_URL . '/uploads';

/* ---------- Resolve table_id ---------- */
$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($action === 'create_blank') {
  $stmt = $conn->prepare("INSERT INTO applicants_table (user_id, created_at) VALUES (?, NOW())");
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
    $q = $conn->prepare("SELECT table_id FROM applicants_table WHERE user_id = ? ORDER BY table_id ASC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute(); $q->bind_result($latestId); $q->fetch(); $q->close();
    $table_id = (int)$latestId;
  }
  if ($table_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO applicants_table (user_id, created_at) VALUES (?, NOW())");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$stmt->insert_id;
    $stmt->close();
  }
  $_SESSION['current_table_id'] = $table_id;
}

/* ---------- Create/Update (applicants + base) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id       = $_POST['id'] ?? '';
  $rec_id   = is_numeric($id) ? (int)$id : 0;
  $table_id = (int)($_POST['table_id'] ?? $table_id);

  $name            = trim($_POST['name'] ?? '');
  $stage           = trim($_POST['stage'] ?? '');
  $applying_for    = trim($_POST['applying_for'] ?? '');
  $email_address   = trim($_POST['email_address'] ?? '');
  $phone           = trim($_POST['phone'] ?? '');
  $interview_date  = trim($_POST['interview_date'] ?? ''); // expect 'YYYY-MM-DD' or ''
  $interviewer     = trim($_POST['interviewer'] ?? '');
  $interview_score = trim($_POST['interview_score'] ?? '');
  $notes           = trim($_POST['notes'] ?? '');

  // Handle upload
  if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
      if (is_ajax()) { json_out(['ok'=>false, 'error'=>'Upload failed (PHP error '.$_FILES['photo']['error'].')'], 400); }
      header("Location: /ItemPilot/home.php?autoload=1&type=applicants&table_id={$table_id}", true, 303);
      exit;
    }
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
      if (is_ajax()) { json_out(['ok'=>false, 'error'=>'Could not create uploads directory.'], 500); }
      header("Location: /ItemPilot/home.php?autoload=1&type=applicants&table_id={$table_id}", true, 303);
      exit;
    }
    $tmp   = $_FILES['photo']['tmp_name'];
    $orig  = basename($_FILES['photo']['name']);
    $ext   = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $safe  = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $fname = $safe . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($ext ? ".{$ext}" : '');
    $dest  = $UPLOAD_DIR . $fname;
    move_uploaded_file($tmp, $dest);
    $photo = $fname;
  }

  // Dynamic fields
  $dynIn = $_POST['dyn'] ?? [];
  $mapStmt = $conn->prepare("SELECT id, field_name FROM applicants_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC");
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

  // Whitelist dynamic columns
  $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants_base'");
  $validCols = $colRes ? array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME') : [];
  $exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
  $editable  = array_values(array_diff($validCols, $exclude));

  $toSave = [];
  foreach ($dynIn as $k => $v) {
    if (in_array($k, $editable, true)) $toSave[$k] = ($v === '') ? null : $v;
  }

  $actionPerformed = ($rec_id <= 0) ? 'create' : 'update';

  if ($rec_id <= 0) {
    // INSERT into applicants
    $stmt = $conn->prepare("INSERT INTO applicants (name, stage, applying_for, email_address, phone, interview_date, interviewer, interviewer_score, notes, table_id, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssssssii', $name, $stage, $applying_for, $email_address, $phone, $interview_date, $interviewer, $interviewer_score, $notes, $table_id, $uid);
    $stmt->execute();
    $row_id = (int)$stmt->insert_id;
    $stmt->close();

    // applicants_base
    if ($toSave) {
      $cols  = array_keys($toSave);
      $place = array_fill(0, count($cols), '?');
      $sql   = "INSERT INTO applicants_base (`table_id`,`user_id`,`row_id`,`" . implode("`,`", $cols) . "`) VALUES (?,?,?," . implode(',', $place) . ")";
      $stmt  = $conn->prepare($sql);
      $types = 'iii' . str_repeat('s', count($cols));
      $params = [$table_id, $uid, $row_id];
      foreach ($cols as $c) { $params[] = $toSave[$c]; }
      $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
      call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($params)));
      $stmt->execute();
      $stmt->close();
    } else {
      $insb = $conn->prepare("INSERT INTO applicants_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
      $insb->bind_param('iii', $table_id, $uid, $row_id);
      $insb->execute();
      $insb->close();
    }

  } else {
    // UPDATE applicants
    $stmt = $conn->prepare("UPDATE applicants SET name=?, stage=?, applying_for=?, email_address=?, phone=?, interview_date=?, interviewer=?, interviewer_score, notes WHERE id=? AND table_id=? AND user_id=?");
    $stmt->bind_param('sssssssssiii', $name, $stage, $applying_for, $email_address, $phone, $interview_date, $interviewer, $interviewer_score, $notes, $rec_id, $table_id, $uid);
    $stmt->execute();
    $stmt->close();

    // ensure applicants_base exists
    $chk = $conn->prepare("SELECT id FROM applicants_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
    $chk->bind_param('iii', $table_id, $uid, $rec_id);
    $chk->execute();
    $base = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$base) {
      $insb = $conn->prepare("INSERT INTO applicants_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
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
        $sql = "UPDATE applicants_base SET ".implode(', ', $setParts)." WHERE table_id=? AND user_id=? AND row_id=?";
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

  /* ---------- AJAX response ---------- */
  if (is_ajax()) {
    json_out([
      'ok'        => true,
      'action'    => $actionPerformed,
      'row_id'    => $row_id,
      'table_id'  => $table_id
    ]);
  }

  // Non-AJAX
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;
}

/* ---------------------------
   Pagination + data fetch
----------------------------*/
$limit  = 10;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM applicants WHERE user_id = ? AND table_id = ?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute(); $countStmt->bind_result($totalRows); $countStmt->fetch(); $countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("
  SELECT id, name, stage, applying_for, attachment, email_address, phone,
         interview_date, interviewer, interview_score, notes
    FROM applicants
   WHERE user_id = ? AND table_id = ?
ORDER BY id ASC
   LIMIT ? OFFSET ?
");
$dataStmt->bind_param('iiii', $uid, $table_id, $limit, $offset);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();
$hasRecord = count($rows) > 0;

/* ---------------------------
   Table head labels (fixed headers)
----------------------------*/
$theadStmt = $conn->prepare("
  SELECT id, name, stage, applying_for, attachment, email_address, phone,
         interview_date, interviewer, interview_score, notes
    FROM applicants_thead
   WHERE user_id = ? AND table_id = ?
ORDER BY id DESC
   LIMIT 1
");
$theadStmt->bind_param('ii', $uid, $table_id);
$theadStmt->execute();
$headRow = $theadStmt->get_result()->fetch_assoc();
$theadStmt->close();

if (!$headRow) {
  $defaults = [
    'name'            => 'Name',
    'stage'           => 'Stage',
    'applying_for'    => 'Applying For',
    'attachment'      => 'Attachment',
    'email_address'   => 'Email Address',
    'phone'           => 'Phone',
    'interview_date'  => 'Interview Date',
    'interviewer'     => 'Interviewer',
    'interview_score' => 'Interview Score',
    'notes'           => 'Notes',
  ];
  $ins = $conn->prepare("
    INSERT INTO applicants_thead
      (user_id, table_id, name, stage, applying_for, attachment, email_address, phone,
       interview_date, interviewer, interview_score, notes)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
  ");
  $ins->bind_param(
    'iissssssssss',
    $uid, $table_id,
    $defaults['name'], $defaults['stage'], $defaults['applying_for'], $defaults['attachment'],
    $defaults['email_address'], $defaults['phone'], $defaults['interview_date'],
    $defaults['interviewer'], $defaults['interview_score'], $defaults['notes']
  );
  $ins->execute();
  $newId = (int)$conn->insert_id;
  $ins->close();
  $headRow = array_merge(['id'=>$newId,'table_id'=>$table_id],$defaults);
}

/* ---------------------------
   Dynamic fields for rendering
----------------------------*/
/* ---------------------------
   Dynamic fields metadata
----------------------------*/
$fieldsStmt = $conn->prepare("SELECT id, field_name FROM applicants_fields WHERE user_id=? AND table_id=? ORDER BY id ASC");
$fieldsStmt->bind_param('ii', $uid, $table_id);
$fieldsStmt->execute(); $fields = $fieldsStmt->get_result()->fetch_all(MYSQLI_ASSOC); $fieldsStmt->close();

$colRes    = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='applicants_base'");
$validCols = $colRes ? array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME') : [];
$dynCount  = 0;
foreach ($fields as $f) if (in_array($f['field_name'], $validCols, true)) $dynCount++;

/* Fixed columns in grid: 9 (name, delivery, country, status, age, price, material, profit, model) */
$fixedCount = 10;
$hasAction  = true;
$totalCols  = $fixedCount + $dynCount + ($hasAction ? 1 : 0);

/* ---------------------------
   Title (table name)
----------------------------*/
$titleStmt = $conn->prepare("
  SELECT table_title FROM applicants_table
   WHERE user_id = ? AND table_id = ? LIMIT 1
");
$titleStmt->bind_param('ii', $uid, $table_id);
$titleStmt->execute();
$titleRes = $titleStmt->get_result(); $tableTitleRow = $titleRes->fetch_assoc();
$titleStmt->close();
$tableTitle = $tableTitleRow['table_title'] ?? 'Untitled Applicants Table';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Applicants</title>
</head>
<body>

<header id="appHeader" class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out" style="padding-left:1.25rem;padding-right:1.25rem;">
<section class="flex mt-6 justify-between ml-3">
    <form method="POST" action="<?= $CATEGORY_URL ?>/edit.php" class="flex gap-2 thead-form">
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

<main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden" id="applicantsSection">
  <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-[94rem]">

<div class="flex justify-between">
    <div>
      <input id="rowSearchA" type="search" placeholder="Search rows…" data-rows=".applicant-row" data-count="#countA" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-72"/>
      <span id="countA" class="ml-2 text-xs text-gray-600"></span>
    </div>

    <svg xmlns="http://www.w3.org/2000/svg" id="actionMenuBtn" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>

    <!-- Action menu -->
  <div id="actionMenuList"
     role="dialog" aria-modal="true" aria-labelledby="moreTitle"
     class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2
           rounded-2xl bg-white/95 backdrop-blur
            shadow-2xl ring-1 ring-black/5 overflow-hidden">

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

      <button data-close-add
              class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              aria-label="Close">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/>
        </svg>
      </button>
    </div>

    <!-- Body -->
    <div class="p-2 md:w-100 w-90 space-y-5">

      <!-- Add fields (FLEX) -->
      <div id="addColumnBtn"
          class="cursor-pointer group flex flex-col md:flex-row md:items-center justify-between gap-3
                  p-3 rounded-xl border border-transparent ring-1 ring-transparent
                  hover:bg-blue-50/60 hover:border-blue-200 hover:ring-blue-100 transition">
        <!-- left: icon + text -->
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
        <!-- right: button -->
        <button id="addFieldsBtn"
                class="shrink-0 w-full md:w-auto px-4 py-2 text-[12px] font-medium rounded-md
                      bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
          Add
        </button>
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
      <div id="addDeleteBtn"
          class="cursor-pointer group flex flex-col md:flex-row md:items-center justify-between gap-3
                  p-3 rounded-xl border border-transparent ring-1 ring-transparent
                  hover:bg-red-50/60 hover:border-red-200 hover:ring-red-100 transition">
        <!-- left: icon + text -->
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
        <!-- right: button -->
        <button id="deleteFieldsBtn"
                class="shrink-0 w-full md:w-auto px-4 py-2 text-[12px] font-medium rounded-md
                      bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
          Delete
        </button>
      </div>

    </div>
  </div>


  <!-- Add Field modal -->
  <div id="addColumnPop"
     class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2
            w-[min(92vw,28rem)] rounded-2xl bg-white/95 backdrop-blur
            shadow-2xl ring-1 ring-black/5">
  <!-- Header -->
  <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <svg class="h-6 w-6 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
        </svg>
        <h3 class="text-sm font-semibold text-gray-900">Add new field</h3>
      </div>
      <button data-close-add type="button" id="closeAddColumnPop"
              class="cursor-pointer p-1.5 rounded-md hover:bg-gray-100 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              aria-label="Close">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Body -->
  <div class="px-5 py-4">
    <form action="/ItemPilot/categories/Applicants%20Table/add_fields.php" method="post" class="space-y-3">
      <input type="hidden" name="table_id" value="<?= (int)($table_id ?? 0) ?>">

      <label for="field_name" class="block text-sm font-medium text-gray-700">Field name</label>
      <input id="field_name" name="field_name" required
             class="w-full rounded-xl border border-gray-300 bg-slate-50 px-3 py-2
                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                    placeholder:text-gray-400"
             type="text" placeholder="e.g. Price, SKU, Notes" />

      <div class="pt-2">
        <button type="submit"
                class="w-full rounded-lg bg-blue-600 px-4 py-2 text-white text-sm font-medium
                       hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
          Add Field
        </button>
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

    <!-- Danger hint -->
    <div class="mt-3 flex items-start gap-2 px-1">
      <svg class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <circle cx="12" cy="12" r="9"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v6M12 17h.01"/>
      </svg>
      <p class="text-xs text-gray-600">
        Select a field to delete. <span class="font-medium text-gray-800">This action can’t be undone.</span>
      </p>
    </div>
  </div>

  <!-- Body -->
  <div class="px-5 py-4">
    <form action="/ItemPilot/categories/Applicants%20Table/delete_fields.php" method="post" class="space-y-3">
      <input type="hidden" name="table_id" value="<?= (int)($table_id ?? 0) ?>">

      <?php
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $table_id = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);
        $sql = "SELECT id, field_name FROM applicants_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $uid, $table_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $fields = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
      ?>

      <div class="divide-y divide-gray-100 rounded-xl overflow-hidden ring-1 ring-gray-100">
        <?php foreach ($fields as $field): ?>
          <div class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-gray-50 transition">
            <input type="text" readonly
                   name="extra_field_<?= (int)$field['id'] ?>"
                   value="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-transparent border-none px-1 py-1 text-sm text-gray-900
                          pointer-events-none focus:outline-none" />

            <a href="/ItemPilot/categories/Applicants%20Table/delete_fields.php?id=<?= (int)$field['id'] ?>&table_id=<?= (int)$table_id ?>"
               onclick="return confirm('Delete this field?')"
               class="inline-flex items-center justify-center rounded-md p-1.5
                      text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
               aria-label="Delete" title="Delete">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/>
              </svg>
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="pt-3 mt-1 border-t border-gray-100 flex items-center justify-end">
        <button type="button" data-close-add
                class="px-3 py-1.5 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
          Cancel
        </button>
      </div>
    </form>
  </div>
  </div>
        </div>



   <!-- THEAD -->
<div class="universal-table" id="applicants-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
  <form action="<?= $CATEGORY_URL ?>/edit_thead.php" method="post" class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">
    <input type="hidden" name="id" value="<?= (int)($headRow['id'] ?? 0) ?>">
    <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">

    <div class="app-grid text-xs gap-2 font-semibold text-black uppercase" style="--cols: <?= (int)$totalCols ?>;">
      <div class="p-2"><input name="name"            value="<?= htmlspecialchars($headRow['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"            placeholder="Name"            class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="stage"           value="<?= htmlspecialchars($headRow['stage'] ?? '', ENT_QUOTES, 'UTF-8') ?>"           placeholder="Stage"           class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="applying_for"    value="<?= htmlspecialchars($headRow['applying_for'] ?? '', ENT_QUOTES, 'UTF-8') ?>"    placeholder="Applying For"    class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="attachment"      value="<?= htmlspecialchars($headRow['attachment'] ?? '', ENT_QUOTES, 'UTF-8') ?>"      placeholder="Attachment"      class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="email_address"   value="<?= htmlspecialchars($headRow['email_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"   placeholder="Email Address"   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="phone"           value="<?= htmlspecialchars($headRow['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"           placeholder="Phone"           class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="interview_date"  value="<?= htmlspecialchars($headRow['interview_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"  placeholder="Interview Date"  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="interviewer"     value="<?= htmlspecialchars($headRow['interviewer'] ?? '', ENT_QUOTES, 'UTF-8') ?>"     placeholder="Interviewer"     class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="interview_score" value="<?= htmlspecialchars($headRow['interview_score'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Interview Score" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="notes"           value="<?= htmlspecialchars($headRow['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"           placeholder="Notes"           class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>

      <?php foreach ($fields as $field): ?>
        <div class="p-2"><!-- normalized spacing -->
          <input type="text" name="extra_field_<?= (int)$field['id'] ?>" value="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Field" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
      <?php endforeach; ?>

      <?php if ($hasAction): ?><div class="p-2"></div><?php endif; ?>
    </div>
  </form>
</div>

<!-- TBODY -->
<div class="w-full divide-y divide-gray-200">
  <?php if ($hasRecord): foreach ($rows as $r): ?>
    <form method="POST" action="/ItemPilot/categories/Applicants Table/edit_tbody.php?id=<?= (int)$r['id'] ?>"
          enctype="multipart/form-data"
          class="applicant-row border-b border-gray-200 hover:bg-gray-50 text-sm"
          style="--cols: <?= (int)$totalCols ?>;">

      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="p-2 text-gray-600" data-col="name">
        <input type="text" name="name" value="<?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </div>

      <?php
      $statusColors = [
        'No hire'         => 'bg-red-100 text-red-800',
        'Interviewing'    => 'bg-yellow-100 text-yellow-800',
        'Hire'            => 'bg-green-100 text-green-800',
        'Decision needed' => 'bg-gray-100 text-gray-800',
      ];
      $colorClass = $statusColors[$r['stage'] ?? ''] ?? 'bg-white text-gray-900';
      ?>
      <div class="p-2 text-gray-600 text-xs font-semibold" data-col="stage">
        <select data-autosave="1" name="stage" style="appearance:none;"
                class="w-full px-2 py-1 rounded-xl <?= $colorClass ?>">
          <option value="No hire"         <?= ($r['stage'] ?? '') === 'No hire' ? 'selected' : '' ?>>No hire</option>
          <option value="Interviewing"    <?= ($r['stage'] ?? '') === 'Interviewing' ? 'selected' : '' ?>>Interviewing</option>
          <option value="Hire"            <?= ($r['stage'] ?? '') === 'Hire' ? 'selected' : '' ?>>Hire</option>
          <option value="Decision needed" <?= ($r['stage'] ?? '') === 'Decision needed' ? 'selected' : '' ?>>Decision needed</option>
        </select>
      </div>


      <div class="p-2 text-gray-600" data-col="applying_for">
        <input type="text" name="applying_for" value="<?= htmlspecialchars($r['applying_for'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </div>

      <div class="p-2 text-gray-600 text-xs font-semibold" data-col="attachment">
        <?php if (!empty($r['attachment'])): ?>
          <img src="<?= $CATEGORY_URL ?>/uploads/<?= htmlspecialchars($r['attachment'], ENT_QUOTES, 'UTF-8') ?>"
               class="thumb"
               alt="<?= htmlspecialchars($r['name'] ?? 'Attachment', ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
          <span class="italic text-gray-400 ml-[5px]">📎 None</span>
        <?php endif; ?>
      </div>

      <div class="p-2 text-gray-600" data-col="email_address">
        <input type="text" name="email_address" value="<?= htmlspecialchars($r['email_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </div>

      <div class="p-2 text-gray-600" data-col="phone">
        <input type="text" name="phone" value="<?= htmlspecialchars($r['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </div>

      <div class="p-2 text-gray-600" data-col="interview_date">
        <input type="text" name="interview_date" value="<?= htmlspecialchars($r['interview_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </div>

      <div class="p-2 text-gray-600" data-col="interviewer">
        <input type="text" name="interviewer" value="<?= htmlspecialchars($r['interviewer'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </div>

      <?php
      $scoreColors = [
        'Failed'              => 'bg-red-100 text-red-800',
        'Probably no hire'    => 'bg-yellow-100 text-yellow-800',
        'Worth consideration' => 'bg-blue-100 text-blue-800',
        'Good candidate'      => 'bg-green-100 text-green-800',
        'Hire this person'    => 'bg-gray-100 text-gray-800',
      ];
      $scoreClass = $scoreColors[$r['interview_score'] ?? ''] ?? 'bg-white text-gray-900';
      ?>
      <div class="p-2 text-gray-600 text-xs font-semibold" data-col="interview_score">
        <select data-autosave="1" name="interview_score" style="appearance:none;"
                class="w-full px-2 py-1 rounded-xl <?= $scoreClass ?>">
          <option value="Failed"               <?= ($r['interview_score'] ?? '') === 'Failed' ? 'selected' : '' ?>>Failed</option>
          <option value="Probably no hire"     <?= ($r['interview_score'] ?? '') === 'Probably no hire' ? 'selected' : '' ?>>Probably no hire</option>
          <option value="Worth consideration"  <?= ($r['interview_score'] ?? '') === 'Worth consideration' ? 'selected' : '' ?>>Worth consideration</option>
          <option value="Good candidate"       <?= ($r['interview_score'] ?? '') === 'Good candidate' ? 'selected' : '' ?>>Good candidate</option>
          <option value="Hire this person"     <?= ($r['interview_score'] ?? '') === 'Hire this person' ? 'selected' : '' ?>>Hire this person</option>
        </select>
      </div>
 
      <div class="p-2 text-gray-600" data-col="notes">
        <input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </div>

      <!-- Dynamic value inputs -->
      <div class="p-2 text-gray-600" data-col="dyn">
        <?php
          $row_id   = (int)$r['id'];
          $user_id  = (int)($_SESSION['user_id'] ?? 0);
          $table_id = (int)$table_id;

          // 1) field metadata
          $stmt = $conn->prepare("SELECT id, field_name FROM applicants_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC");
          $stmt->bind_param('ii', $user_id, $table_id);
          $stmt->execute();
          $fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
          $stmt->close();
          $colRes    = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants_base'");
          $validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
          $dynFields = array_values(array_filter($fields, function($m) use ($validCols) {
            return in_array($m['field_name'], $validCols, true);
          }));

          $baseRow = [];
          $stmt = $conn->prepare("SELECT * FROM applicants_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
          $stmt->bind_param('iii', $table_id, $user_id, $row_id);
          $stmt->execute();
          $baseRow = $stmt->get_result()->fetch_assoc() ?: [];
          $stmt->close();
        ?>

        <?php foreach ($dynFields as $meta): $col = $meta['field_name']; ?>
          <input type="text" name="dyn[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($baseRow[$col] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        <?php endforeach; ?>
      </div>

      <div class="p-2">
        <a href="<?= $CATEGORY_URL ?>/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>"
           onclick="return confirm('Are you sure?')"
           class="icon-btn" aria-label="Delete row">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6m2 4H7l1 12h8l1-12z" />
          </svg>
        </a>
      </div>
    </form>
  <?php endforeach; else: ?>
    <div class="px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300">No records found.</div>
  <?php endif; ?>
</div>


    <?php if ($totalPages > 1): ?>
      <div class="pagination applicants my-2 flex justify-start md:justify-center space-x-2">
        <?php if ($page > 1): ?>
          <a href="insert_applicant.php?page=<?= $page-1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">« Prev</a>
        <?php endif; ?>
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
          <a href="insert_applicant.php?page=<?= $i ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded transition <?= $i===$page ? 'bg-blue-600 text-white border-blue-600 font-semibold' : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="insert_applicant.php?page=<?= $page+1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">Next »</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</header>

<!-- Add New Record modal -->
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

    <form action="<?= $CATEGORY_URL ?>/insert_applicant.php" method="POST" enctype="multipart/form-data" class="new-record-form space-y-6">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <h1 class="w-full px-4 py-2 text-center text-2xl"><?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?></h1>

      <div class="mt-5">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['name'] ?? 'Name') ?></label>
        <input type="text" name="name" placeholder="<?= htmlspecialchars($headRow['name'] ?? 'Name') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['stage'] ?? 'Stage') ?></label>
        <select name="stage" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="No hire">No hire</option>
          <option value="Interviewing">Interviewing</option>
          <option value="Hire">Hire</option>
          <option value="Decision needed">Decision needed</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['applying_for'] ?? 'Applying For') ?></label>
        <input type="text" name="applying_for" placeholder="<?= htmlspecialchars($headRow['applying_for'] ?? 'Applying For') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['attachment'] ?? 'Attachment') ?></label>
        <input id="attachment" type="file" name="attachment" accept="image/*" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['email_address'] ?? 'Email Address') ?></label>
        <input type="text" name="email_address" placeholder="<?= htmlspecialchars($headRow['email_address'] ?? 'Email Address') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['phone'] ?? 'Phone') ?></label>
        <input type="text" name="phone" placeholder="<?= htmlspecialchars($headRow['phone'] ?? 'Phone') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['interview_date'] ?? 'Interview Date') ?></label>
        <input type="text" name="interview_date" placeholder="<?= htmlspecialchars($headRow['interview_date'] ?? 'Interview Date') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['interviewer'] ?? 'Interviewer') ?></label>
        <input type="text" name="interviewer" placeholder="<?= htmlspecialchars($headRow['interviewer'] ?? 'Interviewer') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['interview_score'] ?? 'Interview Score') ?></label>
        <select name="interview_score" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="Failed">Failed</option>
          <option value="Probably no hire">Probably no hire</option>
          <option value="Worth consideration">Worth consideration</option>
          <option value="Good candidate">Good candidate</option>
          <option value="Hire this person">Hire this person</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['notes'] ?? 'Notes') ?></label>
        <input type="text" name="notes" placeholder="<?= htmlspecialchars($headRow['notes'] ?? 'Notes') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <?php if ($fields): ?>
        <!-- Dynamic create form inputs -->
        <?php foreach ($fields as $field): ?>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" name="extra_field_<?= (int)$field['id'] ?>" placeholder="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div>
        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">Create New Record</button>
      </div>
    </form>
  </div>
</div>

<style>.custom-select { appearance: none; }</style>
</body>
</html>
