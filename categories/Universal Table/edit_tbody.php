<?php
// edit_universal.php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? '';
  $name = $_POST['name'] ?? '';
  $notes = $_POST['notes'] ?? '';
  $assignee = $_POST['assignee'] ?? '';
  $status = $_POST['status'] ?? '';

  $sql = "UPDATE universal SET name = ?, notes = ?, assignee = ?, status = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssi', $name, $notes, $assignee, $status ,$id);
  if ($stmt->execute()) {
    header("Location: /ItemPilot/home.php");
    exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT name, notes, assignee, status FROM universal WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($name, $notes, $assignee, $status );
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
<div class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <!-- Register Form -->
  <div class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg" id="signup">
    <a href="/ItemPilot/home.php" class="text-blue-500 underline"><svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-600 hover:text-gray-800 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></a>
    <div class="flex gap-1 justify-center items-center">  
      <h1 class="text-4xl font-bold text-center mb-8 mt-6">Edit Data</h1></a>
    </div>
    <form method="POST">
      <input type="hidden" name="id" value="<?= $id ?>">

      <div>
        <label for="name" class="block text-gray-700 font-medium mb-2">Name</label>
        <input type="text" name="name" id="name" value="<?= htmlspecialchars($name) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>
      
      <div>
        <label for="notes" class="block text-gray-700 font-medium mb-2">Notes</label>
        <input type="text" name="notes" id="notes" value="<?= htmlspecialchars($notes) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="assignee" class="block text-gray-700 font-medium mb-2">Assignee</label>
        <input type="text" name="assignee" id="assignee" value="<?= htmlspecialchars($assignee) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div>
        <label for="status" class="block text-gray-700 font-medium mb-2">Status</label>
        <input type="text" name="status" id="status" value="<?= htmlspecialchars($status) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
      </div>

      <div class="mt-4 flex justify-between">
        <button type="submit" class="px-6 py-2 bg-black text-white rounded-lg">Save</button>
      </div>
    </form>
  </div>
</body>
</html>