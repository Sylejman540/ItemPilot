<?php
require_once __DIR__ . '/../../db.php';
 session_start();
  $uid = $_SESSION['user_id'] ?? 'guest';  // or username if you prefer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? '';
  $name = $_POST['name'] ?? '';
  $title = $_POST['title'] ?? '';
  $notes = $_POST['notes'] ?? '';
  $assignee = $_POST['assignee'] ?? '';
  $status = $_POST['status'] ?? '';
  $attachment_summary = null;

  if(
    isset($_FILES['attachment_summary']) &&
    $_FILES['attachment_summary']['error'] === UPLOAD_ERR_OK
  ){
    $tmp  = $_FILES['attachment_summary']['tmp_name'];
    $orig = basename($_FILES['attachment_summary']['name']);
    $dest = __DIR__ . '/uploads/' . $orig;
    
    if (! move_uploaded_file($tmp, $dest)) {
      die("Failed to save uploaded file.");
    }

    $attachment_summary = $orig;
  }

  if(empty($id)){
    $stmt = $conn->prepare("INSERT INTO universal (name, notes, title, assignee, status, attachment_summary, user_id)VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssssi', $name, $notes, $title, $assignee, $status, $attachment_summary, $uid);
  } else {
    $stmt = $conn->prepare("UPDATE universal SET name = ?, notes = ?, title = ?, assignee = ?, status = ?, attachment_summary = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ssssssii', $name, $notes, $title, $assignee, $status, $attachment_summary, $id, $uid);
  }

  if (! $stmt->execute()) {
    die("Database error: " . $stmt->error);
  }

  $stmt->close();

  header("Location: /Itempilot/home.php");
  exit;
}

  $stmt = $conn->prepare("SELECT id, name, notes, title, assignee, status, attachment_summary FROM universal WHERE user_id = ? ORDER BY id ASC");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $result = $stmt->get_result();

  // ←– INSERT THESE TWO LINES RIGHT HERE
  $rows      = $result->fetch_all(MYSQLI_ASSOC);
  $hasRecord = count($rows) > 0;

  $stmt->close();

?>
<!DOCTYPE html> 
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Universal Home – Airtable Style</title>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  </style>
</head>
<body class="bg-gray-100 min-h-screen">

  <!-- Header -->
  <header class="absolute w-full">
    <section class="flex py-4 justify-between bg-gray-200" id="randomHeader">
      <a href="/ItemPilot/home.php#events">
        <button class="p-2 rounded transition ml-4" aria-label="Go back">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-black cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
      </a>

      <?php if ($hasRecord): ?>
        <?php foreach ($rows as $row): ?>
          <div data-id="<?= $row['id'] ?>">
            <div data-field="title" class="px-4 py-2 text-lg text-center text-black font-semibold">
              <?= htmlspecialchars($row['title']) ?>
            </div>
          </div>

      <div class="flex">
        <a href="edit.php?id=<?= $row['id'] ?>" class="mr-2 bg-gray-50 text-sm hover:bg-gray-100 px-4 py-3 rounded-lg inline-block">Edit Title</a>
      <?php endforeach; ?>
      <?php endif; ?>
        <a id="addIcon" class="flex gap-1 mr-5 bg-gray-50 hover:bg-gray-100 cursor-pointer px-2 rounded-lg">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mt-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        <button class="cursor-pointer text-sm">Create New</button>
        </a>
      </div>
    </section>
    
  <!-- Table -->
  <div class="overflow-x-auto">
    <table class="w-200 md:w-[97%] md:ml-5 md:mr-5 border-separate border-spacing-2">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Name</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Notes</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Assignee</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Status</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Attachment</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Action</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <?php if ($hasRecord): ?>
          <?php foreach ($rows as $row): ?>
            <tr data-id="<?= $row['id'] ?>" class="hover:bg-gray-50">
              <td data-field="name" contenteditable class="px-4 py-2 text-sm text-gray-800">
                <?= htmlspecialchars($row['name']) ?>
              </td>
              <td data-field="notes" contenteditable class="px-4 py-2 text-sm text-gray-800">
                <?= htmlspecialchars($row['notes']) ?>
              </td>
              <td data-field="assignee" contenteditable class="px-4 py-2 text-sm text-gray-800">
                <?= htmlspecialchars($row['assignee']) ?>
              </td>
              <td class="px-2 py-2 text-sm">
                <div data-field="status" contenteditable
                    class="bg-green-700 text-white px-4 py-2 rounded-lg font-semibold">
                  <?= htmlspecialchars($row['status']) ?>
                </div>
              </td>
              <td class="px-4 py-2 text-sm text-gray-800">
                <?php if ($row['attachment_summary']): ?>
                  <img src="uploads/<?= htmlspecialchars($row['attachment_summary']) ?>"
                      alt="" class="w-16 h-16 object-cover rounded-md">
                <?php else: ?>
                  <span class="text-gray-400">No image</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2 text-sm text-red-500 underline">
                <a href="delete.php?id=<?= $row['id'] ?>"
                  onclick="return confirm('Are you sure?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
              No records found for your account.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  </header>

  <!-- Add a new record -->
  <div class="min-h-screen flex items-center justify-center p-4 hidden relative" id="addForm">
   <div class="bg-white w-full max-w-lg p-8 rounded-2xl shadow-lg" id="signup">
    <div class="flex justify-between">
      <a href="insert_universal.php">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="6" x2="18" y2="18" /><line x1="6" y1="18" x2="18" y2="6" /></svg>
      </a>

      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </div>

    <h1 class="text-4xl font-bold text-center md:mb-8 mb-1 md:mt-6 mt-2">Unnamed record</h1></a>
    <form action="insert_universal.php" method="POST" enctype="multipart/form-data" class="space-y-6">
    <?php if (! $hasRecord): ?>
      <div>
        <label for="title" class="block text-gray-700 font-medium mb-2">Tabel Name</label>
        <input type="text" name="title" id="title" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
    <?php endif; ?>

      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Name</label>
        <input type="text" name="name" id="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      
      <div>
        <label for="notes" class="block text-gray-700 font-medium mb-2">Notes</label>
        <input type="text" name="notes" id="notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="assignee" class="block text-gray-700 font-medium mb-2">Assignee</label>
        <input type="text" name="assignee" id="assignee" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="status" class="block text-gray-700 font-medium mb-2">Status</label>
        <select type="text" name="status" id="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
          <option name="do" id="do">To Do</option>
          <option name="progress" id="progress">In Progress</option>
          <option name="done" id="done">Done</option>
        </select>
      </div>
      
      <div>
        <label for="attachment_summary" class="block text-gray-700 font-medium mb-2">Attachment</label>
        <input id="attachment_summary" type="file" name="attachment_summary" accept="image/*" class="w-full border border-gray-300 rounded-lg p-2 text-smfile:bg-pink-100 file:border-0 file:rounded-md file:px-4 file:py-2 file:text-[#B5707D]">
      </div>

      <div>
        <button type="submit" name="universal" class="w-full py-3 bg-black text-white font-semibold rounded-lg transition cursor-pointer">Create New Record</button>
      </div>
    </form>
   </div>
  </div>
  

<script>
    document.addEventListener('DOMContentLoaded', () => {
    const el     = document.getElementById('pageTitle');
    const userId = <?= json_encode($uid) ?>;
    const KEY    = `pageTitle_${userId}`;

    // 1) Load saved title for this user
    const saved = localStorage.getItem(KEY);
    if (saved) {
      el.textContent = saved;
      document.title = saved;
    }

    // 2) On blur or Enter → save under the per‑user key
    function save() {
      const txt = el.textContent.trim();
      if (txt) {
        localStorage.setItem(KEY, txt);
        document.title = txt;
      }
    }

    el.addEventListener('blur', save);
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        el.blur();
      }
    });
    });
    

            
    const addIcon = document.getElementById("addIcon");

    addIcon.addEventListener('click', removeArrow);

    function removeArrow(){
      const arrowIcon = document.getElementById("addForm");
      const removeRecord = document.getElementById("removeRecord");

      arrowIcon.style.display = "flex";
      removeRecord.style.display = "none";
    }

    
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[contenteditable="true"]').forEach(el => {
    el.addEventListener('blur', () => {
      const tr    = el.closest('tr');
      const id    = tr?.dataset.id;
      const field = el.dataset.field;
      const value = el.textContent.trim();

      if (!id || !field) return;

      fetch('update_cell.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id, field, value })
      })
      .then(res => res.json())
      .then(json => {
        if (!json.success) {
          alert('⚠️ Save failed');
        }
      })
      .catch(err => console.error(err));
    });
  });
});
</script>

</body>
</html>


