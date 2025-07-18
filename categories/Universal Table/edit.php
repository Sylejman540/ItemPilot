<?php
// edit_universal.php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name     = $_POST['name']     ?? '';
  $notes    = $_POST['notes']    ?? '';
  $assignee = $_POST['assignee'] ?? '';
  $status = $_POST['status'] ?? '';

  $sql = "UPDATE universal SET name = ?, notes = ?, assignee = ?, status = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssi', $name, $notes, $assignee, $status, $id);
  if ($stmt->execute()) {
    header("Location: insert_universal.php");
    exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT name, notes, assignee, status FROM universal WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($name, $notes, $assignee, $status);
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <link rel="icon" href="/images/icon.png"/>
  <title>ItemPilot</title>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <!-- Register Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg" id="signup">
    <div class="flex gap-1 justify-center items-center">
      <img src="/ItemPilot/images/icon.png" alt="Icon" class="w-15 h-15">
      <h1 class="text-4xl font-bold text-center mb-8 mt-6">Edit Table</h1></a>
    </div>
    <form method="POST" enctype="multipart/form-data" class="space-y-6">
      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($name)?>" id="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Notes</label>
        <input type="text" name="notes" id="notes" value="<?= htmlspecialchars($notes)?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Assignee</label>
        <input type="assigne" name="assignee" id="assignee" value="<?= htmlspecialchars($assignee)?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Status</label>
        <select type="status" name="status" id="status" value="<?= htmlspecialchars($status)?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
          <option name="do" id="do">To Do</option>
          <option name="progress" id="progress">In Progress</option>
          <option name="done" id="done">Done</option>
        </select> 
      </div>

      <div>
        <a href="insert_universal.php"><button type="submit" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition cursor-pointer">Edit Table</button></a>
      </div>

      <div class="flex justify-center">
        <a href="insert_universal.php" class="text-center text-blue-500 underline">Go Back</a>
      </div>
    </form>
  </div>
</body>
</html>