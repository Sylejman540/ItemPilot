<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0) { header("Location: register/login.php"); exit; }

$CATEGORY_URL = '/ItemPilot/categories/Applicants%20Table';
$UPLOAD_DIR   = __DIR__ . '/uploads/';

$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

/* ---------------------------
   Resolve current table_id
----------------------------*/
if ($action === 'create_blank') {
  $stmt = $conn->prepare("INSERT INTO applicants_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $table_id = (int)$conn->insert_id;
  $stmt->close();
  $_SESSION['current_applicants_table_id'] = $table_id;

} elseif ($table_id > 0) {
  $_SESSION['current_applicants_table_id'] = $table_id;

} else {
  $table_id = (int)($_SESSION['current_applicants_table_id'] ?? 0);

  if ($table_id <= 0) {
    $q = $conn->prepare("SELECT table_id FROM applicants_table WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute(); $q->bind_result($latestId); $q->fetch(); $q->close();
    $table_id = (int)$latestId;
  }
  if ($table_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO applicants_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$conn->insert_id;
    $stmt->close();
  }

  $_SESSION['current_applicants_table_id'] = $table_id;
}

/* ---------------------------
   Helpers for dynamic fields
----------------------------*/
// Fetch metadata fields (name list for this table)
function fetch_applicant_fields(mysqli $conn, int $uid, int $table_id): array {
  $sql = "SELECT id, field_name FROM applicants_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ii', $uid, $table_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  return $res ?: [];
}
// Valid columns in applicants_base
function base_valid_columns(mysqli $conn): array {
  $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants_base'");
  return $colRes ? array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME') : [];
}

/* ---------------------------
   Create / Update row (POST)
----------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id              = $_POST['id'] ?? '';
  $name            = trim($_POST['name'] ?? '');
  $stage           = trim($_POST['stage'] ?? '');
  $applying_for    = trim($_POST['applying_for'] ?? '');
  $email_address   = trim($_POST['email_address'] ?? '');
  $phone           = trim($_POST['phone'] ?? '');
  $interview_date  = trim($_POST['interview_date'] ?? ''); // expect 'YYYY-MM-DD' or ''
  $interviewer     = trim($_POST['interviewer'] ?? '');
  $interview_score = trim($_POST['interview_score'] ?? '');
  $notes           = trim($_POST['notes'] ?? '');

  // Keep existing attachment unless a new one is uploaded
  $attachment = $_POST['existing_attachment'] ?? '';

  if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
      die("Could not create uploads directory.");
    }
    $tmp  = $_FILES['attachment']['tmp_name'];
    $orig = basename($_FILES['attachment']['name']);
    $dest = $UPLOAD_DIR . $orig;
    if (!move_uploaded_file($tmp, $dest)) {
      die("Failed to save uploaded file.");
    }
    $attachment = $orig;
  }

  $interview_date_db = ($interview_date === '') ? null : $interview_date;

  // ---- NEW: collect dynamic field inputs ----
  $dynIn = [];
  $metaFields = fetch_applicant_fields($conn, (int)$uid, (int)$table_id);
  foreach ($metaFields as $m) {
    $key = 'extra_field_' . (int)$m['id'];
    if (array_key_exists($key, $_POST)) {
      $val = $_POST[$key];
      $dynIn[$m['field_name']] = ($val === '') ? null : $val;
    }
  }
  // Intersect with real columns
  $validCols = base_valid_columns($conn);
  $exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
  $editable  = array_values(array_diff($validCols, $exclude));

  $toSave = [];
  foreach ($dynIn as $k => $v) {
    if (in_array($k, $editable, true)) {
      $toSave[$k] = $v; // null OK
    }
  }

  if ($id === '' || $id === null) {
    // INSERT applicants
    $stmt = $conn->prepare("
      INSERT INTO applicants
        (user_id, table_id, name, stage, applying_for, attachment, email_address, phone,
         interview_date, interviewer, interview_score, notes, created_at)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
      'iissssssssss',
      $uid, $table_id, $name, $stage, $applying_for, $attachment, $email_address, $phone,
      $interview_date_db, $interviewer, $interview_score, $notes
    );
    $stmt->execute();
    $row_id = (int)$stmt->insert_id;
    $stmt->close();

    // INSERT applicants_base
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
      // create an empty base row so later updates can find it
      $insb = $conn->prepare("INSERT INTO applicants_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
      $insb->bind_param('iii', $table_id, $uid, $row_id);
      $insb->execute();
      $insb->close();
    }

  } else {
    // UPDATE applicants
    $stmt = $conn->prepare("
      UPDATE applicants
         SET name            = ?,
             stage           = ?,
             applying_for    = ?,
             attachment      = ?,
             email_address   = ?,
             phone           = ?,
             interview_date  = ?,
             interviewer     = ?,
             interview_score = ?,
             notes           = ?
       WHERE id = ? AND table_id = ? AND user_id = ?
    ");
    $stmt->bind_param(
      'ssssssssssiii',
      $name, $stage, $applying_for, $attachment, $email_address, $phone,
      $interview_date_db, $interviewer, $interview_score, $notes,
      $id, $table_id, $uid
    );
    $stmt->execute();
    $stmt->close();

    // UPSERT applicants_base
    // ensure a base row exists
    $chk = $conn->prepare("SELECT id FROM applicants_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
    $chk->bind_param('iii', $table_id, $uid, $id);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$exists) {
      $insb = $conn->prepare("INSERT INTO applicants_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
      $insb->bind_param('iii', $table_id, $uid, $id);
      $insb->execute();
      $insb->close();
    }

    if ($toSave) {
      $set   = [];
      $vals  = [];
      $types = '';
      foreach ($toSave as $col => $val) {
        if ($val === null) { $set[] = "`$col`=NULL"; }
        else { $set[] = "`$col`=?"; $vals[] = $val; $types .= 's'; }
      }
      if (in_array('updated_at', $validCols, true)) { $set[] = "`updated_at`=NOW()"; }
      if ($set) {
        $sql = "UPDATE applicants_base SET " . implode(', ', $set) . " WHERE table_id=? AND user_id=? AND row_id=?";
        $types .= 'iii';
        $vals[] = $table_id; $vals[] = $uid; $vals[] = (int)$id;

        $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
        $stmt = $conn->prepare($sql);
        call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($vals)));
        $stmt->execute();
        $stmt->close();
      }
    }
  }

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
$fields    = fetch_applicant_fields($conn, (int)$uid, (int)$table_id);
$validCols = base_valid_columns($conn);
$dynFields = array_values(array_filter($fields, function($m) use ($validCols) {
  return in_array($m['field_name'], $validCols, true);
}));

// Column math for CSS grid (10 fixed + dynamic + actions)
$fixedCount = 10; // the fixed applicants columns we render below
$hasAction  = true;
$totalCols  = $fixedCount + count($dynFields) + ($hasAction ? 1 : 0);

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
    <form action="<?= $CATEGORY_URL ?>/edit.php" method="POST" class="flex gap-2">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="text" name="table_title" value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>" class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" placeholder="Untitled Applicants Table"/>
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

    <!-- same id -->
    <div id="actionMenuList" class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[min(92vw,28rem)] rounded-xl bg-white shadow-2xl ring-1 ring-gray-900/5 p-0 overflow-hidden">

      <!-- header (tighter) -->
      <div class="flex items-start justify-between px-4 pt-3 pb-2 border-b border-gray-100">
        <div>
          <h3 id="moreTitle" class="text-sm font-semibold text-gray-900">More</h3>
          <p class="mt-0.5 text-[11px] text-gray-500">Table actions</p>
        </div>
        <button data-close-add class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/>
          </svg>
        </button>
      </div>

      <div class="p-3 space-y-3">
        <div id="addColumnBtn" class="cursor-pointer group md:flex items-start gap-2 p-2 rounded-lg border border-transparent hover:bg-blue-50/40 hover:border-blue-100 transition">
          <div class="mt-0.5 shrink-0 grid place-items-center w-7 h-7 rounded-full bg-blue-100 text-blue-700">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6Z"/>
            </svg>
          </div>

          <div class="flex-1">
            <h4 class="text-[13px] font-medium text-gray-900 md:mt-0 mt-1">Add fields</h4>
            <p class="text-[11px] leading-4 text-gray-500 md:mt-0 mt-1">Create a new column with type and default value.</p>
          </div>

          <button id="addFieldsBtn" class="self-center px-2.5 py-1 text-[13px] font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 md:mt-0 mt-1">Add</button>
        </div>

        <div class="h-px bg-gray-100"></div>

        <p class="px-0.5 text-[10px] font-semibold tracking-wide text-red-600/80 uppercase">Danger</p>

        <div id="addDeleteBtn" class="cursor-pointer group md:flex items-start gap-2 p-2 rounded-lg border border-transparent hover:bg-red-50/40 hover:border-red-100 transition">
          <div class="mt-0.5 shrink-0 grid place-items-center w-7 h-7 rounded-full bg-red-100 text-red-600">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-3 6h12l-1 10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 9Zm4 2v8h2v-8h-2Zm4 0v8h2v-8h-2Z"/>
            </svg>
          </div>

          <div class="flex-1">
            <h4 class="md:mt-0 mt-1 text-[13px] font-medium text-gray-900">Delete fields</h4>
            <p class="md:mt-0 mt-1 text-[11px] leading-4 text-gray-500">Remove selected fields from this table.<span class="text-gray-700 font-medium">This can’t be undone.</span></p>
          </div>

          <button id="deleteFieldsBtn" class="md:mt-0 mt-1 self-center px-2.5 py-1 text-[13px] font-medium rounded-md bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">Delete
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

        <form action="/ItemPilot/categories/Applicants%20Table/add_fields.php" method="post">
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
        <form action="/ItemPilot/categories/Applicants%20Table/delete_fields.php" method="post" class="mt-3">
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

          <div class="divide-y divide-gray-100">
            <?php foreach ($fields as $field): ?>
              <div class="flex items-center justify-between">
                <input type="text" readonly name="extra_field_<?= (int)$field['id'] ?>" value="<?= htmlspecialchars($field['field_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent px-1 py-1 text-base text-gray-900"/>

                <a href="/ItemPilot/categories/Applicants%20Table/delete_fields.php?id=<?= (int)$field['id'] ?>&table_id=<?= (int)$table_id ?>" onclick="return confirm('Delete this field?')" class="inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="Delete" title="Delete">
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

    <form action="<?= $CATEGORY_URL ?>/insert_applicant.php" method="POST" enctype="multipart/form-data" class="space-y-6">
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
