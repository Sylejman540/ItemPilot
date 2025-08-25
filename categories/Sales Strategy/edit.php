  <?php
  require_once __DIR__ . '/../../db.php';
  session_start();

  $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
  if (!$table_id) {
      $table_id = filter_input(INPUT_GET, 'table_id', FILTER_VALIDATE_INT);
  }
  if (!$table_id) {
      http_response_code(400);
      exit('No valid table_id provided');
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $table_title = $_POST['table_title'] ?? '';

     $stmt = $conn->prepare("UPDATE sales_table SET table_title = ? WHERE table_id = ? AND user_id = ?");
      if (!$stmt) { http_response_code(500); exit('Prepare failed: '.$conn->error); }

      $stmt->bind_param('sii', $table_title, $table_id, $uid);
      if (!$stmt->execute()) {
          http_response_code(500);
          exit('Update failed: ' . $stmt->error);
      }
      $stmt->close();

      header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
      exit;
  }

  $stmt = $conn->prepare("SELECT table_title FROM `tables` WHERE table_id = ?");
  $stmt->bind_param('i', $table_id);
  $stmt->execute();
  $stmt->bind_result($table_title);
  if (!$stmt->fetch()) {
      $stmt->close();
      exit("Record #{$table_id} not found");
  }
  $stmt->close();
