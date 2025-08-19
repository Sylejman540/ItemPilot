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

if ($action === 'create_blank') {
    // Do NOT pass table_id – let DB auto-generate it
    $stmt = $conn->prepare(
        "INSERT INTO tables (user_id, created_at) VALUES (?, NOW())"
    );
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $table_id = (int)$conn->insert_id;   // <- this is your NEW table_id
    $stmt->close();
    
}

// Fetch persistent table title
$tblStmt = $conn->prepare("SELECT title FROM universal WHERE user_id = ? ORDER BY id ASC LIMIT 1");
$tblStmt->bind_param('i', $uid);
$tblStmt->execute();
$tblStmt->bind_result($tableTitle);
$tblStmt->fetch();
$tblStmt->close();

// Handle insert/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $title = $_POST['title'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $assignee = $_POST['assignee'] ?? '';
    $status = $_POST['status'] ?? '';
    $attachment_summary = null;

    $attachment_summary = $_POST['existing_attachment'] ?? '';

    if (isset($_FILES['attachment_summary']) && $_FILES['attachment_summary']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['attachment_summary']['tmp_name'];
        $orig = basename($_FILES['attachment_summary']['name']);
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            die("Could not create uploads directory.");
        }
        $dest = $uploadDir . $orig;
        if (!move_uploaded_file($tmp, $dest)) {
            die("Failed to save uploaded file.");
        }
        $attachment_summary = $orig; // Only overwrite if upload is successful
    }

    if (empty($id)) {
        $stmt = $conn->prepare("INSERT INTO universal (name, notes, title, assignee, status, attachment_summary, table_id, user_id) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssii', $name, $notes, $title, $assignee, $status, $attachment_summary, $table_id, $uid);
    } else {
        $stmt = $conn->prepare("UPDATE universal SET name = ?, notes = ?, title = ?, assignee = ?, status = ?, attachment_summary = ? WHERE id = ? AND table_id = ? AND user_id = ?");
        $stmt->bind_param('ssssssiii', $name, $notes, $title, $assignee, $status, $attachment_summary, $id, $table_id, $uid);
    }
    $stmt->execute();
    $stmt->close();

    $returnPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    header("Location: /ItemPilot/home.php?autoload=1");
    exit;
}

// Pagination logic
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total rows
$countStmt = $conn->prepare("SELECT COUNT(*) FROM universal WHERE user_id = ?");
$countStmt->bind_param('i', $uid);
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = (int)ceil($totalRows / $limit);

// Fetch current page rows
$dataStmt = $conn->prepare("SELECT id, name, notes, title, assignee, status, attachment_summary FROM universal WHERE user_id = ? ORDER BY id ASC LIMIT ? OFFSET ?");
$dataStmt->bind_param('iii', $uid, $limit, $offset);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// Determine record existence
$hasRecord = count($rows) > 0;

