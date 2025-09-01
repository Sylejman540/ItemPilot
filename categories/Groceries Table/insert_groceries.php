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

// Persist/resolve current table_id
if ($action === 'create_blank') {
    // always create new blank table   for this user
    $stmt = $conn->prepare("INSERT INTO groceries_table (user_id, created_at) VALUES (?, NOW())");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$conn->insert_id;
    $stmt->close();

    $_SESSION['current_table_id'] = $table_id;

} elseif ($table_id > 0) {
    // if passed in URL
    $_SESSION['current_table_id'] = $table_id;

} else {
    // try session
    $table_id = (int)($_SESSION['current_table_id'] ?? 0);

    // fallback: latest table for this user
    if ($table_id <= 0) {
        $q = $conn->prepare("SELECT table_id FROM `groceries_table` WHERE user_id = ? ORDER BY table_id DESC LIMIT 1");
        $q->bind_param('i', $uid);
        $q->execute();
        $q->bind_result($latestId);
        $q->fetch();
        $q->close();
        $table_id = (int)$latestId;
    }

    // if still none, create first one
    if ($table_id <= 0) {
        $stmt = $conn->prepare("INSERT INTO groceries_table (user_id, created_at) VALUES (?, NOW())");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $table_id = (int)$conn->insert_id;
        $stmt->close();
    }

    $_SESSION['current_table_id'] = $table_id;
}

// Handle insert/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $brand_flavor = $_POST['brand_flavor'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $department = $_POST['department'] ?? '';
    $purchased = $_POST['purchased'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $photo = null;

    $photo = $_POST['existing_photo'] ?? '';

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['photo']['tmp_name'];
        $orig = basename($_FILES['photo']['name']);
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            die("Could not create uploads directory.");
        }
        $dest = $uploadDir . $orig;
        if (!move_uploaded_file($tmp, $dest)) {
            die("Failed to save uploaded file.");
        }
        $photo = $orig; // Only overwrite if upload is successful
    }


    // ensure types

    if (empty($id)) {
        $stmt = $conn->prepare("INSERT INTO groceries (photo, brand_flavor, quantity, department, purchased, notes, table_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssisii', $photo, $brand_flavor, $quantity, $department, $purchased, $notes, $table_id, $uid);
    } else {
        $stmt = $conn->prepare("UPDATE groceries SET photo = ?, brand_flavor = ?, quantity = ?, department = ?, purchased = ?, notes = ? WHERE id = ? AND table_id = ? AND user_id = ?");
        $stmt->bind_param('ssssisiii', $photo, $brand_flavor, $quantity, $department, $purchased, $notes, $id, $table_id, $uid);
    }
    $stmt->execute();
    $stmt->close();

    $returnPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    header("Location: /ItemPilot/home.php?autoload=1&type=groceries&table_id={$table_id}");
    exit;
}

// Pagination logic
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total rows
$countStmt = $conn->prepare("SELECT COUNT(*) FROM groceries WHERE user_id = ? AND table_id = ?");
$countStmt->bind_param('ii', $uid, $table_id);
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

// Fetch current page rows
$dataStmt = $conn->prepare("SELECT id, photo, brand_flavor, quantity, department, purchased, notes FROM groceries WHERE user_id = ? AND table_id = ? ORDER BY id ASC LIMIT ? OFFSET ?");
$dataStmt->bind_param('iiii', $uid, $table_id, $limit, $offset);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// Determine record existence
$hasRecord = count($rows) > 0;

