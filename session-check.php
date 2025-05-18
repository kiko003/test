<?php
session_start();
header('Content-Type: application/json');
require_once "db.php";

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['valid' => false]);
    exit;
}

// Check that session_id matches what's stored in the database
$user_id = $_SESSION['user_id'];
$session_id = session_id();

$stmt = $conn->prepare("SELECT session_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($db_session_id);
$stmt->fetch();
$stmt->close();

if ($db_session_id !== $session_id) {
    // Session mismatch - probably force logged out
    session_destroy();
    echo json_encode(['valid' => false]);
    exit;
}

// All checks passed, session is valid
echo json_encode(['valid' => true]);
exit;
?>