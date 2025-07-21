<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

// sanitize
$id    = isset($_POST['id'])    ? (int) $_POST['id']    : 0;
$field = isset($_POST['field']) && in_array($_POST['field'], ['name','notes','assignee','status'])
         ? $_POST['field']
         : '';
$value = isset($_POST['value']) ? trim($_POST['value']) : '';

if ($id && $field !== '') {
    // backtick the column to avoid collisions
    $stmt = $conn->prepare("UPDATE universal SET `$field` = ? WHERE id = ?");
    $stmt->bind_param('si', $value, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);
