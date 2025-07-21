<?php
require_once __DIR__ . '/../../db.php';
 session_start();
  $uid = $_SESSION['user_id'] ?? 'guest';  // or username if you prefer

  $labels = [];
$stmt = $conn->prepare("
  SELECT col_key,label_text
    FROM user_table_labels
   WHERE user_id = ?
");
$stmt->bind_param("i",$uid);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
  $labels[$r['col_key']] = $r['label_text'];
}
$stmt->close();



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

  header("Location: /Itempilot/categories/Universal Table/insert_universal.php");
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
<body class="bg-white min-h-screen">

  <!-- Header -->
  <header class="absolute w-full">
    <section class="flex py-4 justify-between bg-gray-200" id="randomHeader">
      <a href="/ItemPilot/home.php#events">
        <button class="p-2 rounded transition ml-4" aria-label="Go back">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-black cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
      </a>

      <?php if ($hasRecord): ?>
      <?php $first = $rows[0];?>
        <div data-id="<?= $first['id'] ?>" class="px-4 py-2 text-center flex">
          <div data-field="title" contenteditable class="text-lg font-semibold text-black"><?= htmlspecialchars($first['title']) ?></div>
        </div>
        
        <div class="flex gap-4">
          <a href="edit.php?id=<?= $first['id'] ?>" class=" bg-gray-50 text-sm hover:bg-gray-100 px-4 py-3 rounded-lg">Edit Title</a>
        <?php endif; ?>
        <a id="addIcon" class="flex gap-1 mr-5 bg-gray-50 hover:bg-gray-100 cursor-pointer px-2 rounded-lg">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mt-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        <button class="cursor-pointer text-sm">Create New</button>
        </a>
      </div>
    </section>
    
    <div class="overflow-x-auto md:mx-8 mt-5">
      <table class="md:w-full w-240 divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Assignee</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Attachment</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if($hasRecord): foreach($rows as $r): ?>
            <tr class="odd:bg-gray-100 hover:bg-gray-200">
              <td data-field="name"      contenteditable class="px-4 py-2 text-sm text-gray-900">
                <?= htmlspecialchars($r['name']) ?>
              </td>
              <td data-field="notes"     contenteditable class="px-4 py-2 text-sm text-gray-700">
                <?= htmlspecialchars($r['notes']) ?>
              </td>
              <td data-field="assignee"  contenteditable class="px-4 py-2 text-sm text-gray-900">
                <?= htmlspecialchars($r['assignee']) ?>
              </td>
              <td class="px-4 py-2">
                <span data-field="status" contenteditable class="px-2 py-1 text-xs bg-green-100 rounded">
                  <?= htmlspecialchars($r['status']) ?>
                </span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-500">
                <?php if ($r['attachment_summary']): ?>
                  <img src="uploads/<?= htmlspecialchars($r['attachment_summary']) ?>"
                      class="w-10 h-10 rounded-md" alt="Attachment">
                <?php else: ?>
                  <span class="italic text-gray-400">None</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2 text-left text-sm">
                <a href="delete.php?id=<?= $r['id'] ?>"
                  onclick="return confirm('Are you sure?')"
                  class="inline-block px-2 py-1 text-red-500 border border-red-500 rounded hover:bg-red-50 transition">
                  <!-- Trash Icon -->
                  <svg xmlns="http://www.w3.org/2000/svg" class="inline h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7
                            m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/>
                  </svg>
                </a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr>
              <td colspan="6" class="px-4 py-2 text-center text-gray-500">
                No records found.
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
    
document.querySelectorAll('th[contenteditable]').forEach(th=>{
  th.addEventListener('blur',()=>{
    fetch('update_label.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`col_key=${th.dataset.key}&label_text=${encodeURIComponent(th.textContent)}`
    });
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


