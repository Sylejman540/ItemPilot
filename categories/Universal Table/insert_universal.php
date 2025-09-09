<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0) { header("Location: register/login.php"); exit; }

/* ---------- Config ---------- */
$CATEGORY_URL = '/ItemPilot/categories/Universal%20Table';      // URL (encoded space)
$UPLOAD_DIR   = __DIR__ . '/uploads/';                          // FS path
$UPLOAD_URL   = $CATEGORY_URL . '/uploads';                     // URL path for images

/* ---------- Resolve table_id (session/URL/create) ---------- */
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

/* ---------- Create/Update record ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id        = $_POST['id'] ?? '';
  $name      = $_POST['name'] ?? '';
  $notes     = $_POST['notes'] ?? '';
  $assignee  = $_POST['assignee'] ?? '';
  $status    = $_POST['status'] ?? '';
  $attachment_summary = $_POST['existing_attachment'] ?? '';

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

  if ($id === '' || $id === null) {
    $stmt = $conn->prepare("
      INSERT INTO universal (name, notes, assignee, status, attachment_summary, table_id, user_id)
      VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->bind_param('ssssssi', $name, $notes, $assignee, $status, $attachment_summary, $table_id, $uid);
  } else {
    $stmt = $conn->prepare("
      UPDATE universal
         SET name = ?, notes = ?, assignee = ?, status = ?, attachment_summary = ?
       WHERE id = ? AND table_id = ? AND user_id = ?
    ");
    $stmt->bind_param('sssssiii', $name, $notes, $assignee, $status, $attachment_summary, $id, $table_id, $uid);
  }
  $stmt->execute(); $stmt->close();

  header("Location: /ItemPilot/home.php?autoload=1&type=universal&table_id={$table_id}");
  exit;
}

/* ---------- Title (tables) ---------- */
$stmt = $conn->prepare("SELECT table_title FROM `tables` WHERE user_id = ? AND table_id = ? LIMIT 1");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$tableTitle = $row['table_title'] ?? 'Untitled table';