// Table head labels
$theadStmt = $conn->prepare("SELECT * FROM universal_thead WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$theadStmt->bind_param('i', $uid);
$theadStmt->execute();
$thead = $theadStmt->get_result()->fetch_assoc();
$theadStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Universal Table</title>
</head>
<body>
<?php
$rows  = $rows ?? [];         
$first = $rows[0] ?? null;   
?>

<header class="absolute md:w-[75%] md:ml-16 md:mr-16 w-[90%] ml-5 mr-5">
  <section class="flex mt-5 justify-between ml-3" id="randomHeader">

    <?php if ($first): ?>
      <form method="POST" action="/ItemPilot/categories/Universal Table/edit.php?id=<?= (int)$first['id'] ?>">
        <input type="hidden" name="id" value="<?= (int)$first['id'] ?>">
        <input type="text" name="title" id="title" value="<?= htmlspecialchars($first['title'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </form>
    <?php else: ?>
      <div class="w-full px-4 py-2 text-gray-400 italic">
        No table yet. Create a new one.
      </div>
    <?php endif; ?>

    <button id="addIcon" type="button"
            class="flex items-center gap-1 bg-blue-800 py-[10px] cursor-pointer hover:bg-blue-700 px-2 rounded-lg text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="text-sm">Create New</span>
    </button>

  </section>

  <div class="overflow-x-auto md:overflow-x-hidden">
  <div class="md:mx-8 mt-20 ml-3 bg-white py-15 md:p-8 md:px-10 rounded-xl md:w-full w-240">

    <!-- THEAD -->
    <form action="/ItemPilot/categories/Universal%20Table/edit_thead.php" method="post" id="myForm" class="w-full mb-2">
      <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
      <div class="flex text-black text-xs uppercase font-semibold border-b border-gray-300">
        <div class="w-1/5 p-2">
          <input name="thead_name" value="<?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?>" placeholder="Name" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
        <div class="w-1/5 p-2">
          <input name="thead_notes" value="<?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?>" placeholder="Notes" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
        <div class="w-1/5 p-2">
          <input name="thead_assignee" value="<?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?>" placeholder="Assignee" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
        <div class="w-1/5 p-2">
          <input name="thead_status" value="<?= htmlspecialchars($thead['thead_status'] ?? 'Status') ?>" placeholder="Status" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
        <div class="w-1/5 p-2">
          <input name="thead_attachment" value="<?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment') ?>" placeholder="Attachment" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
      </div>
    </form>

    <!-- TBODY -->
    <div class="w-full divide-y divide-gray-200">
      <?php if ($hasRecord): foreach ($rows as $r): ?>
        <form method="POST" action="/ItemPilot/categories/Universal Table/edit_tbody.php?id=<?= $r['id'] ?>" enctype="multipart/form-data" class="flex items-center border-b border-gray-300 text-sm">
          
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <input type="hidden" name="title" value="<?= htmlspecialchars($tableTitle) ?>">
          <input type="hidden" name="existing_attachment" value="<?= htmlspecialchars($r['attachment_summary']) ?>">

          <div class="w-1/5 p-2">
            <input type="text" name="name" value="<?= htmlspecialchars($r['name']) ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/5 p-2">
            <input type="text" name="notes" value="<?= htmlspecialchars($r['notes']) ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-1/5 p-2 ml-10">
            <input type="text" name="assignee" value="<?= htmlspecialchars($r['assignee']) ?>" class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition" />
          </div>

          <div class="w-40 p-2 mr-20">
            <?php
              $statusColors = [
                'To Do'       => 'bg-gray-100 text-gray-800',
                'In Progress' => 'bg-yellow-100 text-yellow-800',
                'Done'        => 'bg-green-100 text-green-800'
              ];
              $colorClass = $statusColors[$r['status']] ?? 'bg-white text-gray-900';
            ?>
            <select name="status" class="w-full px-2 py-1 rounded-xl  <?= $colorClass ?>">
              <option value="To Do"       <?= $r['status'] === 'To Do' ? 'selected' : '' ?>>To Do</option>
              <option value="In Progress" <?= $r['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="Done"        <?= $r['status'] === 'Done' ? 'selected' : '' ?>>Done</option>
            </select>
          </div>

          <div class="w-1/5 p-2 flex items-center gap-3">
            <?php if ($r['attachment_summary']): ?>
              <img src="/ItemPilot/categories/Universal Table/uploads/<?= htmlspecialchars($r['attachment_summary']) ?>" class="w-16 h-10 rounded-md" alt="Attachment">
            <?php else: ?>
              <span class="italic text-gray-400">None</span>
            <?php endif; ?>
            <div class="ml-auto flex items-center">
              <a href="/ItemPilot/categories/Universal Table/delete.php?id=<?= $r['id'] ?>"  onclick="return confirm('Are you sure?')"  class="inline-block py-1 px-2 text-red-500 border border-red-500 rounded hover:bg-red-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="inline h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/>
                </svg>
              </a>
            </div>
          </div>
        </form>
      <?php endforeach; else: ?>
        <div class="px-4 py-4 text-center text-gray-500 w-full border-b border-gray-300">No records found.</div>
      <?php endif; ?>
    </div>

    <!-- Pagination (unchanged) -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination my-4 flex justify-center space-x-2">
        <?php if ($page > 1): ?><a href="insert_universal.php?page=<?= $page-1 ?>" class="px-3 py-1 border rounded hover:bg-gray-100">« Prev</a><?php endif; ?>
        <?php for ($i=1; $i<=$totalPages; $i++): ?><a href="insert_universal.php?page=<?= $i ?>" class="px-3 py-1 border rounded <?= $i===$page?'bg-gray-200 font-semibold':'hover:bg-gray-100' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="insert_universal.php?page=<?= $page+1 ?>" class="px-3 py-1 border rounded hover:bg-gray-100">Next »</a><?php endif; ?>
      </div>
    <?php endif; ?>
    
  </div>
  </div>
  </header>


  <!-- Add a new record -->
  <div id="addForm" class="min-h-screen flex items-center justify-center p-4 hidden relative">
   <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg" id="signup">
    <div class="flex justify-between">
      <a href="#" data-close-add>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="6" y1="6" x2="18" y2="18" />
          <line x1="6" y1="18" x2="18" y2="6" />
        </svg>
      </a>
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </div>
    <?php if ($hasRecord): ?>
      <div data-id="<?= $first['id'] ?>" class="px-4 py-2 text-center flex justify-center">
      <div data-field="title" class="text-3xl font-bold text-center mb-6"><?= htmlspecialchars($first['title']) ?></div>
      </div>
    <?php endif; ?>
    <form action="/ItemPilot/categories/Universal Table/insert_universal.php" method="POST" enctype="multipart/form-data" class="space-y-6">

      <?php if (! $hasRecord): ?>
        <div>
          <label for="title" class="block text-gray-700 font-medium mb-2">Tabel Name</label>
          <input type="text" name="title" id="title" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>
      <?php endif; ?>

      <div>
        <label><?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?></label>
        <input type="text" name="name" id="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      
      <div>
        <label><?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?></label>
        <input type="text" name="notes" id="notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label><?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?></label>
        <input type="text" name="assignee" id="assignee" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label><?= htmlspecialchars($thead['thead_status'] ?? 'Status') ?></label>
        <select type="text" name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
          <option name="do" id="do">To Do</option>
          <option name="progress" id="progress">In Progress</option>
          <option name="done" id="done">Done</option>
        </select>
      </div>
      
      <div>
        <label><?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment') ?></label>
        <input id="attachment_summary" type="file" name="attachment_summary" accept="image/*" class="w-full border border-gray-300 rounded-lg p-2 text-smfile:bg-pink-100 file:border-0 file:rounded-md file:px-4 file:py-2 file:text-[#B5707D]">
      </div>

      <div>
        <button type="submit" name="universal" class="w-full py-3 bg-blue-800 hover:bg-blue-700 cursor-pointer text-white font-semibold rounded-lg transition cursor-pointer">Create New Record</button>
      </div>
    </form>
   </div>
  </div>
</body>
</html>



