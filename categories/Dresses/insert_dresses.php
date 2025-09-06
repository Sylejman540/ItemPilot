<?php
require_once __DIR__ . '/../../db.php';
session_start();
$uid = $_SESSION['user_id'] ?? 0;

if ($uid <= 0) {
    header("Location: register/login.php");
    exit;
}

$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

/* ---------------------------
   Resolve current table_id
----------------------------*/
if ($action === 'create_blank') {
    // Create a new sales_table row for this user
    $stmt = $conn->prepare("INSERT INTO sales_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
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
        $q = $conn->prepare("SELECT table_id FROM sales_table WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
        $q->bind_param('i', $uid);
        $q->execute();
        $q->bind_result($latestId);
        $q->fetch();
        $q->close();
        $table_id = (int)$latestId;
    }
    if ($table_id <= 0) {
        $stmt = $conn->prepare("INSERT INTO sales_table (user_id, created_at) VALUES (?, CURRENT_TIMESTAMP)");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $table_id = (int)$conn->insert_id;
        $stmt->close();
    }

    $_SESSION['current_sales_table_id'] = $table_id;
}

/* ---------------------------
   Create / Update row (POST)
----------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id                 = $_POST['id'] ?? '';
    $linked_initiatives = $_POST['linked_initiatives'] ?? '';
    $notes              = $_POST['notes'] ?? '';
    $executive_sponsor  = $_POST['executive_sponsor'] ?? '';
    $status             = $_POST['status'] ?? '';
    $complete           = $_POST['complete'] ?? '';
    $priority           = $_POST['priority'] ?? '';
    $owner              = $_POST['owner'] ?? '';
    $deadline           = $_POST['deadline'] ?? '';
 
    // keep existing attachment if none uploaded
    $attachment = $_POST['existing_attachment'] ?? '';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['attachment']['tmp_name'];
        $orig = basename($_FILES['attachment']['name']);

        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            die("Could not create uploads directory.");
        }
        $dest = $uploadDir . $orig;
        if (!move_uploaded_file($tmp, $dest)) {
            die("Failed to save uploaded file.");
        }
        $attachment = $orig;
    }

    if (empty($id)) {
        $stmt = $conn->prepare("
          INSERT INTO sales_strategy
            (linked_initiatives, notes, executive_sponsor, status, complete, priority, owner, deadline, attachment, table_id, user_id)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssssssis',
          $linked_initiatives, $notes, $executive_sponsor, $status, $complete, $priority, $owner, $deadline, $attachment, $table_id, $uid
        );
    } else {
        $stmt = $conn->prepare("
          UPDATE sales_strategy
             SET linked_initiatives = ?,
                 notes              = ?,
                 executive_sponsor  = ?,
                 status             = ?,
                 complete           = ?,
                 priority           = ?,
                 owner              = ?,
                 deadline           = ?,
                 attachment         = ?
           WHERE id = ? AND table_id = ? AND user_id = ?
        ");
        $stmt->bind_param('sssssssssiii',
          $linked_initiatives, $notes, $executive_sponsor, $status, $complete, $priority, $owner, $deadline, $attachment, $id, $table_id, $uid
        );
    }

    $stmt->execute();
    $stmt->close();

    header("Location: /ItemPilot/home.php?autoload=1&type=sales&table_id={$table_id}");
    exit;
}

/* ---------------------------
   Pagination + data fetch
----------------------------*/
$limit  = 10;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("
  SELECT COUNT(*) FROM sales_strategy WHERE user_id = ? AND table_id = ?
");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("
  SELECT id, linked_initiatives, notes, executive_sponsor, status, complete, priority, owner, deadline, attachment
    FROM sales_strategy
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
   Table head labels
----------------------------*/
$theadStmt = $conn->prepare("
  SELECT linked_initiatives, executive_sponsor, status, complete, notes, priority, owner, deadline, attachment
    FROM sales_strategy_thead
   WHERE user_id = ? AND table_id = ?
ORDER BY id DESC
   LIMIT 1
");
$theadStmt->bind_param('ii', $uid, $table_id);
$theadStmt->execute();
$thead = $theadStmt->get_result()->fetch_assoc();
$theadStmt->close();

/* For title */
/* For title */
$titleStmt = $conn->prepare("SELECT table_id, table_title FROM sales_table WHERE user_id = ? AND table_id = ? LIMIT 1");
$titleStmt->bind_param('ii', $uid, $table_id);
$titleStmt->execute();
$titleRes = $titleStmt->get_result();
$tableTitleRow = $titleRes->fetch_assoc();
$titleStmt->close();
$tableTitle = $tableTitleRow['table_title'] ?? 'Undefined Title';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dresses</title>
</head>
<body>
<header id="appHeader" class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out" style="padding-left:1.25rem; padding-right:1.25rem;">
  <section class="flex mt-6 justify-between ml-3">
    <!-- Rename action to the title handler and encode the space -->
    <form action="/ItemPilot/categories/Dresses/edit.php" method="POST" class="flex gap-2">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input
        type="text"
        name="table_title"
        value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>"
        class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"
        placeholder="Untitled sales table"
      />
    </form>

    <button id="addIcon" type="button"
            class="flex items-center gap-1 bg-blue-500 hover:bg-blue-400 py-[10px] cursor-pointer px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">New Record</span>
    </button>
  </section>


<main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden">
  <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-[90rem]">

    <div class="mb-3">
      <input id="rowSearchS" type="search" placeholder="Search rows…" data-rows=".sales-row" data-count="#countS" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-96"/>
      <span id="countS" class="ml-2 text-xs text-gray-600"></span>
    </div>


    <?php
    // Prefill THEAD form
    $theadFetch = $conn->prepare("
      SELECT id, table_id, linked_initiatives, executive_sponsor, status, complete, notes, priority, owner, deadline, attachment
        FROM sales_strategy_thead
       WHERE user_id = ? AND table_id = ?
       ORDER BY id DESC
       LIMIT 1
    ");
    $theadFetch->bind_param('ii', $uid, $table_id);
    $theadFetch->execute();
    $res = $theadFetch->get_result();
    $headRow = $res && $res->num_rows ? $res->fetch_assoc() : ['id'=>0,'table_id'=>$table_id];
    $theadFetch->close();
    ?>

    <!-- THEAD -->
    <div class="universal-table" id="sales-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
      <form action="/ItemPilot/categories/Dresses/edit_thead.php" method="post"
            class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">

        <input type="hidden" name="id" value="<?= (int)($headRow['id'] ?? 0) ?>">
        <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">

        <div class="flex text-xs gap-2 font-semibold text-black uppercase">
          <div class="w-1/10 p-2">
            <input name="linked_initiatives" value="<?= htmlspecialchars($headRow['linked_initiatives'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Name" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="notes" value="<?= htmlspecialchars($headRow['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Data per dorezim" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="executive_sponsor" value="<?= htmlspecialchars($headRow['executive_sponsor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Shteti" class="w-full bg-transparent whitespace-normal break-words border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="status" value="<?= htmlspecialchars($headRow['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Status" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="complete" value="<?= htmlspecialchars($headRow['complete'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Mosha" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="priority" value="<?= htmlspecialchars($headRow['priority'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Cmimi" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="owner" value="<?= htmlspecialchars($headRow['owner'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Cmimi i materialit" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="deadline" value="<?= htmlspecialchars($headRow['deadline'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Fitimi" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
          <div class="w-1/10 p-2">
            <input name="attachment" value="<?= htmlspecialchars($headRow['attachment'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Modeli" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
          </div>
        </div>
      </form>
    </div>

    <!-- TBODY -->
    <div class="md:w-full w-[88rem] divide-y divide-gray-200">
      <?php if ($hasRecord): foreach ($rows as $r): ?>
        <form method="POST"
              action="/ItemPilot/categories/Dresses/edit_tbody.php?id=<?= (int)$r['id'] ?>"
              enctype="multipart/form-data"
              class="sales-row flex items-center border-b gap-2 border-gray-200 hover:bg-gray-50 text-sm"
              data-sponsor="<?= htmlspecialchars($r['executive_sponsor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-status="<?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-priority="<?= htmlspecialchars($r['priority'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
          <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <div class="w-1/10 p-2 text-gray-600" data-col="linked_initiatives">
            <input type="text" name="linked_initiatives" value="<?= htmlspecialchars($r['linked_initiatives'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/10 p-2 text-gray-600" data-col="notes">
            <input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/10 p-2 text-gray-600" data-col="executive_sponsor">
            <input type="text" name="executive_sponsor" value="<?= htmlspecialchars($r['executive_sponsor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/10 p-2 text-gray-600 text-xs font-semibold" data-col="status">
            <?php
              $statusColors = [
                'To Do'       => 'bg-red-100 text-red-800',
                'In Progress' => 'bg-yellow-100 text-yellow-800',
                'Done'        => 'bg-green-100 text-green-800'
              ];
              $colorClass = $statusColors[$r['status'] ?? ''] ?? 'bg-white text-gray-900';
            ?>
            <select name="status" style="appearance:none;" class="w-full px-2 py-1 rounded-xl status--autosave <?= $colorClass ?>">
              <option value="To Do"       <?= ($r['status'] ?? '') === 'To Do' ? 'selected' : '' ?>>To Do</option>
              <option value="In Progress" <?= ($r['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="Done"        <?= ($r['status'] ?? '') === 'Done' ? 'selected' : '' ?>>Done</option>
            </select>
          </div>

          <div class="w-1/10 p-2 text-gray-600" data-col="complete">
            <input type="text" name="complete" value="<?= htmlspecialchars($r['complete'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/10 p-2 text-gray-600" data-col="priority">
            <input type="text" name="priority" value="<?= htmlspecialchars($r['priority'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/10 p-2 text-gray-600" data-col="owner">
            <input type="text" name="owner" value="<?= htmlspecialchars($r['owner'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/10 p-2 text-gray-600 whitespace-normal break-words" data-col="deadline">
            <input type="text" name="deadline" value="<?= htmlspecialchars($r['deadline'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/10 p-2 flex items-center" data-col="modeli">
            <?php if (!empty($r['attachment'])): ?>
              <img src="/ItemPilot/categories/Dresses/uploads/<?= htmlspecialchars($r['attachment'], ENT_QUOTES, 'UTF-8') ?>"
                   class="w-16 h-10 rounded-md" alt="Attachment">
            <?php else: ?>
              <span class="italic text-gray-400 ml-[5px]">📎 None</span>
            <?php endif; ?>
            <div class="w-1/10 flex items-center">
              <a href="/ItemPilot/categories/Dresses/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>"
                 onclick="return confirm('Are you sure?')"
                 class="inline-block py-1 px-6 text-red-500 hover:bg-red-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none" viewBox="0 0 24 24"
                     stroke-width="1.8" stroke="currentColor"
                     class="w-10 h-10 text-gray-500 hover:text-red-600 transition p-2 rounded">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 3h6m2 4H7l1 12h8l1-12z" />
                </svg>
              </a>
            </div>
          </div>
        </form>
      <?php endforeach; else: ?>
        <div class="px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300">
          No records found.
        </div>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination strategy-section my-2 flex justify-start md:justify-center space-x-2">
        <?php if ($page > 1): ?>
          <a href="insert_dresses.php?page=<?= $page-1 ?>&table_id=<?= $salesId ?>"
            class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">
            « Prev
          </a>
        <?php endif; ?>

        <?php for ($i=1; $i<=$totalPages; $i++): ?>
          <a href="insert_dresses.php?page=<?= $i ?>&table_id=<?= $salesId ?>"
            class="px-3 py-1 border rounded transition
                    <?= $i===$page
                      ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                      : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a href="insert_dresses.php?page=<?= $page+1 ?>&table_id=<?= $salesId ?>"
            class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">
            Next »
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<div id="addForm" class="fixed inset-0 z-50 hidden">
<div class="w-full p-5 rounded-2xl shadow-lg overflow-y-auto" id="signup">

  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/40" data-close-add></div>

  <!-- Modal panel -->
  <div class="relative mx-auto mt-16 w-full max-w-md">
    <div class="bg-white w-full p-5 rounded-2xl shadow-lg" id="signup">
      <div class="flex justify-between">
        <a href="#" data-close-add aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <line x1="6" y1="6" x2="18" y2="18" />
            <line x1="6" y1="18" x2="18" y2="6" />
          </svg>
        </a>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor"
             viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </div>

      <!-- keep your existing <form> exactly as-is -->
      <form action="/ItemPilot/categories/Dresses/insert_dresses.php"
            method="POST" enctype="multipart/form-data" class="space-y-6" id="addSalesForm">
        <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">

        <h1 class="w-full px-4 py-2 text-center text-2xl">
          <?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <!-- Name -->
        <div class="mt-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['linked_initiatives'] ?? 'Name') ?>
          </label>
          <input type="text" name="linked_initiatives"
                 placeholder="<?= htmlspecialchars($thead['linked_initiatives'] ?? 'Name') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['notes'] ?? 'Data per dorezim') ?>
          </label>
          <input type="text" name="notes"
                 placeholder="<?= htmlspecialchars($thead['notes'] ?? 'Data per dorezim') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <!-- Executive sponsor -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['executive_sponsor'] ?? 'Shteti') ?>
          </label>
          <input type="text" name="executive_sponsor"
                 placeholder="<?= htmlspecialchars($thead['executive_sponsor'] ?? 'Shteti') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['status'] ?? 'Status') ?>
          </label>
          <select name="status"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="To Do">To Do</option>
            <option value="In Progress">In Progress</option>
            <option value="Done">Done</option>
          </select>
        </div>

        <!-- Complete -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['complete'] ?? 'Mosha') ?>
          </label>
          <input type="text" name="complete"
                 placeholder="<?= htmlspecialchars($thead['complete'] ?? 'Mosha') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <!-- Priority (Cmimi) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['priority'] ?? 'Cmimi') ?>
          </label>
          <input type="text" name="priority" id="priorityAdd"
                 placeholder="<?= htmlspecialchars($thead['priority'] ?? 'Cmimi') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <!-- Owner (Cmimi i materialit) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['owner'] ?? 'Cmimi i materialit') ?>
          </label>
          <input type="text" name="owner" id="ownerAdd"
                 placeholder="<?= htmlspecialchars($thead['owner'] ?? 'Cmimi i materialit') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <!-- Deadline (Fitimi) — auto-computed -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['deadline'] ?? 'Fitimi') ?>
          </label>
          <input type="text" name="deadline" id="deadlineAdd"
                 placeholder="<?= htmlspecialchars($thead['deadline'] ?? 'Fitimi') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <!-- Attachment -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <?= htmlspecialchars($thead['attachment'] ?? 'Modeli') ?>
          </label>
          <input id="attachment_summary" type="file" name="attachment" accept="image/*" capture="environment"
                 class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
        </div>

        <div>
          <button type="submit"
                  class="w-full py-3 bg-blue-500 hover:bg-blue-400 text-white font-semibold rounded-lg transition">
            Create New Record
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
