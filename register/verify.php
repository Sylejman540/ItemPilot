<?php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$email = $_SESSION['verify_email'] ?? null;

if (!$email) {
    header("Location: /ItemPilot/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    $stmt = $conn->prepare("SELECT id, verify_code FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();

        if ($user['verify_code'] === $code) {
            // Code matches → verify session
            $upd = $conn->prepare("UPDATE users SET is_verified=1, verify_code=NULL WHERE id=?");
            $upd->bind_param("i", $user['id']);
            $upd->execute();

            $_SESSION['user_id'] = $user['id'];
            unset($_SESSION['verify_email']);

            header("Location: /ItemPilot/home.php");
            exit;
        } else {
            $error = "Invalid code. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
  <link rel="icon" href="images/icon.png"/>
  <title>ItemPilot</title>
</head>
<body class="flex items-center justify-center h-screen bg-gray-100 w-full">
  <div class="bg-white p-8 rounded-xl shadow-lg md:w-96">
    <h2 class="text-2xl font-bold text-center mb-4">Verify Your Email</h2>
    <p class="text-gray-600 text-center mb-6">
      A verification code was sent to <br><strong><?php echo htmlspecialchars($email); ?></strong>
    </p>
    <?php if (!empty($error)): ?>
      <p class="text-red-500 text-center mb-4"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="post" class="space-y-4">
      <input type="text" name="code" placeholder="Enter code"
        class="w-full px-4 py-2 border-gray-200 border-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
      <button type="submit"
        class="w-full bg-blue-900 text-white py-2 rounded-lg hover:bg-blue-800">Verify</button>
    </form>
  </div>
</body>
</html>
