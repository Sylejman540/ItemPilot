<?php
require_once __DIR__ . '/../../db.php';
session_start();
$uid = $_SESSION['user_id'] ?? 0;

if ($uid <= 0) {
  // Redirect to login or deny access
  header("Location: /register/login.php");
  exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'];
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
  
    $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  header("Location: /ItemPilot/home.php?table_id=$newId");
  exit;
}
  $stmt = $conn->prepare("SELECT id, name, notes, title, assignee, status, attachment_summary FROM universal WHERE user_id = ? ORDER BY id ASC LIMIT 4");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $result = $stmt->get_result();

  // ←– INSERT THESE TWO LINES RIGHT HERE
  $rows      = $result->fetch_all(MYSQLI_ASSOC);
  $hasRecord = count($rows) > 0;

  $stmt = $conn->prepare("SELECT * FROM universal_thead WHERE user_id = ? ORDER BY id DESC LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $result = $stmt->get_result();
  $thead = $result->fetch_assoc();
  $stmt->close();
?>

  <!-- Header -->
  <header class="absolute md:w-[75%] md:ml-16 md:mr-16 w-[100%] h-96 bg-white">
    <section class="md:flex py-4 md:justify-between justify-center md:ml-0 ml-3" id="randomHeader">
      <?php if ($hasRecord): ?>
      <?php $first = $rows[0];?>
        <div data-id="<?= $first['id'] ?>" class="px-4 py-2 text-center flex">
          <div data-field="title" class="text-lg font-semibold  text-center text-black"><?= htmlspecialchars($first['title']) ?></div>
        </div>
        
        <div class="flex md:gap-4 justify-between">
        <a href="#"  id="openForm"  data-id="<?= $first['id'] ?>"  data-title="<?= htmlspecialchars($first['title']) ?>"  class="bg-gray-100 text-sm hover:bg-gray-200 px-4 py-3 rounded-lg">Edit Title</a>
        <?php endif; ?>
        <button id="addIcon" type="button" class="flex items-center gap-1 mr-5 bg-gray-100 hover:bg-gray-200 px-2 rounded-lg cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
        <span class="text-sm">Create New</span>
      </button>
      </div>
    </section>

    <div class="md:mx-8 mt-5 overflow-x-auto md:ml-0 ml-3">
      <table class="md:w-full w-240 divide-y divide-gray-200 border-collapse border border-gray-300">
        <thead class="bg-[#333333] text-white h-10">
          <tr>
            <th class="border-l px-4 py-2 text-xs font-medium uppercase">
              <?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?>
            </th>
            <th class="px-4 py-2 text-xs font-medium uppercase">
              <?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?>
            </th>
            <th class="px-4 py-2 text-xs font-medium uppercase">
              <?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?>
            </th>
            <th class="px-4 py-2 text-xs font-medium uppercase">
              <?= htmlspecialchars($thead['thead_status'] ?? 'Status') ?>
            </th>
            <th class="px-4 py-2 text-xs font-medium uppercase">
              <?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment') ?>
            </th>
            <th class="px-4 py-2 text-xs font-medium uppercase">
              <button id="openTbodyForm" class="text-blue-400 underline">Edit</button>
            </th>
          </tr>
        </thead>

        <?php if($hasRecord): foreach($rows as $r): ?>
        <?php $rowIndex = 0; ?>
        <tbody class="divide-y divide-gray-200">
            <tr>
              <td data-row="<?= $rowIndex ?>" data-col="0" class="border-l border-gray-300 px-4 py-2 text-sm text-gray-900" tabindex="0">
                <?= htmlspecialchars($r['name']) ?>
              </td>
              <td data-row="<?= $rowIndex ?>" data-col="1" class="border-l border-gray-300 px-4 py-2 text-sm text-gray-700" tabindex="0">
                <?= htmlspecialchars($r['notes']) ?>
              </td>
              <td data-row="<?= $rowIndex ?>" data-col="2" class="border-l border-gray-300 px-4 py-2 text-sm text-gray-900" tabindex="0">
                <?= htmlspecialchars($r['assignee']) ?>
              </td>
              <td class="border-l border-gray-300 px-4 py-2">
                <span data-row="<?= $rowIndex ?>" data-col="3" class="border-l border-gray-300 px-2 py-1 text-xs bg-green-100 rounded" tabindex="0">
                  <?= htmlspecialchars($r['status']) ?>
                </span>
              </td>
              <td data-row="<?= $rowIndex ?>" data-col="4" class="border-l border-gray-300 px-4 py-2 text-sm text-gray-500" tabindex="0">
                <?php if ($r['attachment_summary']): ?>
                  <img src="categories/Universal Table/uploads/<?= htmlspecialchars($r['attachment_summary']) ?>" class="w-10 h-10 rounded-md" alt="Attachment">
                <?php else: ?>
                  <span class="italic text-gray-400">None</span>
                <?php endif; ?>
              </td>
              <td class="border-l border-gray-300 px-2 py-4 text-left text-sm flex gap-5">
                <a href="/ItemPilot/categories/Universal Table/delete.php?id=<?= $r['id'] ?>" onclick="return confirm('Are you sure?')" class="inline-block px-2 py-1 text-red-500 border border-red-500 rounded hover:bg-red-50 transition">
                  <!-- Trash Icon -->
                  <svg xmlns="http://www.w3.org/2000/svg" class="inline h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/></svg>
                </a>
                <a id="openTheadForm" href="/ItemPilot/categories/Universal Table/edit_tbody.php?id=<?= $r['id'] ?>" class="inline-block px-2 py-1 text-blue-500 border border-blue-500 rounded hover:bg-blue-50 transition" id="edit-button">
                  <!-- Pen Icon -->
                  <svg xmlns="http://www.w3.org/2000/svg" class="inline h-4 w-4" fill="none" stroke="blue" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 20h9" /><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z" /></svg>
                </a>
              </td>
            </tr>
            <?php $rowIndex++; ?>
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
    <?php $first = $rows[0];?>
      <div data-id="<?= $first['id'] ?>" class="px-4 py-2 text-center flex">
      <div data-field="title" class="text-lg font-semibold  text-center text-black"><?= htmlspecialchars($first['title']) ?></div>
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
        <button type="submit" name="universal" class="w-full py-3 bg-black text-white font-semibold rounded-lg transition cursor-pointer">Create New Record</button>
      </div>
    </form>
   </div>
  </div>


  <!-- Edit Title -->
  <div id="editFormWrapper" class="flex items-center justify-center p-4 hidden cursor-pointer">
    <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg relative">
      <button data-close-modal class="absolute top-3 left-4 text-gray-500 hover:text-black">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>


      <h1 class="text-3xl font-bold text-center mb-6">Edit Title</h1>

      <form method="POST" action="/ItemPilot/categories/Universal Table/edit.php?id=<?= $first['id'] ?>">

        <input type="hidden" name="id" value="<?= $id ?>">

        <div>
          <label for="title" class="block text-gray-700 font-medium mb-2">Title</label>
          <input type="text" name="title" id="title" value="<?= htmlspecialchars($first['title']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
        </div>

        <div class="mt-4 flex justify-between">
          <button type="submit" class="px-6 py-2 bg-black text-white rounded-lg">Save</button>
        </div>
      </form>
    </div>
  </div>

 <div id="theadForm" class="fixed inset-0 flex items-center hidden justify-center p-4 cursor-pointer">
  <!-- Edit Thead Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg relative">
    <!-- Close Button -->
    <a href="#" data-close-thead class="absolute top-12">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-600 hover:text-gray-800 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </a>

    <div class="flex gap-1 justify-center items-center">  
      <h1 class="text-4xl font-bold text-center mb-8 mt-6">Edit Table Head</h1>
    </div>

    <form action="/ItemPilot/categories/Universal%20Table/edit_thead.php?id=<?= $r['id'] ?>" method="post">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">

      <div>
        <label><?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?></label>
        <input type="text" name="thead_name" id="thead_name" value="<?= htmlspecialchars($r['thead_name'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      
      <div>
        <label><?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?></label>
        <input type="text" name="thead_notes" id="thead_notes" value="<?= htmlspecialchars($r['thead_notes'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label><?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?></label>
        <input type="text" name="thead_assignee" id="thead_assignee" value="<?= htmlspecialchars($r['thead_assignee'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label><?= htmlspecialchars($thead['thead_status'] ?? 'Status') ?></label>
        <input type="text" name="thead_status" id="thead_status" value="<?= htmlspecialchars($r['thead_status'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label><?= htmlspecialchars($thead['thead_attachment'] ?? 'Attachment') ?></label>
        <input type="text" name="thead_attachment" id="thead_attachment" value="<?= htmlspecialchars($r['thead_attachment'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="mt-7 flex justify-between">
        <button type="submit" class="px-6 py-2 bg-black text-white rounded-lg">Save</button>
      </div>
    </form>
  </div>
</div>


  <div id="tbodyForm" class="fixed inset-0 flex items-center justify-center p-4 hidden cursor-pointer">
  <!-- Edit Tbody Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg relative">
          <a href="#" data-close-thead class="absolute top-12">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-600 hover:text-gray-800 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </a>
    <div class="flex gap-1 justify-center items-center">  
      <h1 class="text-4xl font-bold text-center mb-8 mt-6">Edit Data</h1></a>
    </div>
    <form  action="/ItemPilot/categories/Universal Table/edit_tbody.php?id=<?= $r['id'] ?>" method="post">
      <input type="hidden" name="id" value="<?= $id ?>">

      <div>
        <label><?= htmlspecialchars($thead['thead_name'] ?? 'Name') ?></label>
        <input type="text" name="name" id="name" value="<?= htmlspecialchars($first['name']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      
      <div>
        <label><?= htmlspecialchars($thead['thead_notes'] ?? 'Notes') ?></label>
        <input type="text" name="notes" id="notes" value="<?= htmlspecialchars($first['notes']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label><?= htmlspecialchars($thead['thead_assignee'] ?? 'Assignee') ?></label>
        <input type="text" name="assignee" id="assignee" value="<?= htmlspecialchars($first['assignee']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label><?= htmlspecialchars($thead['thead_status'] ?? 'Status') ?></label>
        <input type="text" name="status" id="status" value="<?= htmlspecialchars($first['status']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="mt-7 flex justify-between">
        <button type="submit" class="px-6 py-2 bg-black text-white rounded-lg">Save</button>
      </div>
    </form>
  </div>
  </div>

</body>
</html>