// Table head labels
$theadStmt = $conn->prepare("SELECT * FROM groceries_head WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$theadStmt->bind_param('i', $uid);
$theadStmt->execute();
$thead = $theadStmt->get_result()->fetch_assoc();
$theadStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Groceries</title>
</head>
<body>
<?php
$rows  = $rows ?? [];   // already fetched above
?>

<header id="appHeader" class="absolute md:mt-13 mt-20 transition-all duration-300 ease-in-out" style="padding-left: 1.25rem; padding-right: 1.25rem;">
  <section class="flex mt-6 justify-between ml-3" id="randomHeader">
    <?php
    $tableId = filter_input(INPUT_GET, 'table_id', FILTER_VALIDATE_INT);

    $stmt = $conn->prepare("SELECT table_id, table_title FROM groceries_table WHERE user_id = ? AND table_id = ? LIMIT 1");
    $stmt->bind_param('ii', $uid, $tableId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows) {
      $rowTitle = $res->fetch_assoc(); ?>
      <form method="POST" action="/ItemPilot/categories/Groceries%20Table/edit.php">
        <input type="hidden" name="table_id" value="<?= (int)$rowTitle['table_id'] ?>">
        <input type="text" name="table_title" value="<?= htmlspecialchars($rowTitle['table_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full px-4 py-2 text-lg font-bold text-black rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
      </form>
    <?php
    }
    $stmt->close();
    ?>
    <button id="addIcon" type="button" class="flex items-center gap-1 bg-yellow-400 hover:bg-yellow-500 py-[10px] cursor-pointer px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">New Record</span>
    </button>
  </section>

  <main class="md:mt-0 mt-10 overflow-x-auto md:overflow-x-hidden">
    <div class="mx-auto mt-12 mb-2 mr-5 bg-white p-4 md:p-8 lg:p-10 rounded-xl shadow-md border border-gray-100 md:w-full w-240">

      <?php
      // latest header labels for this table
      $stmt = $conn->prepare("SELECT id, table_id, photo, brand_flavor, quantity, department, purchased, notes
                                FROM groceries_head
                               WHERE user_id = ? AND table_id = ?
                            ORDER BY id DESC
                               LIMIT 1");
      $stmt->bind_param('ii', $uid, $tableId);
      $stmt->execute();
      $res = $stmt->get_result();

      if ($res && $res->num_rows) {
        $head = $res->fetch_assoc();
      } else {
        $head = [
          'id' => 0,
          'table_id' => $tableId,
          'photo' => 'Photo',
          'brand_flavor' => 'Brand/Flavor',
          'quantity' => 'Quantity',
          'department' => 'Department',
          'purchased' => 'Purchased',
          'notes' => 'Notes'
        ];
      }
      $stmt->close();
      ?>

      <!-- THEAD (labels editor) -->
      <div class="universal-table" id="ut-<?= (int)$tableId ?>" data-table-id="<?= (int)$tableId ?>">
        <form action="/ItemPilot/categories/Groceries%20Table/edit_thead.php" method="post" class="w-full thead-form border-b border-gray-200" data-table-id="<?= (int)$tableId ?>">
          <input type="hidden" name="id" value="<?= (int)$head['id'] ?>">
          <input type="hidden" name="table_id" value="<?= (int)$head['table_id'] ?>">

          <div class="flex text-xs md:text-xs font-bold text-gray-900 uppercase">
            <div class="w-1/7 p-2">
              <input name="photo" value="<?= htmlspecialchars($head['photo'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Photo" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
            </div>
            <div class="w-1/7 p-2">
              <input name="brand_flavor" value="<?= htmlspecialchars($head['brand_flavor'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Brand/Flavor" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
            </div>
            <div class="w-1/7 p-2">
              <input name="quantity" value="<?= htmlspecialchars($head['quantity'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Quantity" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
            </div>
            <div class="w-1/7 p-2">
              <input name="department" value="<?= htmlspecialchars($head['department'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Department" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
            </div>
            <div class="w-1/7 p-2">
              <input name="purchased" value="<?= htmlspecialchars($head['purchased'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Purchased" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
            </div>
            <div class="w-1/7 p-2">
              <input name="notes" value="<?= htmlspecialchars($head['notes'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Notes" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
            </div>
          </div>
        </form>
      </div>

      <!-- TBODY (rows) -->
      <div class="w-full divide-y divide-gray-200">
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
          <form method="POST" action="/ItemPilot/categories/Groceries%20Table/insert_groceries.php" enctype="multipart/form-data" class="flex items-center border-b border-gray-200 hover:bg-gray-50 text-sm">

            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="table_id" value="<?= (int)$tableId ?>">
            <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($r['photo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <!-- Photo -->
          <div class="w-1/7 p-2 text-gray-600">
          <?php if ($r['photo']): ?>
            <!-- Show uploaded attachment -->
            <img src="/ItemPilot/categories/Groceries Table/uploads/<?= htmlspecialchars($r['photo']) ?>"  class="w-16 h-10 rounded-md"  alt="Attachment">
            <?php else: ?>
              <!-- Show 'None' when no attachment -->
              <span class="italic text-gray-400 ml-2">📎 None</span>
            <?php endif; ?>
            </div>

            <!-- Brand/Flavor -->
            <div class="w-1/7 p-2 text-gray-600">
              <input type="text" name="brand_flavor" value="<?= htmlspecialchars($r['brand_flavor'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
            </div>

            <!-- Quantity -->
            <div class="w-1/7 p-2 text-gray-600">
              <input type="text" name="quantity" value="<?= htmlspecialchars($r['quantity'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
            </div>

            <!-- Department -->
            <?php
            $DEPTS = [
              'Produce','Bakery','Dairy','Frozen','Meat/Seafood','Dry Goods','Household'
            ];

            $deptColors = [
              'Produce'          => 'bg-green-100 text-green-800',
              'Bakery'           => 'bg-yellow-100 text-yellow-800',
              'Dairy'            => 'bg-blue-100 text-blue-800',
              'Frozen'           => 'bg-cyan-100 text-cyan-800',
              'Meat/Seafood'     => 'bg-rose-100 text-rose-800',
              'Dry Goods' => 'bg-amber-100 text-amber-800',
              'Household'        => 'bg-gray-100 text-gray-800',
            ];

            $deptClass = $deptColors[$r['department'] ?? ''] ?? 'bg-white text-gray-900';
            ?>
            <div class="w-40 p-2 text-gray-600 text-xs font-semibold ">
              <select  data-autosave="1"   name="department"
                      style="appearance:none;"
                      class="w-full px-2 py-1 rounded-xl status--autosave1 <?= $deptClass ?>">
                <?php foreach ($DEPTS as $opt): ?>
                  <option value="<?= $opt ?>" <?= (($r['department'] ?? '') === $opt) ? 'selected' : '' ?>>
                    <?= $opt ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Purchased -->
            <div class="w-1/7 p-2 text-gray-600">
              <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="purchased" value="1" <?= !empty($r['purchased']) ? 'checked' : '' ?> />
                <span>Purchased</span>
              </label>
            </div>

            <!-- Notes -->
            <div class="w-1/7 p-2 text-gray-600">
              <input type="text" name="notes" value="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
            </div>
            
             <div class="ml-auto flex items-center">
            <a 
              href="/ItemPilot/categories/Groceries Table/delete.php?id=<?= $r['id'] ?>&table_id=<?= (int)($row['table_id'] ?? $tableId) ?>"
              onclick="return confirm('Are you sure?')"
              class="inline-block py-1 px-2 text-red-500 hover:bg-red-50 transition">
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
          </form>
        <?php endforeach; else: ?>
          <div class="px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300">No records found.</div>
        <?php endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="pagination my-4 flex justify-start md:justify-center space-x-2">
          <?php if ($page > 1): ?>
            <a href="insert_groceries.php?page=<?= $page-1 ?>&table_id=<?= (int)$tableId ?>"
              class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">
              « Prev
            </a>
          <?php endif; ?>

          <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <a href="insert_groceries.php?page=<?= $i ?>&table_id=<?= (int)$tableId ?>"
              class="px-3 py-1 border rounded transition
                      <?= $i===$page
                        ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                        : 'text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="insert_groceries.php?page=<?= $page+1 ?>&table_id=<?= (int)$tableId ?>"
              class="px-3 py-1 border rounded text-blue-600 border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition">
              Next »
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>
</header>

<!-- Add a new record -->
<div id="addForm" class="min-h-screen flex items-center justify-center p-2 hidden relative mt-13">
  <div class="bg-white w-full max-w-md p-5 rounded-2xl shadow-lg" id="signup">
    <div class="flex justify-between">
      <a href="#" data-close-add>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="6" y1="6" x2="18" y2="18" />
          <line x1="6" y1="18" x2="18" y2="6" />
        </svg>
      </a>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </div>

    <?php
      // show title
      $stmt = $conn->prepare("SELECT table_id, table_title FROM groceries_table WHERE user_id = ? AND table_id = ? LIMIT 1");
      $stmt->bind_param('ii', $uid, $tableId);
      $stmt->execute();
      $res = $stmt->get_result();
      $titleRow = $res && $res->num_rows ? $res->fetch_assoc() : ['table_id' => $tableId, 'table_title' => ''];
      $stmt->close();

      // label defaults for the add form
      $stmt = $conn->prepare("SELECT id, table_id, photo, brand_flavor, quantity, department, purchased, notes
                                FROM groceries_head
                               WHERE user_id = ? AND table_id = ?
                            ORDER BY id DESC
                               LIMIT 1");
      $stmt->bind_param('ii', $uid, $tableId);
      $stmt->execute();
      $res = $stmt->get_result();
      $labels = $res && $res->num_rows
        ? $res->fetch_assoc()
        : ['photo'=>'Photo','brand_flavor'=>'Brand/Flavor','quantity'=>'Quantity','department'=>'Department','purchased'=>'Purchased','notes'=>'Notes'];
      $stmt->close();
    ?>

    <form action="/ItemPilot/categories/Groceries%20Table/insert_groceries.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <input type="hidden" name="table_id" value="<?= (int)$titleRow['table_id'] ?>">

      <h1 class="w-full px-4 py-2 text-center text-2xl">
        <?= htmlspecialchars($titleRow['table_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
      </h1>

      <!-- Photo -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($labels['photo'], ENT_QUOTES, 'UTF-8') ?></label>
        <input id="photo" type="file" name="photo" accept="image/*" class="w-full mt-1 border border-gray-300 rounded-lg p-2 text-sm file:bg-blue-50 file:border-0 file:rounded-md file:px-4 file:py-2">
      </div>

      <!-- Brand/Flavor -->
      <div class="mt-5">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($labels['brand_flavor'], ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="brand_flavor" placeholder="<?= htmlspecialchars($labels['brand_flavor'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <!-- Quantity -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($labels['quantity'], ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="quantity" placeholder="<?= htmlspecialchars($labels['quantity'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <!-- Department -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($labels['department'], ENT_QUOTES, 'UTF-8') ?></label>
        <select name="department" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <?php foreach ($DEPTS as $opt): ?>
            <option value="<?= $opt ?>"><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Purchased -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($labels['purchased'], ENT_QUOTES, 'UTF-8') ?></label>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="purchased" value="1" />
          <span><?= htmlspecialchars($labels['purchased'], ENT_QUOTES, 'UTF-8') ?></span>
        </label>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($labels['notes'], ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="notes" placeholder="<?= htmlspecialchars($labels['notes'], ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
      </div>

      <div>
        <button type="submit" class="w-full py-3 bg-yellow-400 hover:bg-yellow-500 text-white font-semibold rounded-lg transition">Create New Record</button>
      </div>
    </form>
  </div>
</div>

<style>
.custom-select { appearance: none; }
</style>
</body>
</html>
