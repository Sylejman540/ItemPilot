<?php

require_once __DIR__ . '/../../db.php';

// 1) Handle POST (always)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name     = $_POST['name']     ?? '';
    $notes    = $_POST['notes']    ?? '';
    $assignee = $_POST['assignee'] ?? '';

    if (
        isset($_FILES['attachment_summary'])
        && $_FILES['attachment_summary']['error'] === UPLOAD_ERR_OK
    ) {
        $tmp    = $_FILES['attachment_summary']['tmp_name'];
        $orig   = basename($_FILES['attachment_summary']['name']);
        // optional: sanitize $orig or generate a unique name
        $dest   = __DIR__ . '/uploads/' . $orig;
        if (!move_uploaded_file($tmp, $dest)) {
            die("Failed to save uploaded file.");
        }
        $attachment_summary = $orig;
    } else {
        die("Upload error.");
    }

    if($id = 'id'){
      $stmt = $conn->prepare("INSERT INTO universal (id, name, notes, assignee, attachment_summary) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param('issss', $id, $name, $notes, $assignee, $attachment_summary);
      if (!$stmt->execute()) {
        die("Insert error: " . $stmt->error);
      }
      $stmt->close();
      header("Location: " . $_SERVER['PHP_SELF']);
      exit;
    }
}

$result = $conn->query("SELECT id, name, notes, assignee, attachment_summary FROM universal ORDER BY id ASC");
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
  <header class="sticky top-0 bg-white shadow z-10">
    <div class="w-full max-w-screen-3xl mx-auto flex flex-wrap items-center justify-between py-4 px-4 sm:px-6 lg:px-8">
      
      <!-- Left group: Back arrow + Title -->
      <div class="flex items-center space-x-3 w-full sm:w-auto mb-3 sm:mb-0">
        <a href="/ItemPilot/home.php"><button class="p-2 rounded hover:bg-gray-100 transition" aria-label="Go back">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
        </a>
        <h1 class="text-2xl font-semibold text-gray-800">Universal Home</h1>
      </div>

      <!-- Right group: Search + Invite + Add + O+ -->
      <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar px-2">
        <input type="text" placeholder="Search records…" class="flex-shrink-0 w-48 sm:w-64 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"/>
    
        <a href="universal.php">
        <button class="flex-shrink-0 flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>Add record
        </button>
        </a>
        <button title="Invite team member" class="flex-shrink-0 p-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v6m3-3h-6m-6 0a4 4 0 1 0-8 0 4 4 0 0 0 8 0z" /></svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Main Table -->
  <main class="mt-6 max-w-screen-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
      <div class="overflow-x-auto">
        <table class="divide-y divide-gray-200 md:w-[100%] w-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Name</th>
              <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Notes</th>
              <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Assignee</th>
              <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Attachment Summary</th>
              <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Delete Table</th>
              <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Edit Table</th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php if ($result->num_rows): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm text-gray-800"><?= htmlspecialchars($row['name']) ?></td>
                    <td class="px-4 py-2 text-sm text-gray-800"><?= htmlspecialchars($row['notes']) ?></td>
                    <td class="px-4 py-2 text-sm text-gray-800"><?= htmlspecialchars($row['assignee']) ?></td>
                    <td class="px-4 py-2 text-sm text-gray-800">
                    <?php if ($row['attachment_summary']): ?>
                        <img
                        src="uploads/<?= htmlspecialchars($row['attachment_summary']) ?>"
                        alt="Attachment"
                        class="w-16 h-16 object-cover rounded-md"
                        >
                    <?php else: ?>
                        <span class="text-gray-400">No image</span>
                    <?php endif; ?>
                    </td>
                    <td class="px-8 py-2 text-sm text-red-500 underline"><a href="delete.php?id=<?= $row['id'] ?>"onclick="return confirm('Are you sure?')">Delete</a></td>
                    <td class="px-8 py-2 text-sm text-blue-500 underline"><a href="edit.php?id=<?= $row['id'] ?>">Edit</a></td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr>   
                <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                    No records found for your account.
                </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
      </div>
    </div>
  </main>

<script>
    const deleteRecordBtn = document.getElementById("delete-record")

    deleteRecordBtn.addEventListener('click', removeButton());

    function removeButton(){
        deleteRecordBtn.style.display = "none";
    }
</script>
</body>
</html>


