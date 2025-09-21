<?php
require_once __DIR__ . '/../../db.php';
session_start();

/* ---------- helpers ---------- */
function is_ajax(): bool {
  return (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
  ) || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
}
function json_out(array $payload, int $code = 200) {
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Location');
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) json_out(['ok'=>false, 'error'=>'Unauthorized'], 401);

$table_id    = (int)($_POST['table_id'] ?? 0);
$field_label = trim($_POST['field_name'] ?? '');

if ($table_id <= 0) json_out(['ok'=>false, 'error'=>'Missing table_id'], 400);
if ($field_label === '') json_out(['ok'=>false, 'error'=>'Field name is required'], 400);

/* ---------- normalize to a safe SQL column ---------- */
// Make a safe snake_case column (letters, digits, underscore)
$col = strtolower($field_label);
$col = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $col);
$col = preg_replace('/[^a-z0-9_]+/', '_', $col);
$col = preg_replace('/_{2,}/', '_', $col);
$col = trim($col, '_');
if ($col === '' || ctype_digit(substr($col, 0, 1))) $col = 'f_' . $col;
$col = substr($col, 0, 60); // keep it reasonable

// Ensure uniqueness in universal_base
$existsQ = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='universal_base' AND COLUMN_NAME=?");
$existsQ->bind_param('s', $col);
$existsQ->execute();
$existsQ->bind_result($cnt);
$existsQ->fetch();
$existsQ->close();

if ($cnt > 0) {
  // If the column already exists, make a unique one
  $suffix = 2;
  do {
    $try = substr($col . '_' . $suffix, 0, 64);
    $existsQ = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='universal_base' AND COLUMN_NAME=?");
    $existsQ->bind_param('s', $try);
    $existsQ->execute();
    $existsQ->bind_result($cnt2);
    $existsQ->fetch();
    $existsQ->close();
    if ($cnt2 == 0) { $col = $try; break; }
    $suffix++;
  } while (true);
}

/* ---------- add column to universal_base if missing ---------- */
$alterSql = "ALTER TABLE universal_base ADD COLUMN `$col` VARCHAR(255) NULL";
if (!$conn->query($alterSql)) {
  // If it already exists due to race, ignore; otherwise error
  if (stripos($conn->error, 'Duplicate column name') === false) {
    json_out(['ok'=>false, 'error'=>'Failed to alter universal_base: '.$conn->error], 500);
  }
}

/* ---------- insert into universal_fields (metadata) ---------- */
$ins = $conn->prepare("INSERT INTO universal_fields (user_id, table_id, field_name) VALUES (?,?,?)");
$ins->bind_param('iis', $uid, $table_id, $col); // store canonical column key in field_name
if (!$ins->execute()) {
  json_out(['ok'=>false, 'error'=>'Failed to insert field: '.$ins->error], 500);
}
$field_id = (int)$ins->insert_id;
$ins->close();

/* ---------- build HTML snippets to patch UI ---------- */
// THEAD cell (matches your thead form)
ob_start(); ?>
<div class="p-2">
  <input type="text"
         name="extra_field_<?= (int)$field_id ?>"
         value="<?= htmlspecialchars($field_label, ENT_QUOTES, 'UTF-8') ?>"
         placeholder="Field"
         class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
</div>
<?php $thead_html = ob_get_clean();

// TBODY cell input template (appended to each row's dyn area)
ob_start(); ?>
<input type="text"
       name="dyn[<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>]"
       value=""
       class="w-full bg-transparent border-none px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition"/>
<?php $cell_html_template = ob_get_clean();

/* ---------- success ---------- */
json_out([
  'ok'                 => true,
  'table_id'           => $table_id,
  'field_id'           => $field_id,
  'field_label'        => $field_label,
  'field_name'         => $col,                 // canonical column key in universal_base
  'thead_html'         => $thead_html,          // header cell to insert
  'cell_html_template' => $cell_html_template   // input to append into each row dyn area
]);
