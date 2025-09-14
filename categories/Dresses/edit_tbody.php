<?php
require_once __DIR__ . '/../../db.php';
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$uid      = (int)($_SESSION['user_id'] ?? 0);
$id       = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$table_id = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);

if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }
if ($id <= 0 || $table_id <= 0) { http_response_code(400); exit('Missing id/table_id'); }

/* Helpers */
$parse_money = function ($s) {
    $s = (string)$s;
    if ($s === '') return null;
    if (preg_match('/-?\d+(?:[.,]\d+)?/', $s, $m)) {
        return (float) str_replace(',', '.', $m[0]);
    }
    return null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linked_initiatives = $_POST['linked_initiatives'] ?? '';
    $executive_sponsor  = $_POST['executive_sponsor'] ?? '';
    $status             = $_POST['status'] ?? '';
    $complete           = $_POST['complete'] ?? '';
    $notes              = $_POST['notes'] ?? '';
    $priority           = $_POST['priority'] ?? ''; // price
    $owner              = $_POST['owner'] ?? '';    // material cost

    // Recompute deadline (profit) to mirror insert_dresses.php
    $priceNum = $parse_money($priority);
    $costNum  = $parse_money($owner);
    $deadlineNum = (is_null($priceNum) && is_null($costNum))
        ? null
        : round((float)$priceNum - (float)$costNum, 2);
    $deadlineDb = is_null($deadlineNum) ? null : number_format($deadlineNum, 2, '.', '');

    $sql = "
        UPDATE dresses
           SET linked_initiatives = ?,
               executive_sponsor  = ?,
               status             = ?,
               complete           = ?,
               notes              = ?,
               priority           = ?,
               owner              = ?,
               deadline           = $deadlineDb
         WHERE id = ? AND table_id = ? AND user_id = ?
    ";
    $stmt = $conn->prepare($sql);
    // types: 8 strings + 3 ints
    $stmt->bind_param(
        'ssssssssiii',
        $linked_initiatives, $executive_sponsor, $status, $complete, $notes,
        $priority, $owner, $deadlineDb,
        $id, $table_id, $uid
    );
    $stmt->execute();
    $stmt->close();

    header("Location: /ItemPilot/home.php?autoload=1&type=dresses&table_id={$table_id}");
    exit;
}

/* Optional GET fetch (rarely used by your UI, but safe to keep) */
$stmt = $conn->prepare("
    SELECT linked_initiatives, executive_sponsor, status, complete, notes, priority, owner, deadline
      FROM dresses
     WHERE id = ? AND table_id = ? AND user_id = ?
     LIMIT 1
");
$stmt->bind_param('iii', $id, $table_id, $uid);
$stmt->execute();
$stmt->bind_result($linked_initiatives, $executive_sponsor, $status, $complete, $notes, $priority, $owner, $deadline);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(404);
    exit("Record #{$id} not found");
}
$stmt->close();

/* If you ever render a standalone edit view, output HTML here using the fetched values.
   In your current flow, rows post directly to this script, so GET is not used. */
echo 'OK';
