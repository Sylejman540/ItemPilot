<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0) { header("Location: register/login.php"); exit; }

/* ---------- Config ---------- */
$CATEGORY_URL = '/ItemPilot/categories/Groceries%20Table'; // encoded space
$UPLOAD_DIR   = __DIR__ . '/uploads/';
$UPLOAD_URL   = $CATEGORY_URL . '/uploads';

/* ---------- Resolve table_id ---------- */
$action   = $_GET['action'] ?? null;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($action === 'create_blank') {
  $stmt = $conn->prepare("INSERT INTO groceries_table (user_id, created_at) VALUES (?, NOW())");
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
    $q = $conn->prepare("SELECT table_id FROM groceries_table WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
    $q->bind_param('i', $uid);
    $q->execute(); $q->bind_result($latestId); $q->fetch(); $q->close();
    $table_id = (int)$latestId;
  }
  if ($table_id <= 0) {
    $stmt = $conn->prepare("INSERT INTO groceries_table (user_id, created_at) VALUES (?, NOW())");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$stmt->insert_id;
    $stmt->close();
  }
  $_SESSION['current_table_id'] = $table_id;
}

/* ---------- Create/Update ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id           = $_POST['id'] ?? '';
  $brand_flavor = $_POST['brand_flavor'] ?? '';
  $quantity     = $_POST['quantity'] ?? '';
  $department   = $_POST['department'] ?? '';
  $purchased    = empty($_POST['purchased']) ? 0 : 1; // store as int
  $notes        = $_POST['notes'] ?? '';
  $photo        = $_POST['existing_photo'] ?? '';

  if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
      die("Could not create uploads directory.");
    }
    $tmp  = $_FILES['photo']['tmp_name'];
    $orig = basename($_FILES['photo']['name']);
    $dest = $UPLOAD_DIR . $orig;
    if (!move_uploaded_file($tmp, $dest)) {
      die("Failed to save uploaded file.");
    }
    $photo = $orig;
  }

  if ($id === '' || $id === null) {
    $stmt = $conn->prepare("
      INSERT INTO groceries (photo, brand_flavor, quantity, department, purchased, notes, table_id, user_id)
      VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param('ssssisii', $photo, $brand_flavor, $quantity, $department, $purchased, $notes, $table_id, $uid);
  } else {
    $stmt = $conn->prepare("
      UPDATE groceries
         SET photo = ?, brand_flavor = ?, quantity = ?, department = ?, purchased = ?, notes = ?
       WHERE id = ? AND table_id = ? AND user_id = ?
    ");
    $stmt->bind_param('ssssisiii', $photo, $brand_flavor, $quantity, $department, $purchased, $notes, $id, $table_id, $uid);
  }
  $stmt->execute(); $stmt->close();

  header("Location: /ItemPilot/home.php?autoload=1&type=groceries&table_id={$table_id}");
  exit;
}

/* ---------- Title ---------- */
$stmt = $conn->prepare("SELECT table_title FROM groceries_table WHERE user_id = ? AND table_id = ? LIMIT 1");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$res = $stmt->get_result();
$rowTitle = $res && $res->num_rows ? $res->fetch_assoc() : ['table_title' => ''];
$stmt->close();
$tableTitle = $rowTitle['table_title'] ?? '';

