<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: /index.php');
  exit();
}

$mysqli = new mysqli('localhost', 'root', '', 'flora_ai');
if ($mysqli->connect_errno) {
  die("Connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

$user_id = intval($_SESSION['user_id']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['delete_account'])) {
    $mysqli->query("DELETE FROM users WHERE id=$user_id");
    session_destroy();
    header('Location: /Flora AI/Main Page/index.php');
    exit();
  }

  $name = $mysqli->real_escape_string(trim($_POST['name']));
  $email = $mysqli->real_escape_string(trim($_POST['email']));
  $password = trim($_POST['password']);
  $language = $mysqli->real_escape_string($_POST['language'] ?? 'en');
  $notifications = isset($_POST['notifications']) ? 1 : 0;

  if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET name='$name', email='$email', password='$hashed', language='$language', notifications=$notifications WHERE id=$user_id";
  } else {
    $sql = "UPDATE users SET name='$name', email='$email', language='$language', notifications=$notifications WHERE id=$user_id";
  }

  if ($mysqli->query($sql)) {
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $message = '✅ Profile updated successfully!';
  } else {
    $message = '❌ Update failed: ' . $mysqli->error;
  }
}

$user = $mysqli->query("SELECT name, email, language, notifications FROM users WHERE id=$user_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="../image/logo.png">
  <title>Flora AI • Manage Account</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen">


  <div class="flex items-center justify-center md:ml-0 md:mr-0 ml-5 mr-5">
  <div class="max-w-xl w-full bg-white p-8 rounded-2xl shadow-2xl border border-pink-100">
    <h1 class="text-3xl font-extrabold text-center text-[#B5707D] mb-6">Manage Your Account</h1>

    <?php if ($message): ?>
      <div id="success-message" class="mb-4 text-sm text-green-800 bg-green-100 border border-green-300 p-3 rounded-lg text-center shadow">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Name</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>"
               class="w-full border border-gray-300 p-3 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-pink-300">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>"
               class="w-full border border-gray-300 p-3 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-pink-300">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">New Password</label>
        <input type="password" name="password" placeholder="Leave blank to keep current"
               class="w-full border border-gray-300 p-3 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-pink-300">
      </div>

      <div>
        <label class="inline-flex items-center">
          <input type="checkbox" name="notifications" value="1" <?= $user['notifications'] ? 'checked' : '' ?>
                 class="rounded text-pink-500 focus:ring-pink-300">
          <span class="ml-2 text-sm text-gray-700">Receive Email Notifications</span>
        </label>
      </div>

      <div class="flex justify-between items-center">
        <button type="submit"
                class="bg-[#B5707D] hover:bg-[#A25C6A] text-white px-6 py-3 rounded-full font-semibold shadow-md transition">
          Save Changes
        </button>
        <form method="POST" class="mt-6 text-center" onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.');">
              <input type="hidden" name="delete_account" value="1">
              <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Delete Account</button>
        <form>
      </div>
    </form>
  </div>
</div>

</body>
</html>