/* ---------- Ensure THEAD exists for this table ---------- */
$stmt = $conn->prepare("
  SELECT id, table_id, thead_name, thead_notes, thead_assignee, thead_status, thead_attachment
    FROM universal_thead
   WHERE user_id = ? AND table_id = ?
ORDER BY id DESC
   LIMIT 1
");
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
  $ins = $conn->prepare("
    INSERT INTO universal_thead
      (user_id, table_id, thead_name, thead_notes, thead_assignee, thead_status, thead_attachment)
    VALUES (?,?,?,?,?,?,?)
  ");
  $ins->bind_param('iisssss', $uid, $table_id,
    $thead['thead_name'], $thead['thead_notes'], $thead['thead_assignee'],
    $thead['thead_status'], $thead['thead_attachment']
  );
  $ins->execute(); $ins->close();
}
$stmt->close();

/* ---------- Pagination + data ---------- */
$limit  = 10;
$page   = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM universal WHERE user_id = ? AND table_id = ?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute(); $countStmt->bind_result($totalRows); $countStmt->fetch(); $countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("
  SELECT id, name, notes, assignee, status, attachment_summary
    FROM universal
   WHERE user_id = ? AND table_id = ?
ORDER BY id ASC
   LIMIT ? OFFSET ?
");
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
      <input type="text" name="table_title"
             value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>"
             class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"
             placeholder="Untitled table"/>
    </form>

    <button id="addIcon" type="button"
            class="flex items-center gap-1 bg-blue-800 py-[10px] cursor-pointer hover:bg-blue-700 px-2 rounded-lg text-white">
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
      <input id="rowSearchU" type="search" placeholder="Search rows…" data-rows=".universal-row" data-count="#countU" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-72 md:w-96"/>
      <span id="countU" class="ml-2 text-xs text-gray-600"></span>
    </div>

    <!-- Ghost header cell -->
    <button id="addColumnBtn" class="ml-2 px-3 text-xs rounded-lg border border-dashed border-gray-300 text-gray-600 hover:border-gray-400 hover:text-gray-800">+ Add fields</button>

    <!-- Popover -->
    <div id="addColumnPop" class="hidden fixed z-[70] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-80 rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
      <div class="flex items-center gap-2">
        <!-- icon -->
        <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
        <h3 class="text-sm font-semibold text-gray-900">Add new field</h3>
      </div>
      <button data-close-add type="button" class="cursor-pointer p-1 rounded-md hover:bg-gray-100" aria-label="Close" id="closeAddColumnPop">
        <svg class="h-5 w-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <form action="/ItemPilot/categories/Universal Table/add_fields.php" method="post" class="p-4">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">

      <!-- Name -->
      <label for="column_name" class="block text-xs font-medium text-gray-700">Field name</label>
      <div class="relative mt-1">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
        <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7H8m8 5H8m8 5H8"/>
        </svg>
        </span>
        <input id="column_name" name="column_name" type="text" placeholder="e.g. Priority, Owner, ETA" class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required/>
      </div>
      <p class="mt-1 text-[11px] text-gray-500">Use a short, clear label.</p>

      <!-- Optional: Type selector (send as column_type if your PHP handles it) -->
      <div class="mt-3">
        <label for="column_type" class="block text-xs font-medium text-gray-700">Field type</label>
        <select id="column_type" name="column_type" class="mt-1 w-full rounded-lg border border-gray-300 py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="text">Text</option>
          <option value="number">Number</option>
          <option value="date">Date</option>
          <option value="select">Dropdown</option>
          <option value="checkbox">Checkbox</option>
        </select>
        <p class="mt-1 text-[11px] text-gray-500">You can change formatting later.</p>
      </div>

      <!-- Footer actions -->
      <div class="mt-4 flex items-center gap-2">
        <button type="submit" class="cursor-pointer inline-flex w-full justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">Add field</button>
        <button type="button" data-close-add id="cancelAddColumn" class="cursor-pointer inline-flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Cancel</button>
      </div>
    </form>
    </div>
  </div>

    <!-- THEAD -->
    <div class="universal-table" id="ut-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
      <form action="<?= $CATEGORY_URL ?>/edit_thead.php" method="post"
            class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">
        <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
        <div class="flex text-xs md:text-xs font-bold text-gray-900">
          <div class="w-1/5 p-2">
            <input name="thead_name" value="<?= htmlspecialchars($thead['thead_name'] ?? 'Name', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Name" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/5 p-2">
            <input name="thead_notes" value="<?= htmlspecialchars($thead['thead_notes'] ?? 'Notes', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Notes" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/5 p-2">
            <input name="thead_assignee" value="<?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Assignee" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-40 p-2">
            <input name="thead_status" value="<?= htmlspecialchars($thead['thead_status'] ?? 'Status', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Status" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/5 p-2 ml-8">
            <input name="thead_attachment" value="<?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Attachment" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
        </div>
      </form>
    </div>

    <!-- TBODY -->
    <div class="w-full divide-y divide-gray-200">
      <?php if ($hasRecord): foreach ($rows as $r): ?>
        <form method="POST"
              action="<?= $CATEGORY_URL ?>/edit_tbody.php?id=<?= (int)$r['id'] ?>"
              enctype="multipart/form-data"
              class="universal-row flex items-center border-b border-gray-200 hover:bg-gray-50 text-sm"
              data-status="<?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
          <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment_summary'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <div class="w-1/5 p-1 text-gray-600" data-col="name">
            <input type="text" name="name" value="<?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/5 p-1 text-gray-600" data-col="notes">
            <input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/5 p-1 text-gray-600" data-col="assignee">
            <input type="text" name="assignee" value="<?= htmlspecialchars($r['assignee'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-30 px-3 py-1 text-xs font-semibold" data-col="status">
            <?php
              $statusColors = [
                'To Do'       => 'bg-red-100 text-red-800',
                'In Progress' => 'bg-yellow-100 text-yellow-800',
                'Done'        => 'bg-green-100 text-green-800'
              ];
              $colorClass = $statusColors[$r['status'] ?? ''] ?? 'bg-white text-gray-900';
            ?>
            <select name="status" class="custom-select w-full px-3 py-1 rounded-xl status--autosave <?= $colorClass ?>">
              <option value="To Do"       <?= ($r['status'] ?? '') === 'To Do' ? 'selected' : '' ?>>To Do</option>
              <option value="In Progress" <?= ($r['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="Done"        <?= ($r['status'] ?? '') === 'Done' ? 'selected' : '' ?>>Done</option>
            </select>
          </div>

          <div class="w-1/5 p-1 flex items-center gap-3 ml-19" data-col="attachment">
            <?php if (!empty($r['attachment_summary'])): ?>
              <img src="<?= $UPLOAD_URL . '/' . rawurlencode($r['attachment_summary']) ?>"
                   class="w-16 h-10 rounded-md" alt="Attachment">
            <?php else: ?>
              <span class="italic text-gray-400 ml-2">📎 None</span>
            <?php endif; ?>

            <div class="ml-auto flex items-center">
              <a href="<?= $CATEGORY_URL ?>/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>"
                 onclick="return confirm('Are you sure?')"
                 class="inline-block py-1 px-2 text-red-500 hover:bg-red-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.8" stroke="currentColor"
                     class="w-10 h-10 text-gray-500 hover:text-red-600 transition p-2 rounded">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6m2 4H7l1 12h8l1-12z" />
                </svg>
              </a>
            </div>
          </div>
        </form>
      <?php endforeach; else: ?>
        <div class="px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300">No records found.</div>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination my-4 flex justify-start md:justify-center space-x-2">
        <?php if ($page > 1): ?>
          <a href="insert_universal.php?page=<?= $page-1 ?>&table_id=<?= (int)$table_id ?>"
             class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">« Prev</a>
        <?php endif; ?>
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
          <a href="insert_universal.php?page=<?= $i ?>&table_id=<?= (int)$table_id ?>"
             class="px-3 py-1 border rounded transition
               <?= $i===$page ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                              : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="insert_universal.php?page=<?= $page+1 ?>&table_id=<?= (int)$table_id ?>"
             class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">Next »</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
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
        <input type="text" name="name" placeholder="<?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?></label>
        <input type="text" name="notes" placeholder="<?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?></label>
        <input type="text" name="assignee" placeholder="<?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
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
        <input id="attachment_summary" type="file" name="attachment_summary" accept="image/*"
               class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
      </div>

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
