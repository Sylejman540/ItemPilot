<?php
require_once __DIR__ . '/../../db.php';
session_start();
$uid = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id']       ?? '';
    $name     = $_POST['name']     ?? '';
    $notes    = $_POST['notes']    ?? '';
    $assignee = $_POST['assignee'] ?? '';
    $status = $_POST['status'] ?? '';

    // Handle file upload (optional)
    $attachment_summary = null;
    if (
      isset($_FILES['attachment_summary']) &&
      $_FILES['attachment_summary']['error'] === UPLOAD_ERR_OK
    ) {
      $tmp  = $_FILES['attachment_summary']['tmp_name'];
      $orig = basename($_FILES['attachment_summary']['name']);
      $dest = __DIR__ . '/uploads/' . $orig;
      if (! move_uploaded_file($tmp, $dest)) {
        die("Failed to save uploaded file.");
      }
      $attachment_summary = $orig;
    }

    if (empty($id)) {
      $stmt = $conn->prepare("INSERT INTO universal(name, notes, assignee, status, attachment_summary, user_id)VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param('sssssi', $name, $notes, $assignee, $status, $attachment_summary, $uid);
    } else {
      $stmt = $conn->prepare("UPDATE universal SET name= ?, notes = ?, assignee = ?, status = ?, attachment_summary = ? WHERE id = ? AND user_id = ?");
      $stmt->bind_param('sssssii', $name, $notes, $assignee, $status, $attachment_summary, $id, $uid);
    }

    if (! $stmt->execute()) {
      die("Database error: " . $stmt->error);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, notes, assignee, status, attachment_summary FROM universal WHERE user_id = ? ORDER BY id ASC");
$stmt->bind_param('i', $uid);
$stmt->execute();
$result = $stmt->get_result();
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
  <header>
    <section class="flex justify-around bg-green-600 py-4">
      <a href="/ItemPilot/home.php">
        <button class="p-2 rounded transition" aria-label="Go back">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
      </a>

      <h1 id="pageTitle" class="text-white font-semibold text-xl mt-1" contenteditable="true">Untitled Base</h1>

      <button title="Invite team member" class="flex-shrink-0 p-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v6m3-3h-6m-6 0a4 4 0 1 0-8 0 4 4 0 0 0 8 0z" /></svg>
      </button>
    </section>
    
  <!-- Table -->
  <div class="overflow-x-auto">
    <table class="w-200 md:w-[97%] md:ml-5 md:mr-5">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Notes</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Assignee</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Status</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Attachment Summary</th>
          <th class="px-4 sm:px-6 py-3 text-left text-sm font-medium text-gray-600">Action</th>
        </tr>
      </thead>
      <tbody class="bg-white">
        <?php if ($result->num_rows): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-2 text-sm text-gray-800"><?= htmlspecialchars($row['notes']) ?></td>
          <td class="px-4 py-2 text-sm text-gray-800"><?= htmlspecialchars($row['assignee']) ?></td>
          <td class="px-4 py-2 text-sm text-white font-semibold"><div class="bg-green-400 rounded-lg px-4 md:w-[40%] w-[80%] py-2"><?= htmlspecialchars($row['status']) ?></div></td>
          <td class="px-4 py-2 text-sm text-gray-800"><?php if ($row['attachment_summary']): ?>
          <img src="uploads/<?= htmlspecialchars($row['attachment_summary']) ?>" alt="Attachment" class="w-16 h-16 object-cover rounded-md">
        <?php else: ?>
          <span class="text-gray-400">No image</span>
        <?php endif; ?>
          </td>
          <td class="px-8 py-2 text-sm text-red-500 underline"><a href="delete.php?id=<?= $row['id'] ?>"onclick="return confirm('Are you sure?')">Delete</a><a class="text-blue-500 underline px-3" href="edit.php?id=<?= $row['id'] ?>">Edit</a></td>
        </tr>
          <?php endwhile; ?>
          <?php else: ?>
        <tr>   
          <td colspan="5" class="px-4 py-6 text-center text-gray-500">
            No records found for your account.
          </td>
        </tr>
          <?php endif; ?>
      </tbody>
    </table>
  </div>
  </header>

  <section class="flex justify-center">
    <img src="/ItemPilot/images/arrow.png?v2" alt="arrow" class="absolute w-30 h-30 bottom-15 mr-10" id="arrowIcon">
  </section>
  

  <footer class="absolute bottom-0 flex justify-around bg-gray-200 w-full py-3">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round"  stroke-linejoin="round"  d="M4 6h16M4 12h16M4 18h16" /></svg>
    
    <a href="universal.php" id="addIcon">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
    </a>

    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round"  stroke-linejoin="round"  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
  </footer>





<script>
    document.addEventListener('DOMContentLoaded', () => {
      const title = document.getElementById('pageTitle');

      const saved = localStorage.getItem('pageTitle');
      if (saved) title.textContent = saved;

      title.addEventListener('blur', () => {
        localStorage.setItem('pageTitle', title.textContent.trim());
      });
    });

    const addIcon = document.getElementById("addIcon");

    addIcon.addEventListener('click', removeArrow);

    function removeArrow(){
      const arrowIcon = document.getElementById("arrowIcon");

      arrowIcon.style.display = "none";
    }
</script>
</body>
</html>


