<?php
// /ItemPilot/categories/Dresses/insert_dresses.php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { header("Location: /ItemPilot/register/login.php"); exit; }

/* ---------- Paths ---------- */
$CATEGORY_URL = '/ItemPilot/categories/Dresses';
$UPLOAD_DIR   = __DIR__ . '/uploads/';
$UPLOAD_URL   = $CATEGORY_URL . '/uploads';

/* ---------- Resolve table_id ---------- */
$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($action === 'create_blank') {
  $stmt = $conn->prepare("INSERT INTO dresses_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $table_id = (int)$conn->insert_id;
  $stmt->close();
  $_SESSION['current_sales_table_id'] = $table_id;
} elseif ($table_id > 0) {
  $_SESSION['current_sales_table_id'] = $table_id;
} else {
  $table_id = (int)($_SESSION['current_sales_table_id'] ?? 0);
  if ($table_id <= 0) {
    $q = $conn->prepare("SELECT table_id FROM dresses_table WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute(); $q->bind_result($latestId); $q->fetch(); $q->close();
    $table_id = (int)$latestId;
  }
  if ($table_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO dresses_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$conn->insert_id;
    $stmt->close();
  }
  $_SESSION['current_sales_table_id'] = $table_id;
}

/* ---------------------------
   Create / Update (POST)
----------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id                 = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
  $table_id           = (int)($_POST['table_id'] ?? $table_id);

  $linked_initiatives = trim($_POST['linked_initiatives'] ?? '');
  $notes              = trim($_POST['notes'] ?? '');
  $executive_sponsor  = trim($_POST['executive_sponsor'] ?? '');
  $status             = trim($_POST['status'] ?? '');
  $complete           = trim($_POST['complete'] ?? '');
  $priority           = trim($_POST['priority'] ?? ''); // Price
  $owner              = trim($_POST['owner'] ?? '');    // Material cost
  $attachment         = trim($_POST['existing_attachment'] ?? '');

  if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
      http_response_code(500); exit('Could not create uploads directory.');
    }
    $tmp  = $_FILES['attachment']['tmp_name'];
    $orig = basename($_FILES['attachment']['name']);
    if (!move_uploaded_file($tmp, $UPLOAD_DIR . $orig)) {
      http_response_code(500); exit('Failed to save uploaded file.');
    }
    $attachment = $orig;
  }

  // Profit = Price - Material cost
  $parse_money = static function ($s) {
    if ($s === '' || $s === null) return null;
    if (preg_match('/-?\d+(?:[.,]\d+)?/', (string)$s, $m)) return (float)str_replace(',', '.', $m[0]);
    return null;
  };
  $priceNum    = $parse_money($priority);
  $costNum     = $parse_money($owner);
  $deadlineNum = (is_null($priceNum) && is_null($costNum)) ? null : round((float)$priceNum - (float)$costNum, 2);
  $deadlineDb  = is_null($deadlineNum) ? null : number_format($deadlineNum, 2, '.', '');

  // Gather dynamic values from either dyn[col] or extra_field_ID
  $dynIn = $_POST['dyn'] ?? [];
  $map   = $conn->prepare("SELECT id, field_name FROM dresses_fields WHERE user_id=? AND table_id=? ORDER BY id ASC");
  $map->bind_param('ii', $uid, $table_id);
  $map->execute();
  $rs = $map->get_result();
  while ($m = $rs->fetch_assoc()) {
    $k = 'extra_field_'.(int)$m['id'];
    if (array_key_exists($k, $_POST)) {
      $dynIn[$m['field_name']] = ($_POST[$k] === '') ? null : $_POST[$k];
    }
  }
  $map->close();

  // Whitelist against dresses_base
  $colsRes  = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dresses_base'");
  $validCols= $colsRes ? array_column($colsRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME') : [];
  $exclude  = ['id','user_id','table_id','row_id','created_at','updated_at'];
  $editable = array_values(array_diff($validCols, $exclude));

  $toSave = [];
  foreach ($dynIn as $k => $v) {
    if (in_array($k, $editable, true)) $toSave[$k] = ($v === '') ? null : $v;
  }

  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $conn->begin_transaction();
  try {
    if ($id <= 0) {
      $stmt = $conn->prepare("
        INSERT INTO dresses
          (linked_initiatives, notes, executive_sponsor, status, complete,
           priority, owner, deadline, attachment, table_id, user_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
      ");
      $stmt->bind_param(
        'sssssssssii',
        $linked_initiatives, $notes, $executive_sponsor, $status, $complete,
        $priority, $owner, $deadlineDb, $attachment, $table_id, $uid
      );
      $stmt->execute();
      $row_id = (int)$stmt->insert_id;
      $stmt->close();

      if ($toSave) {
        $cols  = array_keys($toSave);
        $place = array_fill(0, count($cols), '?');
        $sql   = "INSERT INTO dresses_base (`table_id`,`user_id`,`row_id`,`".implode("`,`",$cols)."`) VALUES (?,?,?,".implode(',',$place).")";
        $stmt  = $conn->prepare($sql);
        $types = 'iii'.str_repeat('s', count($cols));
        $params = [$table_id, $uid, $row_id];
        foreach ($cols as $c) $params[] = $toSave[$c];
        $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
        call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($params)));
        $stmt->execute(); $stmt->close();
      } else {
        $insb = $conn->prepare("INSERT INTO dresses_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
        $insb->bind_param('iii', $table_id, $uid, $row_id);
        $insb->execute(); $insb->close();
      }

    } else {
      $stmt = $conn->prepare("
        UPDATE dresses
           SET linked_initiatives=?, notes=?, executive_sponsor=?, status=?, complete=?,
               priority=?, owner=?, deadline=?, attachment=?
         WHERE id=? AND table_id=? AND user_id=?
      ");
      $stmt->bind_param(
        'sssssssssiii',
        $linked_initiatives, $notes, $executive_sponsor, $status, $complete,
        $priority, $owner, $deadlineDb, $attachment, $id, $table_id, $uid
      );
      $stmt->execute(); $stmt->close();

      $chk = $conn->prepare("SELECT id FROM dresses_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
      $chk->bind_param('iii', $table_id, $uid, $id);
      $chk->execute(); $base = $chk->get_result()->fetch_assoc(); $chk->close();
      if (!$base) {
        $insb = $conn->prepare("INSERT INTO dresses_base (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
        $insb->bind_param('iii', $table_id, $uid, $id);
        $insb->execute(); $insb->close();
      }
      if ($toSave) {
        $set=[]; $vals=[]; $types='';
        foreach ($toSave as $c=>$v) {
          if ($v === null) $set[]="`$c`=NULL"; else { $set[]="`$c`=?"; $vals[]=$v; $types.='s'; }
        }
        if (in_array('updated_at',$validCols,true)) $set[]="`updated_at`=NOW()";
        if ($set) {
          $sql = "UPDATE dresses_base SET ".implode(', ',$set)." WHERE table_id=? AND user_id=? AND row_id=?";
          $types.='iii'; $vals[]=$table_id; $vals[]=$uid; $vals[]=$id;
          $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
          $stmt=$conn->prepare($sql);
          call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($vals)));
          $stmt->execute(); $stmt->close();
        }
      }
    }

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500); exit('Save failed: '.$e->getMessage());
  }

  header("Location: /ItemPilot/home.php?autoload=1&type=dresses&table_id={$table_id}");
  exit;
}

/* ---------------------------
   Pagination + data fetch
----------------------------*/
$limit  = 10;
$page   = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM dresses WHERE user_id=? AND table_id=?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute(); $countStmt->bind_result($totalRows); $countStmt->fetch(); $countStmt->close();
$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("
  SELECT id, linked_initiatives, notes, executive_sponsor, status, complete, priority, owner, deadline, attachment
  FROM dresses
  WHERE user_id = ? AND table_id = ?
  ORDER BY
    CASE
      WHEN STR_TO_DATE(notes, '%e.%c.%Y') IS NULL AND STR_TO_DATE(notes, '%d.%m.%Y') IS NULL THEN 1
      ELSE 0
    END,
    COALESCE(
      STR_TO_DATE(notes, '%e.%c.%Y'),
      STR_TO_DATE(notes, '%d.%m.%Y')
    ) ASC,
    id ASC
  LIMIT ? OFFSET ?
");
$dataStmt->bind_param('iiii', $uid, $table_id, $limit, $offset);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();
$hasRecord = count($rows) > 0;

/* ---------------------------
   Title + THEAD labels
----------------------------*/
$tit = $conn->prepare("SELECT table_title FROM dresses_table WHERE user_id=? AND table_id=? LIMIT 1");
$tit->bind_param('ii', $uid, $table_id);
$tit->execute(); $titleRes = $tit->get_result()->fetch_assoc(); $tit->close();
$tableTitle = $titleRes['table_title'] ?? 'Untitled Dresses Table';

$theadFetch = $conn->prepare("
  SELECT id, linked_initiatives, executive_sponsor, status, complete, notes, priority, owner, deadline, attachment
    FROM dresses_thead
   WHERE user_id=? AND table_id=?
ORDER BY id DESC LIMIT 1
");
$theadFetch->bind_param('ii', $uid, $table_id);
$theadFetch->execute(); $rs = $theadFetch->get_result();
if ($rs && $rs->num_rows) {
  $headRow = $rs->fetch_assoc();
} else {
  $defaults = [
    'linked_initiatives' => 'Name',
    'executive_sponsor'  => 'Country',
    'status'             => 'Status',
    'complete'           => 'Age',
    'notes'              => 'Delivery date',
    'priority'           => 'Price',
    'owner'              => 'Material cost',
    'deadline'           => 'Profit',
    'attachment'         => 'Model',
  ];
  $ins = $conn->prepare("
    INSERT INTO dresses_thead
      (user_id, table_id, linked_initiatives, executive_sponsor, status, complete, notes, priority, owner, deadline, attachment)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
  ");
  $ins->bind_param(
    'iisssssssss', $uid, $table_id,
    $defaults['linked_initiatives'], $defaults['executive_sponsor'], $defaults['status'],
    $defaults['complete'], $defaults['notes'], $defaults['priority'],
    $defaults['owner'], $defaults['deadline'], $defaults['attachment']
  );
  $ins->execute(); $newId = (int)$conn->insert_id; $ins->close();
  $headRow = ['id'=>$newId] + $defaults;
}
$theadFetch->close();

/* ---------------------------
   Dynamic fields metadata
----------------------------*/
$fieldsStmt = $conn->prepare("SELECT id, field_name FROM dresses_fields WHERE user_id=? AND table_id=? ORDER BY id ASC");
$fieldsStmt->bind_param('ii', $uid, $table_id);
$fieldsStmt->execute(); $fields = $fieldsStmt->get_result()->fetch_all(MYSQLI_ASSOC); $fieldsStmt->close();

$colRes    = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='dresses_base'");
$validCols = $colRes ? array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME') : [];
$dynCount  = 0;
foreach ($fields as $f) if (in_array($f['field_name'], $validCols, true)) $dynCount++;

/* Fixed columns in grid: 9 (name, delivery, country, status, age, price, material, profit, model) */
$fixedCount = 9;
$hasAction  = true;
$totalCols  = $fixedCount + $dynCount + ($hasAction ? 1 : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dresses</title>
</head>
<body>
<header id="appHeader" class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out" style="padding-left:1.25rem;padding-right:1.25rem;">
  <section class="flex mt-6 justify-between ml-3">
    <form action="<?= $CATEGORY_URL ?>/edit.php" method="POST" class="flex gap-2">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="text" name="table_title" value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>"
             class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"
             placeholder="Untitled dresses table"/>
    </form>

    <button id="addIcon" type="button"
            class="flex items-center gap-1 bg-blue-600 hover:bg-blue-700 py-[10px] cursor-pointer px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">New Record</span>
    </button>
  </section>

  <main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden">
    <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-[90rem]">
 <div class="flex justify-between">
    <div>
      <input id="rowSearchS" type="search" placeholder="Search rows…" data-rows=".sales-row" data-count="#countS" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-72"/>
      <span id="countS" class="ml-2 text-xs text-gray-600"></span>
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
    <form action="/ItemPilot/categories/Dresses/add_fields.php" method="post" class="space-y-3">
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
  <div id="addDeletePop"
     class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2
            w-[min(92vw,32rem)] rounded-2xl bg-white/95 backdrop-blur
            shadow-2xl ring-1 ring-black/5">
  <!-- Header -->
  <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
    <div class="flex items-center justify-between gap-3">
      <h3 class="text-sm font-semibold text-gray-900">Delete fields</h3>
      <button data-close-add type="button" id="closeAddColumnPop"
              class="p-1.5 rounded-md hover:bg-gray-100 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              aria-label="Close">
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
    <form action="/ItemPilot/categories/Dresses/delete_fields.php" method="post" class="space-y-3">
      <input type="hidden" name="table_id" value="<?= (int)($table_id ?? 0) ?>">

      <?php
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $table_id = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);
        $sql = "SELECT id, field_name FROM dresses_fields WHERE user_id = ? AND table_id = ? ORDER BY id ASC";
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

            <a href="/ItemPilot/categories/Dresses/delete_fields.php?id=<?= (int)$field['id'] ?>&table_id=<?= (int)$table_id ?>"
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
<div class="universal-table" id="sales-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
  <form action="<?= $CATEGORY_URL ?>/edit_thead.php" method="post"
        class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">
    <input type="hidden" name="id" value="<?= (int)($headRow['id'] ?? 0) ?>">
    <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">

    <!-- unified header grid -->
    <div class="app-grid text-xs font-semibold text-black uppercase" style="--cols: <?= (int)$totalCols ?>;">
      <div class="p-2"><input name="linked_initiatives" value="<?= htmlspecialchars($headRow['linked_initiatives'] ?? 'Name', ENT_QUOTES) ?>"          placeholder="Name"          class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="notes"              value="<?= htmlspecialchars($headRow['notes'] ?? 'Delivery date', ENT_QUOTES) ?>"              placeholder="Delivery date" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="executive_sponsor"  value="<?= htmlspecialchars($headRow['executive_sponsor'] ?? 'Country', ENT_QUOTES) ?>"        placeholder="Country"       class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="status"             value="<?= htmlspecialchars($headRow['status'] ?? 'Status', ENT_QUOTES) ?>"                    placeholder="Status"        class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="complete"           value="<?= htmlspecialchars($headRow['complete'] ?? 'Age', ENT_QUOTES) ?>"                      placeholder="Age"           class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="priority"           value="<?= htmlspecialchars($headRow['priority'] ?? 'Price', ENT_QUOTES) ?>"                    placeholder="Price"         class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="owner"              value="<?= htmlspecialchars($headRow['owner'] ?? 'Material cost', ENT_QUOTES) ?>"               placeholder="Material cost" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="deadline"           value="<?= htmlspecialchars($headRow['deadline'] ?? 'Profit', ENT_QUOTES) ?>"                   placeholder="Profit"        class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
      <div class="p-2"><input name="attachment"         value="<?= htmlspecialchars($headRow['attachment'] ?? 'Model', ENT_QUOTES) ?>"                  placeholder="Model"         class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>

      <!-- Dynamic field labels -->
      <?php foreach ($fields as $f): ?>
        <div class="p-2">
          <input type="text"
                name="extra_field_<?= (int)$f['id'] ?>"
                value="<?= htmlspecialchars($f['field_name'] ?? '', ENT_QUOTES) ?>"
                placeholder="Field"
                class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
      <?php endforeach; ?>


      <?php if ($hasAction): ?><div class="p-2"></div><?php endif; ?>
    </div>
  </form>
</div>

<!-- TBODY -->
<div class="w-full divide-y divide-gray-200">
  <?php if ($hasRecord): foreach ($rows as $r): ?>
    <form method="POST"
          action="<?= $CATEGORY_URL ?>/insert_dresses.php"
          enctype="multipart/form-data"
          class="sales-row border-b border-gray-200 hover:bg-gray-50 text-sm"
          style="--cols: <?= (int)$totalCols ?>;"
          data-status="<?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES) ?>">

      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment'] ?? '', ENT_QUOTES) ?>">

      <div class="p-2 text-gray-600" data-col="linked_initiatives">
        <input type="text" name="linked_initiatives" value="<?= htmlspecialchars($r['linked_initiatives'] ?? '', ENT_QUOTES) ?>"
               class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 text-gray-600" data-col="notes">
        <input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES) ?>"
               class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 text-gray-600" data-col="executive_sponsor">
        <input type="text" name="executive_sponsor" value="<?= htmlspecialchars($r['executive_sponsor'] ?? '', ENT_QUOTES) ?>"
               class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <?php
      $statusColors = [
        'To Do'       => 'bg-red-100 text-red-800',
        'In Progress' => 'bg-yellow-100 text-yellow-800',
        'Done'        => 'bg-green-100 text-green-800',
      ];
      $colorClass = $statusColors[$r['status'] ?? ''] ?? 'bg-white text-gray-900';
      ?>
      <div class="p-2 text-gray-600 text-xs font-semibold" data-col="status">
        <select data-autosave="1" name="status" style="appearance:none;"
                class="w-full px-2 py-1 rounded-xl <?= $colorClass ?>">
          <option value="To Do"       <?= ($r['status'] ?? '') === 'To Do' ? 'selected' : '' ?>>To Do</option>
          <option value="In Progress" <?= ($r['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="Done"        <?= ($r['status'] ?? '') === 'Done' ? 'selected' : '' ?>>Done</option>
        </select>
      </div>


      <div class="p-2 text-gray-600" data-col="complete">
        <input type="text" name="complete" value="<?= htmlspecialchars($r['complete'] ?? '', ENT_QUOTES) ?>"
               class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 text-gray-600" data-col="priority">
        <input type="text" name="priority" value="<?= htmlspecialchars($r['priority'] ?? '', ENT_QUOTES) ?>"
               class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 text-gray-600" data-col="owner">
        <input type="text" name="owner" value="<?= htmlspecialchars($r['owner'] ?? '', ENT_QUOTES) ?>"
               class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="flex p-2 gap-1 text-gray-600 whitespace-normal break-words" data-col="deadline">
        <data class="py-2">&euro;</data>
        <input type="text" name="deadline" value="<?= htmlspecialchars($r['deadline'] ?? '', ENT_QUOTES) ?>" readonly
               class="w-full bg-transparent border-none py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="p-2 flex items-center gap-2" data-col="attachment">
        <?php if (!empty($r['attachment'])): ?>
          <img src="<?= $UPLOAD_URL . '/' . rawurlencode($r['attachment']) ?>" class="thumb"
               alt="<?= htmlspecialchars($r['linked_initiatives'] ?? 'Attachment', ENT_QUOTES) ?>">
        <?php else: ?>
          <span class="italic text-gray-400 ml-[5px]">📎 None</span>
        <?php endif; ?>
      </div>

      <!-- Dynamic values -->
      <?php
        $row_id  = (int)$r['id'];
        $baseRow = [];
        if ($table_id > 0 && $uid > 0 && $row_id > 0) {
          $stmt = $conn->prepare("SELECT * FROM dresses_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
          $stmt->bind_param('iii', $table_id, $uid, $row_id);
          $stmt->execute();
          $baseRow = $stmt->get_result()->fetch_assoc() ?: [];
          $stmt->close();
        }
        $dynFields = array_values(array_filter($fields, function($m) use ($validCols) {
          return in_array($m['field_name'], $validCols, true);
        }));
      ?>
      <div class="p-2 text-gray-600" data-col="dyn">
        <?php foreach ($dynFields as $meta): $col = $meta['field_name']; ?>
          <input type="text" name="dyn[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($baseRow[$col] ?? '', ENT_QUOTES) ?>"
                 class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        <?php endforeach; ?>
      </div>

      <!-- Action -->
      <?php if ($hasAction): ?>
        <div class="p-2">
          <a href="<?= $CATEGORY_URL ?>/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>"
             onclick="return confirm('Are you sure?')"
             class="icon-btn" aria-label="Delete row">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6m2 4H7l1 12h8l1-12z" />
            </svg>
          </a>
        </div>
      <?php endif; ?>
    </form>
  <?php endforeach; else: ?>
    <div class="px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300">No records found.</div>
  <?php endif; ?>
</div>

      <?php if ($totalPages > 1): ?>
        <div class="pagination my-2 flex justify-start md:justify-center space-x-2">
          <?php if ($page > 1): ?>
            <a href="insert_dresses.php?page=<?= $page-1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">« Prev</a>
          <?php endif; ?>
          <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <a href="insert_dresses.php?page=<?= $i ?>&table_id=<?= (int)$table_id ?>"
               class="px-3 py-1 border rounded transition <?= $i===$page ? 'bg-blue-600 text-white border-blue-600 font-semibold' : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="insert_dresses.php?page=<?= $page+1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">Next »</a>
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
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="6" y1="6" x2="18" y2="18" /><line x1="6" y1="18" x2="18" y2="6" /></svg>
      </a>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </div>

    <form action="<?= $CATEGORY_URL ?>/insert_dresses.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <h1 class="w-full px-4 py-2 text-center text-2xl"><?= htmlspecialchars($tableTitle, ENT_QUOTES) ?></h1>

      <div class="mt-5">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['linked_initiatives'] ?? 'Name', ENT_QUOTES) ?></label>
        <input type="text" name="linked_initiatives" placeholder="<?= htmlspecialchars($headRow['linked_initiatives'] ?? 'Name', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['notes'] ?? 'Delivery date', ENT_QUOTES) ?></label>
        <input type="text" name="notes" placeholder="<?= htmlspecialchars($headRow['notes'] ?? 'Delivery date', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['executive_sponsor'] ?? 'Country', ENT_QUOTES) ?></label>
        <input type="text" name="executive_sponsor" placeholder="<?= htmlspecialchars($headRow['executive_sponsor'] ?? 'Country', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['status'] ?? 'Status', ENT_QUOTES) ?></label>
        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="To Do">To Do</option>
          <option value="In Progress">In Progress</option>
          <option value="Done">Done</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['complete'] ?? 'Age', ENT_QUOTES) ?></label>
        <input type="text" name="complete" placeholder="<?= htmlspecialchars($headRow['complete'] ?? 'Age', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['priority'] ?? 'Price', ENT_QUOTES) ?></label>
        <input type="text" name="priority" placeholder="<?= htmlspecialchars($headRow['priority'] ?? 'Price', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['owner'] ?? 'Material cost', ENT_QUOTES) ?></label>
        <input type="text" name="owner" placeholder="<?= htmlspecialchars($headRow['owner'] ?? 'Material cost', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div class="hidden">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['deadline'] ?? 'Profit', ENT_QUOTES) ?></label>
        <input type="text" name="deadline" placeholder="<?= htmlspecialchars($headRow['deadline'] ?? 'Profit', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($headRow['attachment'] ?? 'Model', ENT_QUOTES) ?></label>
        <input type="file" name="attachment" accept="image/*" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
      </div>

      <?php if (!empty($fields)): ?>
        <?php foreach ($fields as $f): ?>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($f['field_name'] ?? '', ENT_QUOTES) ?></label>
            <input type="text" name="extra_field_<?= (int)$f['id'] ?>" placeholder="<?= htmlspecialchars($f['field_name'] ?? '', ENT_QUOTES) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div>
        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">Create New Record</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