/* ---------- Ensure HEAD exists for this table ---------- */
$stmt = $conn->prepare("
  SELECT id, table_id, photo, brand_flavor, quantity, department, purchased, notes
    FROM groceries_head
   WHERE user_id = ? AND table_id = ?
ORDER BY id DESC
   LIMIT 1
");
$stmt->bind_param('ii', $uid, $table_id);
$stmt->execute();
$headRes = $stmt->get_result();

if ($headRes && $headRes->num_rows) {
  $head = $headRes->fetch_assoc();
} else {
  $head = [
    'photo'        => 'Photo',
    'brand_flavor' => 'Brand/Flavor',
    'quantity'     => 'Quantity',
    'department'   => 'Department',
    'purchased'    => 'Purchased',
    'notes'        => 'Notes'
  ];
  $ins = $conn->prepare("
    INSERT INTO groceries_head
      (user_id, table_id, photo, brand_flavor, quantity, department, purchased, notes)
    VALUES (?,?,?,?,?,?,?,?)
  ");
  $zero = 'No';
  $ins->bind_param('iissssss', $uid, $table_id,
    $head['photo'], $head['brand_flavor'], $head['quantity'],
    $head['department'], $head['purchased'], $head['notes']
  );
  $ins->execute(); $ins->close();
}
$stmt->close();

/* ---------- Pagination + data ---------- */
$limit  = 10;
$page   = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) FROM groceries WHERE user_id = ? AND table_id = ?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute(); $countStmt->bind_result($totalRows); $countStmt->fetch(); $countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

$dataStmt = $conn->prepare("
  SELECT id, photo, brand_flavor, quantity, department, purchased, notes
    FROM groceries
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
<title>Groceries</title>
</head>
<body>

<header id="appHeader" class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out" style="padding-left:1.25rem;padding-right:1.25rem;">
  <section class="flex mt-6 justify-between ml-3" id="randomHeader">
    <form method="POST" action="<?= $CATEGORY_URL ?>/edit.php">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <input type="text" name="table_title"
             value="<?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?>"
             class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
    </form>

    <button id="addIcon" type="button" class="flex items-center gap-1 bg-blue-400 hover:bg-blue-500 py-[10px] cursor-pointer px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">New Record</span>
    </button>
  </section>

  <main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden">
    <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-240">

      <div class="mb-3">
        <input id="rowSearchG" type="search" placeholder="Search rows…" data-rows=".groceries-row" data-count="#countG" class="rounded-full pl-3 pr-3 border border-gray-200 h-10 w-72 md:w-96"/>
        <span id="countG" class="ml-2 text-xs text-gray-600"></span>
      </div>

      <!-- THEAD (labels editor) -->
      <div class="universal-table" id="gt-<?= (int)$table_id ?>" data-table-id="<?= (int)$table_id ?>">
        <form action="<?= $CATEGORY_URL ?>/edit_thead.php" method="post" class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$table_id ?>">
          <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
          <div class="flex text-xs md:text-xs font-bold text-gray-900 uppercase">
            <div class="w-1/7 p-2"><input name="photo"         value="<?= htmlspecialchars($head['photo'], ENT_QUOTES, 'UTF-8') ?>"         placeholder="Photo"         class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
            <div class="w-1/7 p-2"><input name="brand_flavor"  value="<?= htmlspecialchars($head['brand_flavor'], ENT_QUOTES, 'UTF-8') ?>"  placeholder="Brand/Flavor"  class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
            <div class="w-1/7 p-2"><input name="quantity"      value="<?= htmlspecialchars($head['quantity'], ENT_QUOTES, 'UTF-8') ?>"      placeholder="Quantity"      class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
            <div class="w-30  p-2"><input name="department"    value="<?= htmlspecialchars($head['department'], ENT_QUOTES, 'UTF-8') ?>"    placeholder="Department"    class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
            <div class="w-1/7 p-2"><input name="purchased"     value="<?= htmlspecialchars($head['purchased'], ENT_QUOTES, 'UTF-8') ?>"     placeholder="Purchased"     class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
            <div class="w-1/7 p-2"><input name="notes"         value="<?= htmlspecialchars($head['notes'], ENT_QUOTES, 'UTF-8') ?>"         placeholder="Notes"         class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/></div>
          </div>
        </form>
      </div>

      <!-- TBODY (rows) -->
      <div class="w-full divide-y divide-gray-200">
        <?php if ($hasRecord): foreach ($rows as $r): ?>
          <form method="POST" action="<?= $CATEGORY_URL ?>/insert_groceries.php" enctype="multipart/form-data" class="groceries-row flex items-center border-b border-gray-200 hover:bg-gray-50 text-sm">

            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
            <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($r['photo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <!-- Photo -->
            <div class="w-1/7 p-2 text-gray-600" data-col="photo">
              <?php if (!empty($r['photo'])): ?>
                <img src="<?= $UPLOAD_URL . '/' . rawurlencode($r['photo']) ?>" class="w-16 h-10 rounded-md" alt="Attachment">
              <?php else: ?>
                <span class="italic text-gray-400 ml-2">📎 None</span>
              <?php endif; ?>
            </div>

            <!-- Brand/Flavor -->
            <div class="w-1/7 p-2 text-gray-600" data-col="brand_flavor">
              <input type="text" name="brand_flavor" value="<?= htmlspecialchars($r['brand_flavor'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
            </div>

            <!-- Quantity -->
            <div class="w-1/7 p-2 text-gray-600" data-col="quantity">
              <input type="text" name="quantity" value="<?= htmlspecialchars($r['quantity'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
            </div>

            <!-- Department -->
            <?php
              $DEPTS = ['Produce','Bakery','Dairy','Frozen','Meat/Seafood','Dry Goods','Household'];
              $deptColors = [
                'Produce'      => 'bg-green-100 text-green-800',
                'Bakery'       => 'bg-yellow-100 text-yellow-800',
                'Dairy'        => 'bg-blue-100 text-blue-800',
                'Frozen'       => 'bg-cyan-100 text-cyan-800',
                'Meat/Seafood' => 'bg-rose-100 text-rose-800',
                'Dry Goods'    => 'bg-amber-100 text-amber-800',
                'Household'    => 'bg-gray-100 text-gray-800',
              ];
              $deptClass = $deptColors[$r['department'] ?? ''] ?? 'bg-white text-gray-900';
            ?>
            <div class="w-30 p-2 text-gray-600 text-xs font-semibold" data-col="department">
              <select data-autosave="1" name="department" style="appearance:none;" class="w-full px-2 py-1 rounded-xl status--autosave <?= $deptClass ?>">
                <?php foreach ($DEPTS as $opt): ?>
                  <option value="<?= $opt ?>" <?= (($r['department'] ?? '') === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Purchased -->
            <div class="w-1/7 p-2 text-gray-600" data-col="purchased">
              <label class="inline-flex items-center gap-2 ml-2">
                <input type="checkbox" name="purchased" value="1" <?= !empty($r['purchased']) ? 'checked' : '' ?> />
                <span>Purchased</span>
              </label>
            </div>

            <!-- Notes -->
            <div class="w-1/7 p-2 text-gray-600" data-col="notes">
              <input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
            </div>

            <div class="ml-auto flex items-center">
              <a href="<?= $CATEGORY_URL ?>/delete.php?id=<?= (int)$r['id'] ?>&table_id=<?= (int)$table_id ?>"
                 onclick="return confirm('Are you sure?')"
                 class="inline-block py-1 px-2 text-red-500 hover:bg-red-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-10 h-10 text-gray-500 hover:text-red-600 transition p-2 rounded">
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
        <div class="pagination grocery my-2 flex justify-start md:justify-center space-x-2">
          <?php if ($page > 1): ?>
            <a href="insert_groceries.php?page=<?= $page-1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">« Prev</a>
          <?php endif; ?>
          <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <a href="insert_groceries.php?page=<?= $i ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded transition <?= $i===$page ? 'bg-blue-600 text-white border-blue-600 font-semibold' : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="insert_groceries.php?page=<?= $page+1 ?>&table_id=<?= (int)$table_id ?>" class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">Next »</a>
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
          <line x1="6" y1="6" x2="18" y2="18" /><line x1="6" y1="18" x2="18" y2="6" />
        </svg>
      </a>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </div>

    <form action="<?= $CATEGORY_URL ?>/insert_groceries.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
      <h1 class="w-full px-4 py-2 text-center text-2xl"><?= htmlspecialchars($tableTitle, ENT_QUOTES, 'UTF-8') ?></h1>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($head['photo'], ENT_QUOTES, 'UTF-8') ?></label>
        <input id="photo" type="file" name="photo" accept="image/*" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
      </div>

      <div class="mt-5">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($head['brand_flavor'], ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="brand_flavor" placeholder="<?= htmlspecialchars($head['brand_flavor'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($head['quantity'], ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="quantity" placeholder="<?= htmlspecialchars($head['quantity'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($head['department'], ENT_QUOTES, 'UTF-8') ?></label>
        <select name="department" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="Produce">Produce</option>
          <option value="Bakery">Bakery</option>
          <option value="Dairy">Dairy</option>
          <option value="Frozen">Frozen</option>
          <option value="Meat/Seafood">Meat/Seafood</option>
          <option value="Dry Goods">Dry Goods</option>
          <option value="Household">Household</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($head['purchased'], ENT_QUOTES, 'UTF-8') ?></label>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="purchased" value="1" />
          <span><?= htmlspecialchars($head['purchased'], ENT_QUOTES, 'UTF-8') ?></span>
        </label>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($head['notes'], ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="notes" placeholder="<?= htmlspecialchars($head['notes'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <button type="submit" class="w-full py-3 bg-blue-400 hover:bg-blue-500 text-white font-semibold rounded-lg transition">Create New Record</button>
      </div>
    </form>
  </div>
</div>

<style>
.custom-select { appearance: none; }
</style>
</body>
</html>
